<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin', 'accueil', 'dispatch', 'tac', 'directeur']);

$role = $_SESSION['role'];
$can_manage_contracts = in_array($role, ['commercial', 'admin', 'directeur']);
$can_view_amount = in_array($role, ['commercial', 'admin', 'directeur']);

if (!isset($_GET['id'])) {
    header("Location: contrats.php");
    exit;
}

$id = $_GET['id'];
$error = "";
$success = "";

// Récupérer le contrat avec TOUTES les colonnes du nouveau schéma
$sql = "SELECT ID_CONTRAT as id, CODE_CONTRAT as numero_contrat, Code_Client as code_client, Nom_Client as client_nom, Code_Site as code_site, Nom_Site as site_nom, 
        Date_Creation as date_creation, Date_Debut as date_debut, Date_Fin as date_fin, Date_Signature as date_signature, DATERESIL as date_resiliation,
        TYPE as categorie_contrat, Montant_Contrat as montant_annuel, ETAT as statut, ID_CLIENT as client_id, PERIODE as frequence_preventive, 
        Couverture_Heures as couverture_horaire, Couverture_Jours as couverture_jours, SERVICE as service_principal, 
        NBRFACTURE as nb_interventions_incluses, RESERVES as notes, TYPE as type_contrat,
        AVENANT as avenant, Contrat_Originale as contrat_originale, VP as vp, Mode_Facturation as mode_facturation,
        Periode_Facturation as periode_facturation, Echeance_Facturation as echeance_facturation, VPPLANIFIER as vpplanifier,
        Ville as ville, ETAT_REDOUANE as etat_redouane 
        FROM CONTRAT WHERE ID_CONTRAT = ?";

$stmtContrat = query($sql, [$id]);
$contrat = $stmtContrat ? sqlsrv_fetch_array($stmtContrat, SQLSRV_FETCH_ASSOC) : null;

if (!$contrat) die("Contrat introuvable.");

$servicePrincipalValue = trim((string)($contrat['service_principal'] ?? ''));
$servicePrincipalDisplay = $servicePrincipalValue !== '' ? $servicePrincipalValue : 'Non renseigne';

// Traitement Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$can_manage_contracts) {
        $error = "Modification non autorisée.";
    } else {
        $numero = $_POST['numero'];
        $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
        $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
        $montant = (isset($_POST['montant']) && trim((string)$_POST['montant']) !== '')
            ? str_replace(',', '.', trim((string)$_POST['montant']))
            : null;
        
        $categorie = $_POST['categorie_contrat'];
        $frequence = $_POST['frequence_preventive'];
        $couverture = $_POST['couverture_horaire'];
        $service = $_POST['service_principal'];
        $nb_interventions = (isset($_POST['nb_interventions_incluses']) && trim((string)$_POST['nb_interventions_incluses']) !== '')
            ? (int)$_POST['nb_interventions_incluses']
            : null;
        $statut = $_POST['statut'];
        $notes = $_POST['notes'];
        
        // Nouveaux champs récupérés du POST
        $date_signature = !empty($_POST['date_signature']) ? $_POST['date_signature'] : null;
        $dateresil = !empty($_POST['dateresil']) ? $_POST['dateresil'] : null;
        $avenant = $_POST['avenant'] ?? '';
        $contrat_originale = $_POST['contrat_originale'] ?? '';
        $vp = $_POST['vp'] ?? '';
        $code_site = $_POST['code_site'] ?? '';
        $nom_site = $_POST['nom_site'] ?? '';
        $mode_facturation = $_POST['mode_facturation'] ?? '';
        $periode_facturation = $_POST['periode_facturation'] ?? '';
        $echeance_facturation = $_POST['echeance_facturation'] ?? '';
        $vpplanifier = $_POST['vpplanifier'] ?? '';
        $couverture_jours = $_POST['couverture_jours'] ?? '';
        $ville = $_POST['ville'] ?? '';
        $etat_redouane = $_POST['etat_redouane'] ?? '';

        if (empty($numero)) {
            $error = "Le numéro est obligatoire.";
        } else {
            $sql = "UPDATE CONTRAT SET 
                        CODE_CONTRAT = ?,
                        Date_Debut = COALESCE(?, Date_Debut),
                        Date_Fin = COALESCE(?, Date_Fin),
                        Montant_Contrat = COALESCE(?, Montant_Contrat),
                        TYPE = COALESCE(NULLIF(?, ''), TYPE),
                        PERIODE = COALESCE(NULLIF(?, ''), PERIODE),
                        Couverture_Heures = COALESCE(NULLIF(?, ''), Couverture_Heures),
                        SERVICE = COALESCE(NULLIF(?, ''), SERVICE),
                        NBRFACTURE = COALESCE(?, NBRFACTURE),
                        ETAT = COALESCE(NULLIF(?, ''), ETAT),
                        RESERVES = COALESCE(?, RESERVES),
                        Date_Signature = COALESCE(?, Date_Signature),
                        DATERESIL = COALESCE(?, DATERESIL),
                        AVENANT = COALESCE(NULLIF(?, ''), AVENANT),
                        Contrat_Originale = COALESCE(NULLIF(?, ''), Contrat_Originale),
                        VP = COALESCE(NULLIF(?, ''), VP),
                        Code_Site = COALESCE(NULLIF(?, ''), Code_Site),
                        Nom_Site = COALESCE(NULLIF(?, ''), Nom_Site),
                        Mode_Facturation = COALESCE(NULLIF(?, ''), Mode_Facturation),
                        Periode_Facturation = COALESCE(NULLIF(?, ''), Periode_Facturation),
                        Echeance_Facturation = COALESCE(NULLIF(?, ''), Echeance_Facturation),
                        VPPLANIFIER = COALESCE(NULLIF(?, ''), VPPLANIFIER),
                        Couverture_Jours = COALESCE(NULLIF(?, ''), Couverture_Jours),
                        Ville = COALESCE(NULLIF(?, ''), Ville),
                        ETAT_REDOUANE = COALESCE(NULLIF(?, ''), ETAT_REDOUANE)
                    WHERE ID_CONTRAT = ?";
            
            $params = [
                $numero, $date_debut, $date_fin, $montant,
                $categorie, $frequence, $couverture,
                $service, $nb_interventions, $statut, $notes,
                $date_signature, $dateresil, $avenant, $contrat_originale,
                $vp, $code_site, $nom_site, $mode_facturation,
                $periode_facturation, $echeance_facturation, $vpplanifier,
                $couverture_jours, $ville, $etat_redouane,
                $id
            ];

            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $success = "Les modifications ont été sauvegardées avec succès.";
                // Recharger les données
                $stmtContrat = query("SELECT ID_CONTRAT as id, CODE_CONTRAT as numero_contrat, Code_Client as code_client, Nom_Client as client_nom, Code_Site as code_site, Nom_Site as site_nom, Date_Creation as date_creation, Date_Debut as date_debut, Date_Fin as date_fin, Date_Signature as date_signature, DATERESIL as date_resiliation, TYPE as categorie_contrat, Montant_Contrat as montant_annuel, ETAT as statut, ID_CLIENT as client_id, PERIODE as frequence_preventive, Couverture_Heures as couverture_horaire, Couverture_Jours as couverture_jours, SERVICE as service_principal, NBRFACTURE as nb_interventions_incluses, RESERVES as notes, TYPE as type_contrat, AVENANT as avenant, Contrat_Originale as contrat_originale, VP as vp, Mode_Facturation as mode_facturation, Periode_Facturation as periode_facturation, Echeance_Facturation as echeance_facturation, VPPLANIFIER as vpplanifier, Ville as ville, ETAT_REDOUANE as etat_redouane FROM CONTRAT WHERE ID_CONTRAT = ?", [$id]);
                $contrat = $stmtContrat ? sqlsrv_fetch_array($stmtContrat, SQLSRV_FETCH_ASSOC) : null;
            } else {
                error_log('[COMMERCIAL_CONTRAT_EDIT] ' . db_last_error_message());
                $error = "Erreur lors de la mise a jour du contrat.";
            }
        }
    }
}

$pageTitle = "Contrat " . htmlspecialchars($contrat['numero_contrat']) . " — SAV $role";

// Calcul simple pour la couleur du statut
$s = strtolower($contrat['statut']);
$sClass = 'badge-normal';
if($s == 'actif') $sClass = 'badge-resolu';
if($s == 'en_attente_signature') $sClass = 'badge-warning';
if($s == 'termine') $sClass = 'badge-info';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .disabled-input { background-color: var(--surface-2); color: var(--text-primary); cursor: not-allowed; opacity: 1 !important;}
        .disabled-input-wrapper i { opacity: .5; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-file-contract text-accent" style="margin-right:8px;"></i><?= $can_manage_contracts ? "Édition du" : "Consultation du"; ?> Contrat</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600; display:flex; align-items:center; gap:10px;">
                        <span class="badge badge-normal" style="font-family:monospace;"><i class="fa-solid fa-hashtag" style="opacity:.5; margin-right:4px;"></i><?= htmlspecialchars($contrat['numero_contrat']) ?></span> 
                        <span class="badge <?= $sClass ?>"><?= strtoupper(str_replace('_', ' ', htmlspecialchars($contrat['statut']))) ?></span>
                    </span>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="client_details.php?id=<?= urlencode($contrat['client_id']) ?>" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-user"></i> Dossier Client</a>
                <a href="contrats.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-list"></i> Liste</a>
            </div>
        </header>

        <div class="page-content" style="max-width:1000px; margin:0 auto;">

            <?php if($error): ?><div class="alert alert-error alert-auto-dismiss"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST" class="card" style="padding:0; overflow:hidden;">
                
                <div style="background:var(--surface-2); padding:24px 32px; border-bottom:1px solid rgba(58,1,92,.08);">
                    <h3 style="margin:0; font-size:1.2rem; color:var(--dark-amethyst-3);"><i class="fa-solid fa-circle-info text-primary" style="margin-right:8px;"></i>Généralités du Contrat</h3>
                </div>

                <div style="padding:32px;">
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Numéro de Contrat (Référence) <span class="text-danger">*</span></label>
                                <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                    <i class="fa-solid fa-barcode input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="numero" value="<?= htmlspecialchars($contrat['numero_contrat']) ?>" required class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="padding-left:36px; width:100%; font-family:monospace; font-weight:700;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Catégorie / Type</label>
                                <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                    <select name="categorie_contrat" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'disabled' : '' ?> style="appearance:none; padding-left:36px; width:100%;">
                                        <?php 
                                        $opts = ['MAINTENANCE', 'GARANTIE', 'SUPPORT', 'AUDIT'];
                                        foreach($opts as $opt): 
                                            $sel = ($contrat['categorie_contrat'] == $opt) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $opt ?>" <?= $sel ?>><?= ucfirst(strtolower($opt)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-tag input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <?php if($can_manage_contracts): ?><i class="fa-solid fa-chevron-down" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i><?php endif; ?>
                                </div>
                                <?php if(!$can_manage_contracts): ?><input type="hidden" name="categorie_contrat" value="<?= htmlspecialchars($contrat['categorie_contrat']) ?>"><?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Client Impliqué</label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-building input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--primary);"></i>
                                    <input type="text" value="<?= htmlspecialchars($contrat['client_nom'] ?? ($contrat['code_client'] ?? 'Non Renseigné')) ?>" class="form-control disabled-input" readonly style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Site Impliqué (Optionnel)</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-location-dot input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--primary);"></i>
                                    <input type="text" value="<?= htmlspecialchars($contrat['site_nom'] ?? ($contrat['code_site'] ?? 'Aucun Site')) ?>" class="form-control disabled-input" readonly style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date de Début <span class="text-danger">*</span></label>
                                <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                    <i class="fa-regular fa-calendar-check input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--success);"></i>
                                    <input type="date" name="date_debut" value="<?= $contrat['date_debut'] ? $contrat['date_debut']->format('Y-m-d') : '' ?>" required class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Date de Fin d'Échéance <span class="text-danger">*</span></label>
                                <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                    <i class="fa-regular fa-calendar-xmark input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--danger);"></i>
                                    <input type="date" name="date_fin" value="<?= $contrat['date_fin'] ? $contrat['date_fin']->format('Y-m-d') : '' ?>" required class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Statut Actuel</label>
                            <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                <select name="statut" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'disabled' : '' ?> style="appearance:none; padding-left:36px; width:100%;">
                                    <?php 
                                    $opts = ['ACTIF', 'EN_ATTENTE_SIGNATURE', 'RENOUVELLEMENT', 'TERMINE'];
                                    foreach($opts as $opt): 
                                        $sel = ($contrat['statut'] == $opt) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $opt ?>" <?= $sel ?>><?= str_replace('_', ' ', ucfirst(strtolower($opt))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fa-solid fa-signal input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                <?php if($can_manage_contracts): ?><i class="fa-solid fa-chevron-down" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i><?php endif; ?>
                            </div>
                            <?php if(!$can_manage_contracts): ?><input type="hidden" name="statut" value="<?= htmlspecialchars($contrat['statut']) ?>"><?php endif; ?>
                        </div>
                    </div>

                    <div style="border-top:1px dashed rgba(58,1,92,.1); margin:32px 0;"></div>

                    <h4 style="color:var(--dark-amethyst-3); margin-top:0; margin-bottom:20px;"><i class="fa-solid fa-handshake text-accent" style="margin-right:8px;"></i>Conditions Commerciales et Périodicités</h4>
                    
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Montant Annuel (MAD)</label>
                                <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                    <i class="fa-solid fa-coins input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--warning);"></i>
                                    <?php if($can_view_amount): ?>
                                    <input type="number" name="montant" step="0.01" value="<?= htmlspecialchars($contrat['montant_annuel']) ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="padding-left:36px; width:100%;">
                                    <?php else: ?>
                                    <input type="text" value="Masqué (Droits Insuffisants)" class="form-control disabled-input" readonly style="padding-left:36px; width:100%;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Nombre d'Interventions Incluses</label>
                                <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                    <i class="fa-solid fa-toolbox input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--info);"></i>
                                    <input type="number" name="nb_interventions_incluses" value="<?= htmlspecialchars($contrat['nb_interventions_incluses'] ?? 0) ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date de Signature</label>
                                <div style="position:relative;" class="<?= !$can_manage_contracts ? 'disabled-input-wrapper' : '' ?>">
                                    <i class="fa-regular fa-pen-to-square input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="date" name="date_signature" value="<?= $contrat['date_signature'] ? $contrat['date_signature']->format('Y-m-d') : '' ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Mode de Facturation</label>
                                <input type="text" name="mode_facturation" value="<?= htmlspecialchars($contrat['mode_facturation'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Période de Facturation</label>
                                <input type="text" name="periode_facturation" value="<?= htmlspecialchars($contrat['periode_facturation'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Échéance de Facturation</label>
                                <input type="text" name="echeance_facturation" value="<?= htmlspecialchars($contrat['echeance_facturation'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Fréquence des Maintenances Préventives</label>
                                <input type="text" name="frequence_preventive" value="<?= htmlspecialchars($contrat['frequence_preventive'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Ville de Compétence</label>
                                <input type="text" name="ville" value="<?= htmlspecialchars($contrat['ville'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Couverture Horaire (SLA)</label>
                                <input type="text" name="couverture_horaire" value="<?= htmlspecialchars($contrat['couverture_horaire'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Couverture Jours</label>
                                <input type="text" name="couverture_jours" value="<?= htmlspecialchars($contrat['couverture_jours'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group" style="grid-column: span 2;">
                                <label>Service Principal Fourni (SERVICE)</label>
                                <input type="text" name="service_principal" value="<?= htmlspecialchars($can_manage_contracts ? $servicePrincipalValue : $servicePrincipalDisplay) ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Avenant</label>
                                <input type="text" name="avenant" value="<?= htmlspecialchars($contrat['avenant'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Contrat Original</label>
                                <input type="text" name="contrat_originale" value="<?= htmlspecialchars($contrat['contrat_originale'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Code Site Assigné</label>
                                <input type="text" name="code_site" value="<?= htmlspecialchars($contrat['code_site'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Nom du Site Assigné</label>
                                <input type="text" name="nom_site" value="<?= htmlspecialchars($contrat['site_nom'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>VP</label>
                                <input type="text" name="vp" value="<?= htmlspecialchars($contrat['vp'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>VP Planifié</label>
                                <input type="text" name="vpplanifier" value="<?= htmlspecialchars($contrat['vpplanifier'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date de Résiliation</label>
                                <input type="date" name="dateresil" value="<?= $contrat['date_resiliation'] ? $contrat['date_resiliation']->format('Y-m-d') : '' ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>État Redouane</label>
                                <input type="text" name="etat_redouane" value="<?= htmlspecialchars($contrat['etat_redouane'] ?? '') ?>" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%;">
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px dashed rgba(58,1,92,.1); margin:32px 0;"></div>

                    <h4 style="color:var(--dark-amethyst-3); margin-top:0; margin-bottom:20px;"><i class="fa-solid fa-boxes-stacked text-primary" style="margin-right:8px;"></i>Périmètre Technique et Annexes</h4>
                    
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group" style="grid-column: span 2;">
                                <label><i class="fa-regular fa-comment-dots"></i> Notes complémentaires / Réserves (RESERVES)</label>
                                <textarea name="notes" class="form-control <?= !$can_manage_contracts ? 'disabled-input' : '' ?>" <?= !$can_manage_contracts ? 'readonly' : '' ?> style="width:100%; min-height:100px; resize:vertical; padding:12px; font-family:inherit;"><?= htmlspecialchars($contrat['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                </div>
                
                <?php if($can_manage_contracts): ?>
                <div style="background:var(--surface-2); padding:24px 32px; border-top:1px solid rgba(58,1,92,.08); display:flex; justify-content:flex-end; gap:16px;">
                    <a href="contrats.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn" style="padding:14px 32px; font-size:1.05rem;"><i class="fa-solid fa-floppy-disk" style="margin-right:8px;"></i> Sauvegarder les modifications</button>
                </div>
                <?php else: ?>
                <div style="background:var(--surface-2); padding:24px 32px; border-top:1px solid rgba(58,1,92,.08); text-align:right;">
                    <span style="color:var(--text-muted); font-size:.9rem;"><i class="fa-solid fa-lock" style="margin-right:4px;"></i> Vous ne disposez pas des droits d'édition sur ce contrat.</span>
                </div>
                <?php endif; ?>
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
