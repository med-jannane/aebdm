<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';
require_once __DIR__ . '/../../utils/CsvImporter.php';

// Debug option (use ?debug=1) to see fatal errors on IIS
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

check_role(['admin', 'commercial', 'accueil', 'directeur', 'charge_de_compte']);

$type = $_GET['type'] ?? 'clients';
$allowedTypes = ['clients', 'sites', 'contrats', 'tickets', 'commandes'];
if (!in_array($type, $allowedTypes)) $type = 'clients';

// Handle Template Download
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $filename = "modele_" . $type . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $out = fopen('php://output', 'w');
    // Add BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");
    
    switch($type) {
        case 'clients': 
            fputcsv($out, [
                'id_client', 'nom', 'adresse', 'ville', 'contact', 'tel', 'tel2', 'tel3', 
                'fax', 'email', 'site', 'blocage', 'activite', 'secteur_activite_sec', 
                'code_secteur_activite_princ', 'code_secteur_activite_sec', 'modalite_paiement', 'sysgm_client_bit'
            ], ';');
            fputcsv($out, [
                'CLI-001', 'Société Exemple', '123 Rue Principale', 'Casablanca', 'M. Dupont', 
                '0600000000', '', '', '', 'contact@exemple.com', 'www.exemple.com', 
                '', 'Service', '', 'INFO', '', 'Virement', '1'
            ], ';');
            break;
        case 'contrats': 
            fputcsv($out, [
                'CODE_CONTRAT', 'Code_Client', 'Nom_Client', 'Date_Creation', 'Date_Debut', 'PERIODE', 'Date_Fin', 'Date_Signature', 
                'AVENANT', 'Contrat_Originale', 'ETAT', 'TYPE', 'VP', 'Code_Site', 'Nom_Site', 'Montant_Contrat', 'Mode_Facturation', 
                'Periode_Facturation', 'Echeance_Facturation', 'VPPLANIFIER', 'SERVICE', 'Couverture_Heures', 'Couverture_Jours', 
                'Ville', 'ETAT_REDOUANE', 'DATERESIL'
            ], ';');
            fputcsv($out, [
                'CTR-2024-001', 'CLI-001', 'Société Exemple', '2024-01-01', '2024-01-01', '12 Mois', '2024-12-31', '2024-01-05', 
                '', '', 'ACTIF', 'Maintenance', 'OUI', 'SIT-001', 'Siège Social', '15000.00', 'Annuel', 
                '12 Mois', 'Fin de mois', 'OUI', 'Support IT', '8h-18h', 'Lundi-Vendredi', 
                'Casablanca', '', ''
            ], ';');
            break;
        case 'sites':
            fputcsv($out, [
                'id_site', 'id_client', 'ville', 'nom_client', 'nom', 'adresse', 'tel', 'fax', 'siteweb', 'email',
                'comment', 'modem', 'blocage', 'datebl', 'datedbl', 'tel2', 'tel3', 'remote_login1', 'mdp1',
                'remote_login2', 'mdp2', 'zone_geo', 'tel_siege', 'code_agence', 'latitude', 'longitude', 'contact_nom'
            ], ';');
            fputcsv($out, [
                'SIT-001', 'CLI-001', 'Casablanca', 'Societe Exemple', 'Siege Principal', '123 Rue Principale', '0600000000', '', 'www.exemple.com', 'site@exemple.com',
                'Site principal client', '', '0', '', '', '', '', '', '', '', '', 'Centre', '', 'AG-01', '', '', 'M. Dupont'
            ], ';');
            break;
        case 'commandes': 
            fputcsv($out, ['numero_commande', 'code_client', 'montant_ht', 'date_commande'], ';');
            fputcsv($out, ['CMD-2024-001', 'CLI-001', '1500.00', '2024-02-15'], ';');
            break;
        case 'tickets': 
            fputcsv($out, ['sujet', 'description', 'code_client', 'priorite'], ';'); // Simplified
            fputcsv($out, ['Panne Internet', 'Plus de connexion depuis ce matin', 'CLI-001', 'haute'], ';');
            break;
    }
    fclose($out);
    exit;
}

$pageTitle = "Importer des " . ucfirst($type);
$msg = "";
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        
        // Augmenter les limites pour les gros imports (sites 4000+ lignes)
        @ini_set('memory_limit', '1024M');
        set_time_limit(0);
        
        $importer = new CsvImporter($conn);
        $report = $importer->import($tmpName, $type);
        
        if ($report['status'] === 'success') {
            $msg = "Import terminé : " . $report['imported'] . " succès, " . $report['failed'] . " échecs.";
        } else {
             $msg = "Erreur : " . $report['message'];
        }
    } else {
        $msg = "Erreur lors du téléchargement du fichier.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        /* Sidebar Fix for Import Page */
        .sidebar ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
        .sidebar ul li a {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: rgba(255,255,255,.7);
            text-decoration: none; border-radius: 12px; font-weight: 600; font-size: .95rem;
            transition: all .3s cubic-bezier(.4,0,.2,1);
        }
        .sidebar ul li a i { width: 22px; text-align: center; font-size: 1.1rem; opacity: .8; transition: transform .3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background: rgba(255,255,255,.1); color: #fff; transform: translateX(6px);
        }
        .sidebar ul li a:hover i, .sidebar ul li a.active i { color: var(--accent-light); opacity: 1; transform: scale(1.1); }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Importation : <?php echo ucfirst($type); ?></h1>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="import_csv.php?type=<?php echo $type; ?>&action=download_template" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Télécharger Modèle</a>
                <a href="audit_imports.php" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass-chart"></i> Audit Imports</a>
                <a href="delete_all_sites.php" class="btn btn-secondary"><i class="fa-solid fa-trash"></i> Vider Sites</a>
                <a href="delete_all_clients.php" class="btn btn-secondary"><i class="fa-solid fa-users-slash"></i> Vider Clients</a>
                <a href="javascript:history.back()" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="card" style="border-left: 5px solid <?php echo ($report && $report['failed'] == 0) ? 'var(--success)' : 'var(--warning)'; ?>;">
                <h3>Résultat de l'import</h3>
                <p><?php echo $msg; ?></p>
                <?php if ($report && !empty($report['errors'])): ?>
                    <div style="background:#fff5f5; padding:10px; border:1px solid #fed7d7; margin-top:10px; max-height:200px; overflow-y:auto;">
                        <strong style="color:var(--danger);">Détails des erreurs :</strong>
                        <ul style="margin:5px 0 0 20px; color:var(--danger);">
                            <?php foreach($report['errors'] as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <p>Formats acceptés : <strong>.CSV</strong> (point-virgule) ou <strong>.XLSX</strong> (Excel).</p>
            
            <div style="background:var(--background); padding:15px; border-radius:var(--radius-sm); margin-bottom:20px; font-family:monospace;">
                <strong>Colonnes attendues :</strong><br>
                <?php 
                switch($type) {
                    case 'clients': echo "id_client; nom; adresse; ville; contact; tel; tel2; tel3; fax; email; site; blocage; activite; secteur_activite_sec; code_secteur_activite_princ; code_secteur_activite_sec; modalite_paiement; sysgm_client_bit"; break;
                    case 'sites': echo "id_site; id_client; ville; nom_client; nom; adresse; tel; fax; siteweb; email; comment; modem; blocage; datebl; datedbl; tel2; tel3; remote_login1; mdp1; remote_login2; mdp2; zone_geo; tel_siege; code_agence; latitude; longitude; contact_nom"; break;
                    case 'contrats': echo "code_contrat; code_client; nom_client; date_creation; date_debut; periode; date_fin; date_signature; avenant; contrat_originale; etat; type; vp; code_site; nom_site; montant_contrat; mode_facturation; periode_facturation; echeance_facturation; vpplanifier; service; couverture_heures; couverture_jours; ville; etat_redouane; dateresil"; break;
                    case 'commandes': echo "numero_commande; code_client; montant_ht; date_commande"; break;
                    case 'tickets': echo "sujet; description; code_client; priorite"; break;
                    default: echo "Format non défini.";
                }
                ?>
            </div>

            <form method="POST" enctype="multipart/form-data" style="padding:20px; border:2px dashed var(--border); text-align:center;">
                <div class="form-group">
                    <label for="csv" style="display:block; margin-bottom:15px; font-size:1.2em;">Choisir un fichier (CSV ou Excel)</label>
                    <input type="file" name="csv_file" id="csv" accept=".csv, .xlsx" required style="margin-bottom:20px;">
                </div>
                <button type="submit" class="btn btn-full"><i class="fa-solid fa-cloud-arrow-up"></i> Lancer l'import</button>
            </form>
        </div>
    </div>
</body>
</html>
