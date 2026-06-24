<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin', 'accueil', 'dispatch', 'tac', 'tech', 'directeur', 'charge_de_compte']);

$role = $_SESSION['role'];
$can_edit_client = in_array($role, ['commercial', 'admin', 'accueil', 'directeur', 'charge_de_compte']);
$can_manage_sites = in_array($role, ['commercial', 'admin', 'accueil', 'tech', 'charge_de_compte', 'directeur']);
$can_manage_contracts = in_array($role, ['commercial', 'admin', 'directeur']);

if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$client_id = $_GET['id'];
$error = "";
$success = "";
$next_site_code = previewNextSequentialCode('site_code', 'SAV_Sites', 'Id_Site', 1, 3);

// Traitement : Ajout Site
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_site') {
    if (!$can_manage_sites) {
        $error = "Permission refusée.";
    } else {
        $nom = $_POST['nom'];
        $adresse = $_POST['adresse'];
        $ville = $_POST['ville'];

        $code_site = $_POST['code_site'] ?? '';
        $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
        $contact = $_POST['contact_nom'];
        $tel = $_POST['contact_tel'];

        if (empty($nom) || empty($adresse)) {
            $error = "Nom et Adresse sont obligatoires pour un site.";
        } else {
            $code_site = getNextSequentialCode('site_code', 'SAV_Sites', 'Id_Site', 1, 3);

            $sql = "INSERT INTO SAV_Sites (Id_Client, Nom, Adresse, Ville, Id_Site, TEL, contact_nom, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = sqlsrv_query($conn, $sql, [$client_id, $nom, $adresse, $ville, $code_site, $tel, $contact, $latitude, $longitude]);
            if ($stmt) {
                $success = "Nouveau site d'intervention ajouté avec succès.";
                $next_site_code = previewNextSequentialCode('site_code', 'SAV_Sites', 'Id_Site', 1, 3);
            } else {
                error_log('[COMMERCIAL_CLIENT_DETAILS_SITE_CREATE] ' . db_last_error_message());
                $error = "Erreur lors de la creation du site.";
            }
        }
    }
}

// Récupérer infos client
$sql = "SELECT ID_Client as id, Nom as nom, ID_Client as code_client, Activite as type_contrat, Adresse as adresse, Ville as ville, TEL as telephone, Email as email, Contact as contact, TEL2 as tel2, TEL3 as tel3, Fax as fax, Site as site_web, Blocage as blocage, Modalite_Paiement as modalite_paiement FROM SAV_Clients WHERE ID_Client = ?";
$stmt = sqlsrv_query($conn, $sql, [$client_id]);
$client = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$client) die("Client introuvable.");

// Récupérer Sites
$sqlSites = "SELECT Id_Site as id, Id_Site as code_site, Nom as nom, Adresse as adresse, Ville as ville, TEL as contact_tel, latitude, longitude, contact_nom FROM SAV_Sites WHERE Id_Client = ?";
$stmtSites = sqlsrv_query($conn, $sqlSites, [$client_id]);

if ($stmtSites === false) {
    error_log('[COMMERCIAL_CLIENT_DETAILS_LOAD_SITES] ' . db_last_error_message());
    die("Erreur interne lors du chargement des sites.");
}

// Récupérer Contrats
$sqlContrats = "SELECT ID_CONTRAT as id, CODE_CONTRAT as numero_contrat, Date_Debut as date_debut, Date_Fin as date_fin
                     FROM CONTRAT
                     WHERE LTRIM(RTRIM(ISNULL(ID_CLIENT, ''))) = LTRIM(RTRIM(?))
                         OR LTRIM(RTRIM(ISNULL(Code_Client, ''))) = LTRIM(RTRIM(?))";
$stmtContrats = sqlsrv_query($conn, $sqlContrats, [$client_id, $client_id]);

$pageTitle = "Fiche Client : " . $client['nom'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .client-header-hero {
            background: linear-gradient(135deg, var(--dark-amethyst-3) 0%, var(--primary) 100%);
            padding: 40px; border-radius: var(--r-xl); box-shadow: 0 10px 30px rgba(58,1,92,.25);
            margin-bottom: 32px; color: white; position: relative; overflow: hidden;
            display: flex; flex-direction: column; gap: 24px;
        }
        .client-header-hero::before {
            content:''; position:absolute; top:-50%; right:-10%; width:500px; height:500px;
            background: radial-gradient(circle, rgba(255,255,255,.1) 0%, transparent 60%); border-radius:50%; pointer-events:none;
        }

        .hero-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px; z-index:1; }
        .hero-title { font-size: 2.2rem; font-weight: 800; margin: 0 0 10px; line-height: 1.2; font-family: 'Rajdhani', sans-serif;}
        .hero-badges { display: flex; gap: 10px; flex-wrap: wrap; }
        .hero-badge { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.2); color: white; padding: 6px 14px; border-radius: 30px; font-size: .85rem; font-weight: 600; display:flex; align-items:center; gap:8px;}
        .hero-badge.highlight { background: var(--accent); border: none; }

        .hero-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; background: rgba(0,0,0,.15); padding: 24px; border-radius: var(--r-md); border: 1px solid rgba(255,255,255,.1); z-index:1; }
        .info-col { display: flex; flex-direction: column; gap: 6px; }
        .info-label { font-size: .8rem; font-weight: 700; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .05em; display:flex; align-items:center; gap:8px; }
        .info-val { font-size: 1.05rem; font-weight: 600; color: white; line-height:1.4;}
        .info-val a { color: var(--accent-light); text-decoration: none; transition: color .2s; }
        .info-val a:hover { color: white; text-decoration: underline; }

        .section-split { display:flex; justify-content:space-between; align-items:flex-end; margin: 40px 0 20px; padding-bottom:16px; border-bottom:2px solid rgba(58,1,92,.08); }
        .section-split h2 { font-size: 1.4rem; color: var(--dark-amethyst-3); margin: 0; display:flex; align-items:center; gap:12px; font-weight:800; }

        .site-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-md); padding: 24px; margin-bottom: 24px; display: none; animation: slideDown .3s ease-out forwards; box-shadow: 0 10px 30px rgba(24,8,44,.05); }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px);} to{opacity:1;transform:translateY(0);} }

        .table-wrap { background: var(--surface); border-radius: var(--r-md); overflow: hidden; box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08); }
        .table-wrap table { width: 100%; border-collapse: collapse; }
        .table-wrap th { background: var(--surface-2); padding: 16px; text-transform: uppercase; font-size: .8rem; font-weight: 700; color: var(--text-muted); border-bottom: 2px solid rgba(58,1,92,.08); text-align:left; }
        .table-wrap td { padding: 16px; border-bottom: 1px solid rgba(58,1,92,.04); vertical-align: middle; }
        .table-wrap tr:hover { background-color: rgba(58,1,92,.02); }
        .table-wrap tr:last-child td { border-bottom: none; }

        .code-pill { background: var(--surface-2); color: var(--dark-amethyst-3); padding: 4px 8px; border-radius: 6px; font-family: monospace; font-weight: 700; border: 1px solid rgba(0,0,0,.05); font-size:.9rem;}

        .action-icon-btn { width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; background:rgba(58,1,92,.05); color:var(--dark-amethyst-3); text-decoration:none; transition:all .2s; }
        .action-icon-btn:hover { background:rgba(58,1,92,.1); transform:translateY(-2px); }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">
        <header>
            <div style="display:flex;align-items:center;gap:16px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-regular fa-folder-open text-accent" style="margin-right:8px;"></i>Dossier Client</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Consultation complète de la fiche</span>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <?php if($can_edit_client): ?>
                <a href="client_edit.php?id=<?= $client['id'] ?>" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-pen"></i> Modifier</a>
                <?php endif; ?>
                <a href="clients.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour</a>
            </div>
        </header>

        <div class="page-content">

            <?php if($error): ?><div class="alert alert-error alert-auto-dismiss"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- HEADER HERO -->
            <div class="client-header-hero">
                <div class="hero-top">
                    <div>
                        <h2 class="hero-title"><?= htmlspecialchars($client['nom']) ?></h2>
                        <div class="hero-badges">
                            <span class="hero-badge"><i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($client['code_client'] ?? 'CODE NON ASSIGNÉ') ?></span>
                            <?php if(!empty($client['type_contrat'])): ?>
                            <span class="hero-badge highlight"><i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($client['type_contrat']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="fa-regular fa-building" style="font-size:4rem; opacity:.2; color:var(--accent-light);"></i>
                </div>

                <div class="hero-info-grid">
                    <div class="info-col">
                        <span class="info-label"><i class="fa-solid fa-map-location-dot"></i> Informations Générales</span>
                        <div class="info-val" style="font-size:.95rem; font-weight:500;">
                            <strong>Adresse:</strong> <?= htmlspecialchars($client['adresse'] ?? 'Non renseignée') ?><br>
                            <strong>Ville:</strong> <?= htmlspecialchars($client['ville'] ?? 'Non renseignée') ?><br>
                            <?php if(!empty($client['blocage'])): ?>
                                <strong class="text-danger">Statut:</strong> <span class="badge badge-error"><?= htmlspecialchars($client['blocage']) ?></span><br>
                            <?php endif; ?>
                            <?php if(!empty($client['modalite_paiement'])): ?>
                                <strong>Paiement:</strong> <?= htmlspecialchars($client['modalite_paiement']) ?><br>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-col">
                        <span class="info-label"><i class="fa-solid fa-address-book"></i> Coordonnées Clés</span>
                        <div class="info-val" style="font-size:.95rem; font-weight:500;">
                            <?php if(!empty($client['contact'])): ?>
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px; color:var(--accent-light);">
                                    <i class="fa-solid fa-user-tie" style="opacity:.8; font-size:.9em;"></i> <?= htmlspecialchars($client['contact']) ?>
                                </div>
                            <?php endif; ?>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <i class="fa-solid fa-phone" style="opacity:.6; font-size:.9em;"></i> <?= htmlspecialchars($client['telephone'] ?: 'Non renseigné') ?>
                            </div>
                            <?php if(!empty($client['tel2'])): ?>
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <i class="fa-solid fa-mobile-screen" style="opacity:.6; font-size:.9em;"></i> <?= htmlspecialchars($client['tel2']) ?> (Tel/Mob 2)
                                </div>
                            <?php endif; ?>
                             <?php if(!empty($client['tel3'])): ?>
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <i class="fa-solid fa-mobile-screen" style="opacity:.6; font-size:.9em;"></i> <?= htmlspecialchars($client['tel3']) ?> (Tel/Mob 3)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-col">
                        <span class="info-label"><i class="fa-solid fa-at"></i> Digital & Contact</span>
                        <div class="info-val" style="font-size:.95rem; font-weight:500;">
                            <?php if(!empty($client['fax'])): ?>
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <i class="fa-solid fa-fax" style="opacity:.6; font-size:.9em;"></i> <?= htmlspecialchars($client['fax']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($client['email'])): ?>
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <i class="fa-solid fa-envelope" style="opacity:.6; font-size:.9em;"></i> <a href="mailto:<?= htmlspecialchars($client['email']) ?>"><?= htmlspecialchars($client['email']) ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($client['site_web'])): ?>
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <i class="fa-solid fa-globe" style="opacity:.6; font-size:.9em;"></i> <a href="<?= (strpos($client['site_web'], 'http') !== 0 ? 'https://' : '') . htmlspecialchars($client['site_web']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($client['site_web']) ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SITES D'INTERVENTION -->
            <div class="section-split">
                <h2><i class="fa-solid fa-location-dot text-success"></i> Sites d'Intervention</h2>
                <?php if($can_manage_sites): ?>
                <button class="btn btn-sm" onclick="document.getElementById('siteForm').style.display='block';" style="border-radius:30px;"><i class="fa-solid fa-plus"></i> Nouveau Site</button>
                <?php endif; ?>
            </div>

            <!-- PANNEAU CREATE SITE -->
            <div id="siteForm" class="site-panel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; border-bottom:1px solid rgba(58,1,92,.08); padding-bottom:16px;">
                    <h4 style="margin:0; font-size:1.1rem; color:var(--dark-amethyst-3);"><i class="fa-solid fa-map-pin text-primary" style="margin-right:8px;"></i>Création d'un Site Local</h4>
                    <button class="btn btn-sm btn-secondary" onclick="document.getElementById('siteForm').style.display='none';"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <div style="margin-bottom: 24px; padding:16px; background:rgba(33,150,243,.05); border-radius:var(--r-md); border:1px solid rgba(33,150,243,.1); display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm" onclick="getLocation('new_site')" style="background-color:var(--info); border:none; box-shadow:none;"><i class="fa-solid fa-location-crosshairs"></i> Détecter ma position GPS</button>
                    <span id="geoStatus_new_site" style="font-size:0.9em; font-weight:600; color:var(--info); display:none;"></span>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="add_site">

                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:20px;">
                        <div class="form-group">
                            <label>Nom de l'établissement <span class="text-danger">*</span></label>
                            <input type="text" name="nom" id="ns_nom" class="form-control" placeholder="ex: Usine Nord" required>
                        </div>
                        <div class="form-group">
                            <label>Code Site Interne (Automatique)</label>
                            <input type="text" name="code_site" value="<?= htmlspecialchars($next_site_code) ?>" readonly class="form-control" style="background:var(--surface-2);">
                        </div>
                        <div class="form-group">
                            <label>Adresse du site <span class="text-danger">*</span></label>
                            <input type="text" name="adresse" id="ns_adresse" class="form-control" placeholder="Adresse complète" required>
                        </div>
                        <div class="form-group">
                            <label>Ville</label>
                            <input type="text" name="ville" id="ns_ville" class="form-control" placeholder="Ville">
                        </div>
                        <div class="form-group">
                            <label>Contact sur place (Nom)</label>
                            <input type="text" name="contact_nom" class="form-control" placeholder="Nom du responsable">
                        </div>
                        <div class="form-group">
                            <label>Contact sur place (Tél)</label>
                            <input type="text" name="contact_tel" class="form-control" placeholder="06...">
                        </div>
                        <div class="form-group">
                            <label>Latitude <i class="fa-brands fa-google text-muted"></i></label>
                            <input type="text" name="latitude" id="ns_lat" class="form-control" placeholder="Coordonnées GPS">
                        </div>
                        <div class="form-group">
                            <label>Longitude <i class="fa-brands fa-google text-muted"></i></label>
                            <input type="text" name="longitude" id="ns_lng" class="form-control" placeholder="Coordonnées GPS">
                        </div>
                    </div>

                    <div style="margin-top:24px; text-align:right;">
                        <button type="submit" class="btn"><i class="fa-solid fa-cloud-arrow-up"></i> Enregistrer Site</button>
                    </div>
                </form>
            </div>

            <!-- TABLE DES SITES -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:100px;">Réf</th>
                            <th>Établissement & Info</th>
                            <th>Contact Local</th>
                            <th>Cartographie</th>
                            <?php if($can_manage_sites): ?><th style="text-align:right;">Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(sqlsrv_has_rows($stmtSites)): ?>
                        <?php while($site = sqlsrv_fetch_array($stmtSites, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><span class="code-pill"><?= htmlspecialchars($site['code_site'] ?? 'N/A') ?></span></td>
                            <td>
                                <strong style="color:var(--dark-amethyst-3); font-size:1.05rem;"><i class="fa-regular fa-building text-primary" style="margin-right:6px;"></i><?= htmlspecialchars($site['nom']) ?></strong>
                                <div style="margin-top:4px; font-size:.85rem; color:var(--text-muted);"><i class="fa-solid fa-map-pin" style="opacity:.5; margin-right:4px;"></i><?= htmlspecialchars($site['adresse']) ?>, <?= htmlspecialchars($site['ville']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600; color:var(--text);"><?= htmlspecialchars($site['contact_nom'] ?? 'Non spécifié') ?></div>
                                <div style="font-size:.85rem; color:var(--text-muted);"><i class="fa-solid fa-phone" style="opacity:.5;"></i> <?= htmlspecialchars($site['contact_tel'] ?? '—') ?></div>
                            </td>
                            <td>
                                <?php if(!empty($site['latitude']) && !empty($site['longitude'])): ?>
                                    <a href="https://www.google.com/maps?q=<?= $site['latitude'].','.$site['longitude'] ?>" target="_blank" class="badge" style="background:rgba(33,150,243,.1); color:#2196F3; border:none; text-decoration:none;"><i class="fa-solid fa-location-arrow"></i> Ouvrir Maps</a>
                                <?php else: ?>
                                    <span class="badge badge-normal" style="opacity:.5;">Non géolocalisé</span>
                                <?php endif; ?>
                            </td>
                            <?php if($can_manage_sites): ?>
                            <td style="text-align:right;">
                                <a href="site_edit.php?id=<?= urlencode($site['id']) ?>" class="action-icon-btn" title="Modifier le site"><i class="fa-solid fa-pen"></i></a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= $can_manage_sites ? 5 : 4 ?>" style="text-align:center; padding:40px; color:var(--text-muted);"><i class="fa-regular fa-compass" style="font-size:2rem; opacity:.5; margin-bottom:10px;"></i><br>Aucun site répertorié pour ce client.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- CONTRATS (Si non Tech) -->
            <?php if($role != 'tech'): ?>
            <div class="section-split">
                <h2><i class="fa-solid fa-file-signature text-primary"></i> Contrats de Maintenance</h2>
                <?php if($can_manage_contracts): ?>
                <a href="contrat_create.php?client_id=<?= $client['id'] ?>" class="btn btn-sm" style="border-radius:30px;"><i class="fa-solid fa-plus"></i> Lier un Contrat</a>
                <?php endif; ?>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Période de Validité</th>
                            <th>Statut</th>
                            <th style="text-align:right;">Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(sqlsrv_has_rows($stmtContrats)): ?>
                        <?php while($contrat = sqlsrv_fetch_array($stmtContrats, SQLSRV_FETCH_ASSOC)):
                            $isExp = ($contrat['date_fin'] && $contrat['date_fin'] < new DateTime());
                            $badgeCl = $isExp ? 'badge-urgente' : 'badge-resolu';
                            $statutTxt = $isExp ? 'EXPIRÉ' : 'ACTIF';
                        ?>
                        <tr>
                            <td><span class="code-pill"><?= htmlspecialchars($contrat['numero_contrat']) ?></span></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:16px;">
                                    <span style="color:var(--success); font-weight:600;"><i class="fa-regular fa-circle-play text-muted" style="margin-right:4px;"></i><?= $contrat['date_debut'] ? $contrat['date_debut']->format('d/m/Y') : '—' ?></span>
                                    <i class="fa-solid fa-arrow-right text-muted" style="opacity:.3;"></i>
                                    <span style="color:<?= $isExp ? 'var(--danger)' : 'var(--text)' ?>; font-weight:<?= $isExp ? '700' : '600' ?>;"><i class="fa-regular fa-circle-stop <?= $isExp ? 'text-danger' : 'text-muted' ?>" style="margin-right:4px;"></i><?= $contrat['date_fin'] ? $contrat['date_fin']->format('d/m/Y') : '—' ?></span>
                                </div>
                            </td>
                            <td><span class="badge <?= $badgeCl ?>"><?= $statutTxt ?></span></td>
                            <td style="text-align:right;">
                                <a href="contrat_edit.php?id=<?= urlencode($contrat['id']) ?>" class="action-icon-btn"><i class="fa-solid fa-arrow-right-to-bracket"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-muted);"><i class="fa-regular fa-folder-open" style="font-size:2rem; opacity:.5; margin-bottom:10px;"></i><br>Le client n'a aucun contrat lié actuellement.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div style="height:60px;"></div>

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

        // Script GPS
        function getLocation(prefix = 'new_site') {
            const statusSpan = document.getElementById('geoStatus_' + prefix);
            if (!statusSpan) return;
            statusSpan.style.display = 'inline-block';
            statusSpan.textContent = "Localisation GPS en cours...";

            const isLocalHost = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
            if (!window.isSecureContext && !isLocalHost) {
                statusSpan.innerHTML = "<i class='fa-solid fa-lock'></i> GPS bloqué: cette page est en HTTP. Utilisez HTTPS pour autoriser la géolocalisation.";
                return;
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;

                        document.getElementById('ns_lat').value = lat;
                        document.getElementById('ns_lng').value = lng;
                        statusSpan.textContent = "GPS trouvé ! Résolution Nominatim...";

                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.address) {
                                    const addressParts = [];
                                    if (data.address.house_number) addressParts.push(data.address.house_number);
                                    if (data.address.road) addressParts.push(data.address.road);

                                    const fullAddress = addressParts.join(' ');
                                    const city = data.address.city || data.address.town || data.address.village || '';

                                    if (fullAddress) document.getElementById('ns_adresse').value = fullAddress;
                                    if (city) document.getElementById('ns_ville').value = city;

                                    statusSpan.innerHTML = "<i class='fa-solid fa-check'></i> Adresse géolocalisée !";
                                    setTimeout(() => statusSpan.style.display = 'none', 3000);
                                } else {
                                    statusSpan.textContent = "GPS ok, adresse introuvable.";
                                }
                            })
                            .catch(err => {
                                statusSpan.textContent = "GPS ok, erreur d'adresse.";
                            });
                    },
                    function(error) {
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                statusSpan.textContent = "Permission GPS refusée par le navigateur.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                statusSpan.textContent = "Position GPS indisponible (signal faible).";
                                break;
                            case error.TIMEOUT:
                                statusSpan.textContent = "Timeout GPS: réessayez en extérieur.";
                                break;
                            default:
                                statusSpan.textContent = "Localisation bloquée ou impossible.";
                        }
                        setTimeout(() => statusSpan.style.display = 'none', 3000);
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                statusSpan.textContent = "Géolocalisation non supportée par le navigateur.";
            }
        }
    </script>
</body>
</html>
