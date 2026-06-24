<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin', 'accueil']);

$role = $_SESSION['role'];
if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$id = $_GET['id'];
$error = "";
$success = "";

// Récupérer le client
$sql = "SELECT ID_Client as id, Nom as nom, ID_Client as code_client, Activite as type_contrat, Adresse as adresse, Ville as ville, '' as region, '' as code_postal, TEL as telephone, Email as email, Contact as contact, TEL2 as tel2, TEL3 as tel3, Fax as fax, Site as site_web, Blocage as blocage, Modalite_Paiement as modalite_paiement, '' as notes FROM SAV_Clients WHERE ID_Client = ?";
$client = sqlsrv_fetch_array(query($sql, [$id]), SQLSRV_FETCH_ASSOC);

if (!$client) die("Client introuvable.");

// Traitement Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = $_POST['nom'];
    $adresse = $_POST['adresse'];
    $ville = $_POST['ville'];
    $region = $_POST['region'] ?? '';
    $cp = $_POST['code_postal'] ?? '';
    $email = $_POST['email'];
    $telephone = $_POST['telephone'];
    $code_client = $_POST['code_client'];
    $type_contrat = $_POST['type_contrat'];
    $notes = $_POST['notes'] ?? '';
    
    // Nouveux champs
    $contact = $_POST['contact'] ?? '';
    $tel2 = $_POST['tel2'] ?? '';
    $tel3 = $_POST['tel3'] ?? '';
    $fax = $_POST['fax'] ?? '';
    $site_web = $_POST['site_web'] ?? '';
    $blocage = $_POST['blocage'] ?? '';
    $modalite = $_POST['modalite_paiement'] ?? '';

    if (empty($nom)) {
        $error = "Le nom du client est obligatoire.";
    } else {
        $sql = "UPDATE SAV_Clients SET 
                    Nom = ?, Adresse = ?, Ville = ?,  
                    Email = ?, TEL = ?, Activite = ?,
                    Contact = ?, TEL2 = ?, TEL3 = ?, Fax = ?, Site = ?, Blocage = ?, Modalite_Paiement = ?
                WHERE ID_Client = ?";
        $params = [
            $nom, $adresse, $ville, 
            $email, $telephone, $type_contrat,
            $contact, $tel2, $tel3, $fax, $site_web, $blocage, $modalite, 
            $id
        ];
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            $success = "Les informations du client ont été mises à jour avec succès.";
            // Rafraichir
            $client['nom'] = $nom;
            $client['adresse'] = $adresse;
            $client['ville'] = $ville;
            $client['region'] = $region;
            $client['code_postal'] = $cp;
            $client['email'] = $email;
            $client['telephone'] = $telephone;
            $client['code_client'] = $code_client;
            $client['type_contrat'] = $type_contrat;
            $client['contact'] = $contact;
            $client['tel2'] = $tel2;
            $client['tel3'] = $tel3;
            $client['fax'] = $fax;
            $client['site_web'] = $site_web;
            $client['blocage'] = $blocage;
            $client['modalite_paiement'] = $modalite;
            $client['notes'] = $notes;
        } else {
            error_log('[COMMERCIAL_CLIENT_EDIT] ' . db_last_error_message());
            $error = "Erreur lors de la mise a jour du client.";
        }
    }
}

$pageTitle = "Modification Client : " . $client['nom'];
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
                    <h1 style="margin:0;"><i class="fa-solid fa-pen-to-square text-accent" style="margin-right:8px;"></i>Éditer le Client</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Mise à jour de la fiche "<?= htmlspecialchars($client['nom']) ?>"</span>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="client_details.php?id=<?= $id ?>" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour au dossier</a>
            </div>
        </header>

        <div class="page-content" style="max-width:900px; margin:0 auto;">

            <?php if($error): ?><div class="alert alert-error alert-auto-dismiss"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <form method="POST" class="card" style="padding:0; overflow:hidden;">
                
                <div style="background:var(--surface-2); padding:24px 32px; border-bottom:1px solid rgba(58,1,92,.08);">
                    <h3 style="margin:0; font-size:1.2rem; color:var(--dark-amethyst-3);"><i class="fa-solid fa-id-card text-primary" style="margin-right:8px;"></i>Informations Générales</h3>
                </div>
                
                <div style="padding:32px;">
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Raison Sociale / Nom du Client <span class="text-danger">*</span></label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-building input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="nom" value="<?= htmlspecialchars($client['nom']) ?>" required class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Code Client (Référence)</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-hashtag input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="code_client" value="<?= htmlspecialchars($client['code_client'] ?? '') ?>" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Catégorie / Secteur</label>
                                <div style="position:relative;">
                                    <select name="type_contrat" class="form-control" style="appearance:none; padding-left:36px; width:100%;">
                                        <?php 
                                        $types = ['INTERVENTION', 'CONTRAT', 'PROSPECT'];
                                        foreach($types as $t):
                                            $sel = ($client['type_contrat'] == $t) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $t ?>" <?= $sel ?>><?= ucfirst(strtolower($t)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-briefcase input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <i class="fa-solid fa-chevron-down" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Téléphone Standard</label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-phone input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="tel" name="telephone" value="<?= htmlspecialchars($client['telephone'] ?? '') ?>" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Email de Contact</label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-envelope input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="email" name="email" value="<?= htmlspecialchars($client['email']) ?>" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Contact sur place / Interlocuteur</label>
                                <div style="position:relative;">
                                    <i class="fa-regular fa-user input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                    <input type="text" name="contact" value="<?= htmlspecialchars($client['contact'] ?? '') ?>" class="form-control" style="padding-left:36px; width:100%;">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Téléphone 2</label>
                                <input type="tel" name="tel2" value="<?= htmlspecialchars($client['tel2'] ?? '') ?>" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Téléphone 3</label>
                                <input type="tel" name="tel3" value="<?= htmlspecialchars($client['tel3'] ?? '') ?>" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Fax</label>
                                <input type="text" name="fax" value="<?= htmlspecialchars($client['fax'] ?? '') ?>" class="form-control" style="width:100%;">
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px dashed rgba(58,1,92,.1); margin:32px 0;"></div>

                    <h4 style="color:var(--dark-amethyst-3); margin-top:0; margin-bottom:20px;"><i class="fa-solid fa-map-location-dot text-accent" style="margin-right:8px;"></i>Coordonnées Postales</h4>
                    
                    <div class="form-section">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Adresse du siège social</label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-map-pin input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                                <input type="text" name="adresse" value="<?= htmlspecialchars($client['adresse']) ?>" class="form-control" style="padding-left:36px; width:100%;">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 2fr 2fr; gap:16px;">
                            <div class="form-group">
                                <label>Code Postal</label>
                                <input type="text" name="code_postal" value="<?= htmlspecialchars($client['code_postal'] ?? '') ?>" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Ville</label>
                                <input type="text" name="ville" value="<?= htmlspecialchars($client['ville']) ?>" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Région</label>
                                <input type="text" name="region" value="<?= htmlspecialchars($client['region'] ?? '') ?>" class="form-control" style="width:100%;">
                            </div>
                        </div>
                        
                        <div class="form-grid" style="margin-top:16px;">
                            <div class="form-group">
                                <label>Site Web</label>
                                <input type="text" name="site_web" value="<?= htmlspecialchars($client['site_web'] ?? '') ?>" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Modalité de paiement</label>
                                <input type="text" name="modalite_paiement" value="<?= htmlspecialchars($client['modalite_paiement'] ?? '') ?>" class="form-control" style="width:100%;">
                            </div>
                            <div class="form-group">
                                <label>Statut Blocage</label>
                                <select name="blocage" class="form-control" style="width:100%;">
                                    <option value="" <?= empty($client['blocage']) ? 'selected' : '' ?>>Aucun blocage</option>
                                    <option value="Bloqué Secrétariat" <?= ($client['blocage'] == 'Bloqué Secrétariat') ? 'selected' : '' ?>>Bloqué Secrétariat</option>
                                    <option value="Bloqué Comptabilité" <?= ($client['blocage'] == 'Bloqué Comptabilité') ? 'selected' : '' ?>>Bloqué Comptabilité</option>
                                    <option value="Inactif" <?= ($client['blocage'] == 'Inactif') ? 'selected' : '' ?>>Inactif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="border-top:1px dashed rgba(58,1,92,.1); margin:32px 0;"></div>

                    <h4 style="color:var(--dark-amethyst-3); margin-top:0; margin-bottom:20px;"><i class="fa-solid fa-comment-dots text-primary" style="margin-right:8px;"></i>Informations Complémentaires</h4>
                    
                    <div class="form-group">
                        <label>Notes & Observations (Internes)</label>
                        <textarea name="notes" placeholder="Horaires, préférences, accès..." class="form-control" style="width:100%; min-height:100px; resize:vertical; padding:12px; font-family:inherit;"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                    </div>

                </div>
                
                <div style="background:var(--surface-2); padding:24px 32px; border-top:1px solid rgba(58,1,92,.08); text-align:right;">
                    <button type="submit" class="btn" style="padding:14px 32px; font-size:1.05rem;"><i class="fa-solid fa-floppy-disk" style="margin-right:8px;"></i> Enregistrer la fiche</button>
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
