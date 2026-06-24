<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin']);

$role = $_SESSION['role'];
$error = "";
$success = "";
$next_contract_code = previewNextSequentialCode('contract_code', 'CONTRAT', 'CODE_CONTRAT', 202200000485, 12);

// Récupérer les clients
$clients = query("SELECT ID_Client as id, Nom as nom, ID_Client as code_client FROM SAV_Clients ORDER BY Nom ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $client_id = $_POST['client_id'];
    $numero = getNextSequentialCode('contract_code', 'CONTRAT', 'CODE_CONTRAT', 202200000485, 12);
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $montant = !empty($_POST['montant']) ? $_POST['montant'] : 0;
        // Les 26 champs de CONTRAT

    // Champs Client & Numéro
    $categorie = $_POST['categorie_contrat'] ?? '';
    // Periode (Fréquence preventive)
    $periode = $_POST['periode'] ?? '';

    // Informations complémentaires
    $date_creation = date('Y-m-d H:i:s');
    $date_signature = !empty($_POST['date_signature']) ? $_POST['date_signature'] : null;
    $avenant = $_POST['avenant'] ?? '';
    $contrat_originale = $_POST['contrat_originale'] ?? '';
    $type = $_POST['type'] ?? '';
    $vp = $_POST['vp'] ?? '';
    $code_site = $_POST['code_site'] ?? '';
    $nom_site = $_POST['nom_site'] ?? '';
    $mode_facturation = $_POST['mode_facturation'] ?? '';
    $periode_facturation = $_POST['periode_facturation'] ?? '';
    $echeance_facturation = $_POST['echeance_facturation'] ?? '';
    $vpplanifier = $_POST['vpplanifier'] ?? '';
    $service = $_POST['service'] ?? '';
    $couverture_heures = $_POST['couverture_heures'] ?? '';
    $couverture_jours = $_POST['couverture_jours'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $etat_redouane = $_POST['etat_redouane'] ?? '';
    $dateresil = !empty($_POST['dateresil']) ? $_POST['dateresil'] : null;

    $statut = $_POST['statut'] ?? 'ACTIF';
    $notes = $_POST['notes'];

    if (empty($client_id) || empty($date_debut) || empty($date_fin)) {
        $error = "Tous les champs obligatoires (*) doivent être remplis.";
    } else {
        // Obtenir le nom et code du client
        $client_info = sqlsrv_fetch_array(query("SELECT Nom, ID_Client FROM SAV_Clients WHERE ID_Client = ?", [$client_id]), SQLSRV_FETCH_ASSOC);
        $nom_client = $client_info ? $client_info['Nom'] : '';
        $code_client_table = $client_info ? $client_info['ID_Client'] : '';

        // Vérifier unicité numéro
        $check = sqlsrv_fetch_array(query("SELECT ID_CONTRAT as id FROM CONTRAT WHERE CODE_CONTRAT = ?", [$numero]));
        if ($check) {
            $error = "Ce numéro de contrat existe déjà.";
        } else {
            $new_id_contrat = uniqid('CTR-');
            $sql = "INSERT INTO CONTRAT (
                        ID_CONTRAT, ID_CLIENT, CODE_CONTRAT, Code_Client, Nom_Client, Date_Creation, Date_Debut, PERIODE, Date_Fin,
                        Date_Signature, AVENANT, Contrat_Originale, ETAT, TYPE, VP,
                        Code_Site, Nom_Site, Montant_Contrat, Mode_Facturation, Periode_Facturation,
                        Echeance_Facturation, VPPLANIFIER, SERVICE, Couverture_Heures, Couverture_Jours,
                        Ville, ETAT_REDOUANE, DATERESIL, RESERVES
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $new_id_contrat, $client_id, $numero, $code_client_table, $nom_client, $date_creation, $date_debut, $periode, $date_fin,
                $date_signature, $avenant, $contrat_originale, $statut, $type, $vp,
                $code_site, $nom_site, $montant, $mode_facturation, $periode_facturation,
                $echeance_facturation, $vpplanifier, $service, $couverture_heures, $couverture_jours,
                $ville, $etat_redouane, $dateresil, $notes
            ];

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt) {
                header("Location: contrats.php");
                exit;
            } else {
                    error_log('[COMMERCIAL_CONTRAT_CREATE] ' . db_last_error_message());
                    $error = "Erreur lors de la création du contrat.";
                $next_contract_code = previewNextSequentialCode('contract_code', 'CONTRAT', 'CODE_CONTRAT', 202200000485, 12);
            }
        }
    }
}

$pageTitle = "Créer un Contrat — SAV " . ucfirst($role);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex;align-items:center;gap:16px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-file-signature text-accent" style="margin-right:8px;"></i>Création d'un Contrat</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Enregistrement d'un nouvel accord commercial</span>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <?php if(isset($_GET['client_id'])): ?>
                <a href="client_details.php?id=<?= $_GET['client_id'] ?>" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour au client</a>
                <?php else: ?>
                <a href="contrats.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="page-content" style="max-width:1000px; margin:0 auto;">

            <?php if($error): ?><div class="alert alert-error alert-auto-dismiss"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST" class="card" style="padding:0; overflow:hidden;">

                <div style="background:var(--surface-2); padding:24px 32px; border-bottom:1px solid rgba(58,1,92,.08);">
                    <h3 style="margin:0; font-size:1.2rem; color:var(--dark-amethyst-3);"><i class="fa-solid fa-handshake text-primary" style="margin-right:8px;"></i>Généralités du Contrat</h3>
                </div>

                <div style="padding:32px;">
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Client Associé <span class="text-danger">*</span></label>
                                <div style="position:relative;">
                                    <select name="client_id" required class="form-control" style="appearance:none; padding-left:36px; width:100%;">
                                        <option value="">-- Sélectionner un client --</option>
                                        <?php while($cl = sqlsrv_fetch_array($clients, SQLSRV_FETCH_ASSOC)):
                                        $selected = (isset($_GET['client_id']) && $_GET['client_id'] == $cl['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $cl['id'] ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($cl['nom']) ?> (<?= htmlspecialchars($cl['code_client'] ?? 'N/A') ?>)
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <i class="fa-regular fa-building input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <i class="fa-solid fa-chevron-down" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Numéro de Contrat (Automatique) <span class="text-danger">*</span></label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-barcode input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="numero" value="<?= htmlspecialchars($next_contract_code) ?>" readonly class="form-control" style="padding-left:36px; width:100%; font-family:monospace; font-weight:700; background:var(--surface-2);">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Catégorie / Type</label>
                                <div style="position:relative;">
                                    <select name="categorie_contrat" class="form-control" style="appearance:none; padding-left:36px; width:100%;">
                                        <option value="MAINTENANCE">Maintenance</option>
                                        <option value="GARANTIE">Garantie</option>
                                        <option value="SUPPORT">Support</option>
                                        <option value="AUDIT">Audit</option>
                                    </select>
                                    <i class="fa-solid fa-tag input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <i class="fa-solid fa-chevron-down" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date de Début <span class="text-danger">*</span></label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-calendar-check input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--success);"></i>
                                    <input type="date" name="date_debut" required class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Date de Fin d'Échéance <span class="text-danger">*</span></label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-calendar-xmark input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--danger);"></i>
                                    <input type="date" name="date_fin" required class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Statut Actuel</label>
                                <div style="position:relative;">
                                    <select name="statut" class="form-control" style="appearance:none; padding-left:36px; width:100%;">
                                        <option value="ACTIF">Actif</option>
                                        <option value="EN_ATTENTE_SIGNATURE">En attente de signature</option>
                                        <option value="RENOUVELLEMENT">En cours de renouvellement</option>
                                        <option value="TERMINE">Terminé / Expiré</option>
                                    </select>
                                    <i class="fa-solid fa-signal input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <i class="fa-solid fa-chevron-down" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Montant Annuel (MAD)</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-coins input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--warning);"></i>
                                    <input type="number" name="montant" step="0.01" placeholder="0.00" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px dashed rgba(58,1,92,.1); margin:32px 0;"></div>

                    <h4 style="color:var(--dark-amethyst-3); margin-top:0; margin-bottom:20px;"><i class="fa-solid fa-gears text-accent" style="margin-right:8px;"></i>Détails Techniques du Service</h4>

                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Service Principal Fourni</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-server input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="service" placeholder="Ex: Support niveau 2 Fortinet" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Couverture Horaire (SLA)</label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-clock input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="couverture_horaire" placeholder="Ex: 8x5 Jour ouvrés" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date de Signature</label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-pen-to-square input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="date" name="date_signature" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Mode de Facturation</label>
                                <input type="text" name="mode_facturation" placeholder="Ex: Annuelle, Trimestrielle" class="form-control" style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Période de Facturation</label>
                                <input type="text" name="periode_facturation" placeholder="Ex: Janvier-Décembre" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Échéance de Facturation</label>
                                <input type="text" name="echeance_facturation" placeholder="Ex: 30 Jours Fin de Mois" class="form-control" style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Fréquence des Maintenances Préventives (PERIODE)</label>
                                <input type="text" name="periode" placeholder="Ex: 1 Trimestre" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Ville de Compétence</label>
                                <input type="text" name="ville" placeholder="Ex: Casablanca" class="form-control" style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Couverture Jours</label>
                                <input type="text" name="couverture_jours" placeholder="Ex: 5j/7, Lundi-Vendredi" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>VP / VP Planifié</label>
                                <input type="text" name="vp" placeholder="VP" class="form-control" style="width:48%; display:inline-block;">
                                <input type="text" name="vpplanifier" placeholder="VP Planifié" class="form-control" style="width:48%; display:inline-block; float:right;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Avenant</label>
                                <input type="text" name="avenant" placeholder="Référence de l'avenant" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Contrat Original</label>
                                <input type="text" name="contrat_originale" placeholder="Référence contrat parent" class="form-control" style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Code Site Assigné</label>
                                <input type="text" name="code_site" placeholder="Code (si spécifique au site)" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Nom du Site Assigné</label>
                                <input type="text" name="nom_site" placeholder="Nom du site" class="form-control" style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date Résiliation / Fin anticipée</label>
                                <input type="date" name="dateresil" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>État Redouane</label>
                                <input type="text" name="etat_redouane" placeholder="État spécifique" class="form-control" style="width:100%;">
                            </div>

                        <!-- Ces champs ont été retirés volontairement car non présents dans la table CONTRAT clean: postes_inclus, partenaires_produits, types_produits -->

                    <div style="border-top:1px dashed rgba(58,1,92,.1); margin:32px 0;"></div>

                    <h4 style="color:var(--dark-amethyst-3); margin-top:0; margin-bottom:20px;"><i class="fa-solid fa-boxes-stacked text-primary" style="margin-right:8px;"></i>Produits & Équipements</h4>

                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Marques / Partenaires Concernés</label>
                                <input type="text" name="partenaires_produits" placeholder="Ex: Cisco, Fortinet" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Catégories de Produits Protégés</label>
                                <input type="text" name="types_produits" placeholder="Ex: Switch, Pare-feu" class="form-control" style="width:100%;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:20px;">
                            <label><i class="fa-regular fa-comment-dots"></i> Remarques concernant le contrat</label>
                            <textarea name="notes" placeholder="Clauses spécifiques, exclusions..." class="form-control" style="width:100%; min-height:100px; resize:vertical; padding:12px; font-family:inherit;"></textarea>
                        </div>
                    </div>

                </div>

                <div style="background:var(--surface-2); padding:24px 32px; border-top:1px solid rgba(58,1,92,.08); text-align:right;">
                    <button type="submit" class="btn" style="padding:14px 32px; font-size:1.05rem;"><i class="fa-solid fa-plus-circle" style="margin-right:8px;"></i> Finaliser et Créer le Contrat</button>
                </div>
            </form>

            <div style="height:40px;"></div>
        </div>
    </div>

    <script>
        document.getElementById('menuBtn') && document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay') && document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });
    </script>
</body>
</html>
