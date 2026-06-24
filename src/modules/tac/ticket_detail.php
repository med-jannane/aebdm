<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tac', 'admin']);

if (!isset($_GET['ticket_id'])) {
    header('Location: tickets_list.php');
    exit;
}

$ticketId = $_GET['ticket_id'];
$error = '';
$success = '';

if (isset($_GET['take'])) {
    query("UPDATE TICKET SET ETAT = 'en_cours_tac' WHERE ID_TICKET = ? AND ETAT = 'ouvert'", [$ticketId]);
    header('Location: ticket_detail.php?ticket_id=' . $ticketId);
    exit;
}

$ticket = sqlsrv_fetch_array(
    query(
        "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, TICKET.OBJET as sujet, TICKET.COMMENT as description, TICKET.NOM_USER as contact_source, TICKET.ID_CLIENT as client_id, TICKET.ID_SITE as site_id,
                SAV_Clients.Nom as client_nom, SAV_Clients.Email as client_email,
                SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville, SAV_Sites.Region as region,
                TICKET.tac_diagnostic, TICKET.tac_solution, TICKET.tac_date_traitement, 'Demande SAV' as type_probleme
         FROM TICKET
         JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
         LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
         WHERE TICKET.ID_TICKET = ?",
        [$ticketId]
    ),
    SQLSRV_FETCH_ASSOC
);

if (!$ticket) {
    header('Location: tickets_list.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnostic = trim($_POST['tac_diagnostic'] ?? '');
    $solution = trim($_POST['tac_solution'] ?? '');
    $action = $_POST['action'] ?? 'save';

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
        if (query($sqlSite, [$modem, $login, $pass, $poste, $abo, $cable, $incl, $ticket['site_id']])) {
            $success = "Informations techniques du site mises à jour.";
        } else {
            $error = "Erreur mise à jour site.";
        }
    } elseif (in_array($action, ['transfer', 'close'], true) && $diagnostic === '') {
        $error = "Le diagnostic est obligatoire pour continuer.";
    } else {
        if ($action === 'transfer') {
            $statut = 'attente_dispatch';
        } elseif ($action === 'close') {
            $statut = 'cloture';
        } else {
            $statut = 'en_cours_tac';
        }

        $sql = "UPDATE TICKET
                SET tac_diagnostic = ?, tac_solution = ?, tac_date_traitement = GETDATE(), ETAT = ?
                WHERE ID_TICKET = ?";
        $params = [$diagnostic, $solution, $statut, $ticketId];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            if ($action === 'transfer') {
                require_once __DIR__ . '/../../utils/NotificationManager.php';
                $nm = new NotificationManager($conn);
                $nm->create("Ticket #$ticketId - Ticket transmis par le TAC, à planifier.", 'dispatch', null, "/sav/src/modules/dispatch/assign_tech.php?ticket_id=$ticketId");
            }
            $success = "Ticket mis a jour.";
        } else {
            error_log('[TAC_TICKET_DETAIL_UPDATE] ' . db_last_error_message());
            $error = "Erreur lors de la mise a jour du ticket.";
        }
    }
    
    // RE-FETCH TICKET TO SHOW UPDATED DATA
    $ticket = sqlsrv_fetch_array(
        query(
            "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, TICKET.OBJET as sujet, TICKET.COMMENT as description, TICKET.NOM_USER as contact_source, TICKET.ID_CLIENT as client_id, TICKET.ID_SITE as site_id,
                    SAV_Clients.Nom as client_nom, SAV_Clients.Email as client_email,
                    SAV_Sites.Nom as site_nom, SAV_Sites.Ville as ville, SAV_Sites.Region as region,
                    TICKET.tac_diagnostic, TICKET.tac_solution, TICKET.tac_date_traitement, 'Demande SAV' as type_probleme
             FROM TICKET
             JOIN SAV_Clients ON SAV_Clients.ID_Client = TICKET.ID_CLIENT
             LEFT JOIN SAV_Sites ON SAV_Sites.Id_Site = TICKET.ID_SITE
             WHERE TICKET.ID_TICKET = ?",
            [$ticketId]
        ),
        SQLSRV_FETCH_ASSOC
    );
}

// Fetch Site History (Tickets & Interventions)
// Tickets (Triés par date décroissante, exclu le ticket actuel)
$sqlTickets = "SELECT ID_TICKET as id, DATE as cree_le, ETAT as statut FROM TICKET WHERE ID_SITE = ? AND ID_TICKET != ? ORDER BY DATE DESC";
$historyTickets = [];
$stmt = query($sqlTickets, [$ticket['site_id'], $ticketId]);
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

if (!$ticket) {
    header('Location: tickets_list.php');
    exit;
}

$statusLabels = [
    'ouvert' => 'Ouvert',
    'en_cours_tac' => 'En TAC',
    'attente_dispatch' => 'En Dispatch',
    'assigne' => 'Assigne',
    'cloture' => 'Cloture'
];

$statusClassMap = [
    'ouvert' => 'pill-open',
    'en_cours_tac' => 'pill-progress',
    'attente_dispatch' => 'pill-dispatch',
    'assigne' => 'pill-dispatch',
    'cloture' => 'pill-closed'
];

$statut = $ticket['statut'];
$label = $statusLabels[$statut] ?? ucfirst($statut);
$pillClass = $statusClassMap[$statut] ?? 'pill-open';

$pageTitle = "Ticket #" . $ticketId;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .split-card {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .split-card { grid-template-columns: 1fr; }
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-weight: 500; }
        .info-value { font-weight: 600; text-align: right; }
        
        .tech-details {
            background-color: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-top: 20px;
        }
        .tech-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .tech-table td {
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
        }
        .tech-table td:first-child {
            color: var(--text-muted);
            font-weight: 500;
            width: 40%;
        }
        .tech-table td:last-child {
            font-weight: 600;
            text-align: right;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Ticket #<?php echo $ticket['id']; ?></h1>
            </div>
            <div style="display:flex; gap:10px;">
                <?php if ($ticket['statut'] === 'ouvert'): ?>
                    <a href="ticket_detail.php?ticket_id=<?php echo $ticketId; ?>&take=1" class="btn"><i class="fa-solid fa-hand-holding-medical"></i> Prendre en charge</a>
                <?php endif; ?>
                <a href="tickets_list.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="card" style="color:var(--danger); border-left:4px solid var(--danger);">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="card" style="color:var(--success); border-left:4px solid var(--success);">
                <i class="fa-solid fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="split-card">
            <div style="display:flex; flex-direction:column; gap:20px;">
                <div class="card">
                <h3 style="color:var(--primary); margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:5px;">Détails du ticket</h3>
                
                <div class="info-row">
                    <span class="info-label">Client</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['client_nom']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Site</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['site_nom']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Statut</span>
                    <span class="badge badge-<?php echo strtolower($ticket['statut']); ?>"><?php echo $label; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact</span>
                    <span class="info-value"><?php echo htmlspecialchars(ucfirst($ticket['contact_source'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Priorité</span>
                    <span class="badge badge-<?php echo strtolower($ticket['priorite']); ?>"><?php echo htmlspecialchars(ucfirst($ticket['priorite'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Type problème</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['type_probleme'] ?? 'Non spécifié'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Créé le</span>
                    <span class="info-value"><?php echo $ticket['cree_le'] ? $ticket['cree_le']->format('d/m/Y H:i') : ''; ?></span>
                </div>
                
                <div style="margin-top:20px; background:var(--background); padding:15px; border-radius:var(--radius-sm);">
                    <strong style="display:block; margin-bottom:5px;"><i class="fa-solid fa-quote-left"></i> Description</strong>
                    <p style="color:var(--text-muted); font-size:0.95em; line-height:1.5;"><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                </div>

                <!-- Technical Details (TAC Added) -->
                <?php
                    // Fetch extended site details (Technical info)
                    $siteDetails = sqlsrv_fetch_array(query("SELECT * FROM Sites WHERE id = ?", [$ticket['site_id']]), SQLSRV_FETCH_ASSOC);
                ?>
                <div class="tech-details">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h4 style="margin-top:0; color:var(--primary); margin-bottom:10px;"><i class="fa-solid fa-server"></i> Informations Techniques</h4>
                        <button type="button" onclick="document.getElementById('editSiteModal').style.display='block'" class="btn btn-sm btn-secondary"><i class="fa-solid fa-pen-to-square"></i> Modifier</button>
                    </div>
                    <table class="tech-table">
                        <tr><td>Code Client</td><td><?php echo htmlspecialchars($ticket['client_id']); ?></td></tr>
                        <tr><td>Nom Client</td><td><?php echo htmlspecialchars($ticket['client_nom']); ?></td></tr>
                        <tr><td>Modem</td><td><?php echo htmlspecialchars($siteDetails['modem'] ?? 'N/A'); ?></td></tr>
                        <tr><td>Login Routeur</td><td><?php echo htmlspecialchars($siteDetails['routeur_login'] ?? 'N/A'); ?></td></tr>
                        <tr><td>Mot de Passe</td><td><?php echo htmlspecialchars($siteDetails['routeur_password'] ?? 'N/A'); ?></td></tr>
                        <tr><td>Poste Inclut</td><td><?php echo htmlspecialchars($siteDetails['poste_inclut'] ?? 'N/A'); ?></td></tr>
                        <tr><td>Type Abo.</td><td><?php echo htmlspecialchars($siteDetails['type_abonnement'] ?? 'N/A'); ?></td></tr>
                        <tr><td>Câblage</td><td><?php echo htmlspecialchars($siteDetails['cablage'] ?? 'N/A'); ?></td></tr>
                        <tr><td>Inclusions</td><td><?php echo htmlspecialchars($siteDetails['inclusions'] ?? 'N/A'); ?></td></tr>
                    </table>
                </div>
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

            <!-- HISTORY SIDEBAR -->
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
            </div> <!-- End Flex Column -->

            <!-- Right Column: Diagnostic -->
            <div class="card">
                <h3 style="color:var(--primary); margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:5px;">Diagnostic & Action TAC</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="tac_diagnostic"><i class="fa-solid fa-stethoscope"></i> Diagnostic *</label>
                        <textarea name="tac_diagnostic" id="tac_diagnostic" rows="5" required class="form-control" placeholder="Détaillez votre analyse ici..."><?php echo htmlspecialchars($ticket['tac_diagnostic'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="tac_solution"><i class="fa-solid fa-lightbulb"></i> Solution proposée</label>
                        <textarea name="tac_solution" id="tac_solution" rows="4" class="form-control" placeholder="Pistes de résolution ou solution appliquée..."><?php echo htmlspecialchars($ticket['tac_solution'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-gavel"></i> Décision</label>
                        <div style="display: flex; flex-direction: column; gap: 10px; background:var(--background); padding:15px; border-radius:var(--radius-sm); border:1px solid var(--border);">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="radio" name="action" value="save" checked> 
                                <span><i class="fa-solid fa-floppy-disk"></i> Enregistrer en cours (Brouillon)</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="radio" name="action" value="transfer"> 
                                <span><i class="fa-solid fa-paper-plane"></i> Transférer au dispatch (Nécessite Intervention)</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                <input type="radio" name="action" value="close"> 
                                <span><i class="fa-solid fa-check-double"></i> Clôturer le ticket (Résolu à distance)</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-full"><i class="fa-solid fa-check"></i> Valider la décision</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
