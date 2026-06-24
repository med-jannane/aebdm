<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tac', 'admin']);

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$id = $_GET['id'];
$msg = "";
$msg_type = "";

// 1. Traitement du Formulaire (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    // Champs détaillés
    $tests = $_POST['tests'] ?? '';
    $resultat = $_POST['resultat'] ?? '';
    $solution = $_POST['solution'] ?? '';
    $duree = $_POST['duree'] ?? 0;
    $moyen = $_POST['moyen'] ?? '';

    if ($action === 'update_site_info') {
        $modem = $_POST['modem'] ?? '';
        $login = $_POST['routeur_login'] ?? '';
        $pass = $_POST['routeur_password'] ?? '';
        $poste = $_POST['poste_inclut'] ?? '';
        $abo = $_POST['type_abonnement'] ?? '';
        $cable = $_POST['cablage'] ?? '';
        $incl = $_POST['inclusions'] ?? '';

        $sqlSite = "UPDATE Sites SET 
                    modem = ?, routeur_login = ?, routeur_password = ?, 
                    poste_inclut = ?, type_abonnement = ?, cablage = ?, inclusions = ?
                    WHERE id = ?";
        // Need to fetch ticket first to get site_id, but ticket fetch is below.
        // We can pass site_id in hidden field? Or just fetch ticket id first.
        // Actually, we can just use the query to get site_id from ticket relation if we don't trust hidden input.
        $tCheck = sqlsrv_fetch_array(query("SELECT ID_SITE FROM TICKET WHERE ID_TICKET = ?", [$id]), SQLSRV_FETCH_ASSOC);
        if ($tCheck && query($sqlSite, [$modem, $login, $pass, $poste, $abo, $cable, $incl, $tCheck['ID_SITE']])) {
            $msg = "Informations site mises à jour.";
            $msg_type = "success";
        } else {
            $msg = "Erreur mise à jour site.";
            $msg_type = "error";
        }
    } 
    elseif ($action === 'take') {
        // ... (Code de prise en charge inchangé)
        $sql = "UPDATE TICKET SET ETAT = 'en_cours_tac' WHERE ID_TICKET = ?";
        if (sqlsrv_query($conn, $sql, [$id])) {
            header("Location: ticket_process.php?id=" . $id . "&msg=taken");
            exit;
        } else {
            error_log('[TAC_TAKE_TICKET] ' . db_last_error_message());
            $msg = "Erreur lors de la prise en charge du ticket.";
            $msg_type = "error";
        }
    } 
    elseif ($action === 'solve' || $action === 'escalate' || $action === 'return_accueil') {
        
        $new_status = ($action === 'solve') ? 'cloture' : (($action === 'return_accueil') ? 'attente_devis' : 'attente_dispatch');
        
        // Mise à jour des champs détaillés + statut
        // On met aussi à jour la description pour l'historique rapide, mais on stocke surtout dans les colonnes dédiées
        $sql = "UPDATE TICKET SET 
                ETAT = ?, 
                MESSAGE_DISPATCH = ?
                WHERE ID_TICKET = ?";
                
        // L'utilisateur a demandé que les "Tests Effectués" servent de message pour le dispatch
        $tests = $_POST['tests'] ?? '';
        $msg_dispatch = "Tests: " . $tests . "\nSolution/Conclusion: " . $solution;
        
        $params = [$new_status, $msg_dispatch, $id];
        
        if (sqlsrv_query($conn, $sql, $params)) {
            $redirectMsg = ($action === 'solve') ? 'solved' : (($action === 'return_accueil') ? 'returned' : 'escalated');
            
            require_once __DIR__ . '/../../utils/NotificationManager.php';
            $nm = new NotificationManager($conn);
            
            if ($action === 'return_accueil') {
                $nm->create("Ticket #$id - Hors contrat détecté par le TAC (retour Accueil).", 'accueil', null, "/sav/src/modules/accueil/tickets.php");
            } elseif ($action === 'escalate') {
                $nm->create("Ticket #$id - Nouveau ticket à planifier (escalade TAC).", 'dispatch', null, "/sav/src/modules/dispatch/assign_tech.php?ticket_id=$id");
            }
            
            header("Location: dashboard.php?msg=" . $redirectMsg);
            exit;
        } else {
            error_log('[TAC_UPDATE_TICKET] ' . db_last_error_message());
            $msg = "Erreur lors de la mise a jour du ticket.";
            $msg_type = "error";
        }
    }
}

// 2. Récupération Ticket
$sql = "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, TICKET.COMMENT as description, TICKET.MESSAGE_DISPATCH as tac_tests, '' as tac_solution, 'Général' as type_probleme, TICKET.OBJET as sujet,
        SAV_Clients.Nom as client_nom, SAV_Clients.ID_Client as client_id, SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville, SAV_Sites.Id_Site as site_id
        FROM TICKET 
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE TICKET.ID_TICKET = ?";
$stmt = query($sql, [$id]);
$ticket = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$ticket) die("Ticket introuvable.");

// Fetch Site History (Tickets & Interventions)
// Tickets (Triés par date décroissante, exclu le ticket actuel)
$sqlTickets = "SELECT ID_TICKET as id, DATE as cree_le, ETAT as statut, COMMENT as description FROM TICKET WHERE ID_SITE = ? AND ID_TICKET != ? ORDER BY DATE DESC";
$historyTickets = [];
$stmt = query($sqlTickets, [$ticket['site_id'], $id]);
if ($stmt) {
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $historyTickets[] = $row;
    }
}

// Interventions
$sqlInterventions = "SELECT I.*, T.ID_TICKET as ticket_id_ref, T.COMMENT as ticket_desc, U.nom_complet as tech_nom
                     FROM Interventions I
                     JOIN TICKET T ON I.ticket_id = T.ID_TICKET
                     LEFT JOIN Users U ON I.tech_id = U.id
                     WHERE T.ID_SITE = ?
                     ORDER BY I.date_planifiee DESC";
$historyInterventions = [];
$stmt = query($sqlInterventions, [$ticket['site_id']]);
if ($stmt) {
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $historyInterventions[] = $row;
    }
}

// Gestion messages URL
if (isset($_GET['msg']) && $_GET['msg'] == 'taken') {
    $msg = "Ticket pris en charge. Remplissez le diagnostic.";
    $msg_type = "success";
}

$pageTitle = "Traitement TAC #" . $ticket['id'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .msg { padding: 15px; border-radius: var(--radius-sm); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .msg.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .form-section-title { font-weight:600; color:var(--primary); margin-bottom:10px; margin-top:20px; display:block; font-size: 1.1em; border-bottom: 2px solid var(--border); padding-bottom: 5px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px; display:inline-block;"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        <header>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1>Ticket #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['client_nom']); ?></h1>
                    <span class="badge badge-<?php echo $ticket['statut']; ?>"><?php echo strtoupper($ticket['statut']); ?></span>
                </div>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="msg <?php echo $msg_type; ?>">
                <i class="fa-solid fa-<?php echo ($msg_type == 'success') ? 'circle-check' : 'circle-exclamation'; ?>"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            <div style="display:flex; flex-direction:column; gap:20px;">
                <div class="card">
                <h3><i class="fa-solid fa-circle-info"></i> Infos Ticket</h3>
                <p><strong><i class="fa-solid fa-location-dot"></i> Site :</strong> <?php echo htmlspecialchars($ticket['site_nom']); ?> (<?php echo htmlspecialchars($ticket['ville']); ?>)</p>
                <p><strong><i class="fa-solid fa-triangle-exclamation"></i> Problème :</strong> <?php echo htmlspecialchars($ticket['type_probleme']); ?></p>
                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button type="button" onclick="openClientDetailsModal('<?php echo htmlspecialchars($ticket['client_id'] ?? ''); ?>')" class="btn btn-sm btn-secondary" style="flex:1; text-align:center;"><i class="fa-solid fa-address-card"></i> Voir Client & tous ses Contrats</button>
                    <button type="button" onclick="openContractModal('<?php echo htmlspecialchars($ticket['client_id'] ?? ''); ?>')" class="btn btn-sm btn-secondary" style="flex:1; text-align:center; background-color:var(--surface-light); border-color:var(--primary); color:var(--primary);"><i class="fa-solid fa-file-contract"></i> Aperçu Contrat Actif</button>
                </div>
                <hr>
                <p><strong>Description :</strong><br><span style="color:var(--text); white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></span></p>
            </div>

            <!-- SITE TECH INFO (Added) -->
            <?php
                $siteDetails = sqlsrv_fetch_array(query("SELECT * FROM SAV_Sites WHERE Id_Site = ?", [$ticket['site_id']]), SQLSRV_FETCH_ASSOC);
            ?>
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin-top:0; color:var(--primary); margin-bottom:10px;"><i class="fa-solid fa-server"></i> Informations Techniques</h3>
                    <button type="button" onclick="document.getElementById('editSiteModal').style.display='block'" class="btn btn-sm btn-secondary"><i class="fa-solid fa-pen-to-square"></i> Modifier</button>
                </div>
                <style>
                    .tech-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
                    .tech-table td { padding: 5px 0; border-bottom: 1px dashed var(--border); }
                    .tech-table td:first-child { color: var(--text-muted); width: 40%; }
                    .tech-table td:last-child { font-weight: 600; text-align: right; }
                </style>
                <table class="tech-table">
                    <tr><td>Modem</td><td><?php echo htmlspecialchars($siteDetails['modem'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Login Routeur</td><td><?php echo htmlspecialchars($siteDetails['routeur_login'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Mot de Passe</td><td><?php echo htmlspecialchars($siteDetails['routeur_password'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Poste Inclut</td><td><?php echo htmlspecialchars($siteDetails['poste_inclut'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Type Abo.</td><td><?php echo htmlspecialchars($siteDetails['type_abonnement'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Câblage</td><td><?php echo htmlspecialchars($siteDetails['cablage'] ?? 'N/A'); ?></td></tr>
                    <tr><td>Inclusions</td><td><?php echo htmlspecialchars($siteDetails['inclusions'] ?? 'N/A'); ?></td></tr>
                </table>
            </div>

            <!-- MODAL EDIT SITE -->
            <div id="editSiteModal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
                <div style="background-color:#fff; margin:10% auto; padding:20px; border:1px solid #888; width:50%; border-radius:var(--radius-md); position:relative;">
                    <span onclick="document.getElementById('editSiteModal').style.display='none'" style="position:absolute; top:10px; right:20px; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
                    <h3 style="color:var(--primary); margin-top:0;"><i class="fa-solid fa-pen"></i> Modifier Informations Techniques</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_site_info">
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label>Modem</label>
                                <input type="text" name="modem" class="form-control" value="<?php echo htmlspecialchars($siteDetails['modem'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Login Routeur</label>
                                <input type="text" name="routeur_login" class="form-control" value="<?php echo htmlspecialchars($siteDetails['routeur_login'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Mot de Passe</label>
                                <input type="text" name="routeur_password" class="form-control" value="<?php echo htmlspecialchars($siteDetails['routeur_password'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Poste Inclut</label>
                                <input type="text" name="poste_inclut" class="form-control" value="<?php echo htmlspecialchars($siteDetails['poste_inclut'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Type Abonnement</label>
                                <input type="text" name="type_abonnement" class="form-control" value="<?php echo htmlspecialchars($siteDetails['type_abonnement'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Câblage</label>
                                <input type="text" name="cablage" class="form-control" value="<?php echo htmlspecialchars($siteDetails['cablage'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Inclusions</label>
                            <textarea name="inclusions" rows="3" class="form-control"><?php echo htmlspecialchars($siteDetails['inclusions'] ?? ''); ?></textarea>
                        </div>
                        
                        <div style="text-align:right; margin-top:15px;">
                            <button type="button" onclick="document.getElementById('editSiteModal').style.display='none'" class="btn btn-secondary">Annuler</button>
                            <button type="submit" class="btn"><i class="fa-solid fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- HISTORY SIDEBAR (Moved here) -->
            <div class="card">
                <h3 style="font-size:1.1em; color:var(--text-main); margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:5px;">
                    <i class="fa-solid fa-file-pdf"></i> Rapports Interventions
                </h3>
                
                <?php if (empty($historyInterventions)): ?>
                    <p class="subtle-text" style="font-size:0.8em;">Aucune intervention passée.</p>
                <?php else: ?>
                    <ul style="list-style:none; padding:0; font-size:0.9em;">
                        <?php 
                        $hasReports = false;
                        foreach(array_slice($historyInterventions, 0, 5) as $hi): 
                            if ($hi['statut'] === 'termine' || $hi['statut'] === 'cloture'):
                                $hasReports = true;
                        ?>
                        <li style="border-bottom:1px dashed var(--border); padding:8px 0; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="color:var(--text-muted); font-size:0.85em; display:block;"><?php echo $hi['date_planifiee'] ? $hi['date_planifiee']->format('d/m/Y') : '-'; ?></span>
                                <strong><?php echo htmlspecialchars($hi['tech_nom'] ?? 'Tech'); ?></strong>
                            </div>
                            <a href="../tech/generate_pdf.php?id=<?php echo $hi['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" style="color:var(--danger); border-color:var(--danger); background:rgba(239,68,68,0.05);">
                                <i class="fa-solid fa-file-pdf"></i> PDF
                            </a>
                        </li>
                        <?php 
                            endif;
                        endforeach; 
                        
                        if (!$hasReports): ?>
                            <li class="subtle-text">Aucun rapport disponible.</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
                
                <div style="margin-top:15px; text-align:center;">
                    <a href="historique_site.php?site_id=<?php echo $ticket['site_id']; ?>" class="btn btn-sm" style="width:100%; background-color:var(--background); border:1px solid var(--border); color:var(--text-muted);"><i class="fa-solid fa-clock-rotate-left"></i> Tout l'historique</a>
                </div>
            </div>
        </div>

            <div class="card">
                <h3><i class="fa-solid fa-microchip"></i> Traitement</h3>
                
                <?php if (in_array($ticket['statut'], ['ouvert', 'nouveau'])): ?>
                    <form method="POST">
                        <div style="text-align:center; padding: 40px;">
                            <i class="fa-solid fa-hand-holding-medical" style="font-size: 3rem; color:var(--primary); margin-bottom: 20px;"></i>
                            <p style="font-size: 1.1em; margin-bottom: 20px;">Ticket en attente de prise en charge.</p>
                            <button type="submit" name="action" value="take" class="btn btn-full"><i class="fa-solid fa-play"></i> Démarrer le Diagnostic</button>
                        </div>
                    </form>

                <?php elseif ($ticket['statut'] == 'en_cours_tac') : ?>
                    <form method="POST">
                        
                        <label class="form-section-title"><i class="fa-solid fa-stethoscope"></i> Diagnostic</label>
                        
                        <div class="form-group">
                            <label>Tests Effectués / Message (Transmis au Dispatch si escaladé) *</label>
                            <textarea name="tests" rows="4" class="form-control" required placeholder="Ex: Ping, Reboot, Vérification logs... Ce message sera lu par le Dispatch."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Solution Appliquée (si résolu)</label>
                            <textarea name="solution" rows="2" class="form-control" placeholder="Si résolu à distance..."></textarea>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:10px;">
                            <div class="form-group">
                                <label>Durée (min)</label>
                                <input type="number" name="duree" value="15" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Moyen utilisé</label>
                                <select name="moyen" class="form-control">
                                    <option value="Téléphone">Téléphone</option>
                                    <option value="Email">Email</option>
                                    <option value="Prise en main">Prise en main (TeamViewer)</option>
                                    <option value="Sur place">Sur place</option>
                                </select>
                            </div>
                        </div>

                        <label class="form-section-title" style="color:var(--warning); border-color:var(--warning);"><i class="fa-solid fa-share-from-square"></i> Actions</label>

                        <!-- ACTION: ESCALATE -->
                        <div style="background:var(--background); padding:15px; border-radius:var(--radius-md); margin-bottom:15px;">
                            <h4 style="margin-top:0;">Besoin d'un technicien ?</h4>
                            <p style="font-size:0.9em; color:var(--text-muted); margin-bottom:10px;">Le diagnostic ci-dessus sera transmis au Dispatch.</p>
                            <button type="submit" name="action" value="escalate" class="btn" style="background-color:var(--warning); color:black; width:100%;">
                                <i class="fa-solid fa-truck-fast"></i> Envoyer au Dispatch
                            </button>
                        </div>

                        <!-- ACTION: SOLVE -->
                        <div style="display:flex; gap:15px;">
                            <button type="submit" name="action" value="solve" class="btn" style="background-color:var(--success); flex:2;">
                                <i class="fa-solid fa-check-circle"></i> Résolu (Clôturer)
                            </button>
                            
                            <button type="submit" name="action" value="return_accueil" class="btn" 
                                    style="background-color: var(--danger); flex:1;"
                                    onclick="return confirm('Confirmez-vous le renvoi à l\'Accueil (Hors Contrat) ? Assurez-vous d\'avoir expliqué la raison dans le message privé.');">
                                <i class="fa-solid fa-ban"></i> Hors Contrat
                            </button>
                        </div>
                    </form>

                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: var(--text-muted);">
                        <i class="fa-solid fa-lock" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Ce ticket est clôturé ou traité.</p>
                    </div>
                    <div style="background:var(--background); padding:15px; border-radius:var(--radius-md); margin-top:15px;">
                        <p><strong>Tests :</strong> <?php echo htmlspecialchars($ticket['tac_tests'] ?? 'Non renseigné'); ?></p>
                        <p><strong>Résultat :</strong> <?php echo htmlspecialchars($ticket['tac_resultat'] ?? 'Non renseigné'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        </div>
    </div>
    <script>
        async function openClientDetailsModal(clientId) {
            document.getElementById('clientDetailsModal').style.display = 'block';
            document.getElementById('clientDetailsContent').innerHTML = '<div style="text-align:center; padding: 20px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Chargement...</div>';
            
            try {
                const response = await fetch(`../api/get_client.php?id=${clientId}`);
                const data = await response.json();
                
                if (data.success) {
                    let html = `<div style="font-size:1.1em; line-height:1.8;">`;
                    for (const [key, value] of Object.entries(data.client)) {
                        if (value === null || value === '' || key.toLowerCase() === 'id') continue;
                        let displayValue = value;
                        if (key.toLowerCase() === 'email') {
                            displayValue = `<a href="mailto:${value}">${value}</a>`;
                        } else if (typeof value === 'string' && value.length > 50) {
                            displayValue = value.replace(/\n/g, '<br>');
                        }
                        const label = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        html += `<p style="margin:5px 0;"><strong>${label} :</strong> <span style="word-break: break-word;">${displayValue}</span></p>`;
                    }
                    html += `</div>`;
                    document.getElementById('clientDetailsContent').innerHTML = html;
                } else {
                    document.getElementById('clientDetailsContent').innerHTML = '<div style="color:var(--danger); text-align:center;">Client introuvable.</div>';
                }
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('clientDetailsContent').innerHTML = '<div style="color:var(--danger); text-align:center;">Erreur réseau lors du chargement.</div>';
            }
        }

        function closeClientDetailsModal() {
            document.getElementById('clientDetailsModal').style.display = 'none';
        }

        async function openContractModal(clientId) {
            document.getElementById('contractModal').style.display = 'block';
            document.getElementById('contractLoading').style.display = 'block';
            document.getElementById('contractDetails').style.display = 'none';
            document.getElementById('contractError').style.display = 'none';

            try {
                const response = await fetch(`../api/get_contrat.php?client_id=${clientId}`);
                const data = await response.json();
                
                document.getElementById('contractLoading').style.display = 'none';

                if(data.success) {
                    document.getElementById('contractDetails').style.display = 'block';
                    
                    const tbody = document.getElementById('contractTableBody');
                    tbody.innerHTML = ''; // Clear previous

                    // Mapping of nice labels for known fields
                    const fieldLabels = {
                        'numero_contrat': 'Numéro Contrat',
                        'date_debut': 'Date de Début',
                        'date_fin': 'Date de Fin',
                        'type': 'Type de contrat',
                        'categorie': 'Catégorie',
                        'materiel': 'Matériel couvert',
                        'details': 'Détails Additionnels',
                        'created_at': 'Créé le'
                    };

                    // First add the special Status row
                    let statusRow = `<tr><td style="width: 40%; padding:8px; border-bottom:1px solid var(--border); color:var(--text-muted);">Statut</td><td style="text-align:right; border-bottom:1px solid var(--border);"><span class="badge badge-${data.contrat.statut_badge}">${data.contrat.status.toUpperCase()}</span></td></tr>`;
                    tbody.insertAdjacentHTML('beforeend', statusRow);

                    // Then iterate through all other fields
                    for (const [key, value] of Object.entries(data.contrat)) {
                         // Skip these special keys, internal IDs
                        if (['status', 'statut_badge', 'id', 'client_id'].includes(key.toLowerCase())) continue;
                        
                        // Ignore empty values so the popup doesn't look empty
                        if (value === null || value === '') continue;

                        const label = fieldLabels[key] || key.charAt(0).toUpperCase() + key.slice(1).replace('_', ' ');
                        let displayValue = value;
                        
                        // Handle long text like 'details' or 'materiel'
                        if (typeof value === 'string' && value.length > 50) {
                            displayValue = value.replace(/\n/g, '<br>');
                        }

                        let row = `<tr><td style="padding:8px; border-bottom:1px solid var(--border); color:var(--text-muted); vertical-align:top;">${label}</td><td style="text-align:right; font-weight:bold; border-bottom:1px solid var(--border); word-break: break-word;">${displayValue}</td></tr>`;
                        tbody.insertAdjacentHTML('beforeend', row);
                    }
                } else {
                    document.getElementById('contractError').style.display = 'block';
                    document.getElementById('contractErrorText').textContent = data.error || "Contrat introuvable.";
                }
            } catch(e) {
                document.getElementById('contractLoading').style.display = 'none';
                document.getElementById('contractError').style.display = 'block';
                document.getElementById('contractErrorText').textContent = "Erreur de connexion au serveur.";
            }
        }

        function closeContractModal() {
            document.getElementById('contractModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const clientModal = document.getElementById('clientDetailsModal');
            const contractModal = document.getElementById('contractModal');
            if (event.target == clientModal) closeClientDetailsModal();
            if (event.target == contractModal) closeContractModal();
        }
    </script>

    <!-- Modal Client Details -->
    <div id="clientDetailsModal" class="modal" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6);">
        <div class="modal-content" style="background-color:var(--background-alt); margin:2% auto; padding:25px; border:none; width:90%; max-width:600px; border-radius:var(--radius-lg); max-height: 95vh; overflow-y:auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid var(--border); padding-bottom:15px; margin-bottom:20px;">
                <h3 style="margin:0; font-size:1.5rem; color:var(--primary);"><i class="fa-solid fa-address-card"></i> Fiche Client</h3>
                <span class="close" style="color:var(--text-muted); font-size:32px; font-weight:bold; cursor:pointer;" onclick="closeClientDetailsModal()">&times;</span>
            </div>
            
            <div id="clientDetailsContent" style="font-size: 1.1em; line-height: 1.8;">
                <div style="text-align:center;"><i class="fa-solid fa-spinner fa-spin"></i> Chargement...</div>
            </div>
            
            <div style="margin-top: 25px; text-align: right; border-top: 1px solid var(--border); padding-top: 15px;">
                <button type="button" class="btn btn-secondary" onclick="closeClientDetailsModal()">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Contract Modal -->
    <div id="contractModal" class="modal" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color:#fefefe; margin:5% auto; padding:20px; border:1px solid #888; width:90%; max-width:600px; border-radius:var(--radius-lg); max-height: 90vh; display: flex; flex-direction: column;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:10px; margin-bottom:15px;">
                <h3 style="margin:0; color:var(--primary);"><i class="fa-solid fa-file-signature"></i> Détails du Contrat Actif</h3>
                <span class="close" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;" onclick="closeContractModal()">&times;</span>
            </div>
            
            <div id="contractLoading" style="text-align:center; padding: 20px;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Chargement...
            </div>

            <div id="contractError" style="display:none; color:var(--danger); padding:10px; background:#fef2f2; border-left:4px solid var(--danger); margin-bottom:15px;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="contractErrorText"></span>
            </div>

            <div id="contractDetails" style="display:none; overflow-y: auto; flex: 1; margin-bottom: 10px; padding-right: 10px;">
                <table class="tech-table" style="width: 100%;">
                    <tbody id="contractTableBody">
                        <!-- Content populated dynamically via JS -->
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeContractModal()">Fermer</button>
            </div>
        </div>
    </div>
</body>
