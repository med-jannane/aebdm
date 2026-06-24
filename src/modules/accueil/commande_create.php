<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

$error = "";
$success = "";

// Initialiser les clients pour la liste
$clients = query("SELECT ID_Client as id, Nom as nom, ID_Client as code_client FROM SAV_Clients ORDER BY Nom");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $client_id = $_POST['client_id'];
    $site_id = !empty($_POST['site_id']) ? $_POST['site_id'] : null;
    $numero = $_POST['numero'];
    $montant = $_POST['montant'];
    $statut = $_POST['statut'];
    
    // Récupérer infos client pour dénormalisation (demandé: nom, code)
    // Note: On stocke nom/code pour garder l'historique NAV tel quel même si client change
    $cli_info = sqlsrv_fetch_array(query("SELECT Nom as nom, ID_Client as code_client FROM SAV_Clients WHERE ID_Client = ?", [$client_id]), SQLSRV_FETCH_ASSOC);
    $nom_client = $cli_info['nom'];
    $code_client = $cli_info['code_client'];

    // Upload Fichier
    $fichier_nom = null;
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == 0) {
        $upload_dir = __DIR__ . '/../../../public/uploads/commandes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $maxFileSize = 8 * 1024 * 1024; // 8 MB
        $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        $originalName = (string)($_FILES['fichier']['name'] ?? '');
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $tmpPath = (string)($_FILES['fichier']['tmp_name'] ?? '');
        $fileSize = (int)($_FILES['fichier']['size'] ?? 0);
        $mimeType = '';
        if ($tmpPath !== '' && is_uploaded_file($tmpPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = (string)finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
            }
        }

        if ($fileSize > $maxFileSize) {
            $error = "Fichier trop volumineux (max 8 Mo).";
        } elseif (!in_array($ext, $allowedExt, true) || !in_array($mimeType, $allowedMime, true)) {
            $error = "Type de fichier non autorisé.";
        } elseif (!is_uploaded_file($tmpPath)) {
            $error = "Upload invalide détecté.";
        } else {
            $fichier_nom = 'CMD_' . bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($tmpPath, $upload_dir . $fichier_nom)) {
                $error = "Echec lors de l'enregistrement du fichier.";
            }
        }
    }

    if (!$error && (empty($client_id) || empty($numero))) {
        $error = "Client et Numéro de commande obligatoires.";
    } elseif (!$error) {
        $sql = "INSERT INTO Commandes (code_client, nom_client, numero_commande, montant_ht, statut, fichier_joint, site_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $params = [$code_client, $nom_client, $numero, $montant, $statut, $fichier_nom, $site_id];
        
        if (sqlsrv_query($conn, $sql, $params)) {
             header("Location: commandes.php?msg=created");
             exit;
        } else {
            error_log('[ACCUEIL_COMMANDE_CREATE] ' . db_last_error_message());
            $error = "Erreur lors de l'enregistrement de la commande.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single { height: 45px !important; border: 1px solid var(--border) !important; border-radius: var(--radius-md) !important; padding: 8px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { top: 8px !important; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Nouvelle Commande (NAV)</h1>
            </div>
            <a href="commandes.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour liste</a>
        </header>

        <?php if($error): ?>
            <div class="card" style="border-left:4px solid var(--danger); color:var(--danger);">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
                    <label><i class="fa-regular fa-user"></i> Client *</label>
                    <select name="client_id" id="client_select" style="width:100%;" required>
                        <option value="">-- Choisir un client --</option>
                        <?php while($c = sqlsrv_fetch_array($clients, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['nom']); ?> (<?php echo htmlspecialchars($c['code_client']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-location-dot"></i> Site Concerné</label>
                    <select name="site_id" id="site_select" style="width:100%;" class="form-control">
                        <option value="">-- Sélectionner le client d'abord --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-receipt"></i> Numéro de Commande *</label>
                    <input type="text" name="numero" required placeholder="Ex: CMD-2024-005" class="form-control">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label><i class="fa-solid fa-dollar-sign"></i> Montant HT</label>
                        <input type="number" name="montant" step="0.01" placeholder="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-info-circle"></i> Statut</label>
                        <select name="statut" class="form-control">
                            <option value="EN_ATTENTE">En Attente</option>
                            <option value="VALIDE">Validé</option>
                            <option value="FACTURE">Facturé</option>
                            <option value="LIVRE">Livré</option>
                            <option value="ANNULE">Annulé</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-file-arrow-up"></i> Fichier Joint (PDF, Doc...)</label>
                    <input type="file" name="fichier" class="form-control">
                </div>

                <button type="submit" class="btn btn-full" style="margin-top:20px;">
                    <i class="fa-solid fa-save"></i> Enregistrer la Commande
                </button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#client_select').select2({ placeholder: "Recherche client..." });

            // Dynamic Site Loading
            $('#client_select').on('change', function() {
                var clientId = $(this).val();
                if(!clientId) return;

                $.ajax({
                    url: 'get_client_details_api.php', 
                    data: { client_id: clientId },
                    dataType: 'json',
                    success: function(data) {
                        var siteOpts = '<option value="">-- Choisir un site --</option>';
                        if(data.sites && data.sites.length > 0) {
                            data.sites.forEach(function(s) {
                                siteOpts += '<option value="'+s.id+'">' + s.nom + ' (' + s.ville + ')</option>';
                            });
                        }
                        $('#site_select').html(siteOpts);
                    }
                });
            });
            
            // Mobile toggle script handling (since we included head.php, check if script.js is loaded or needs init)
             $('.mobile-toggle').click(function() {
                $('.sidebar').toggleClass('active');
            });
        });
    </script>
</body>
</html>
