<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin', 'accueil', 'dispatch', 'tac', 'tech', 'directeur', 'charge_de_compte']);

$role = $_SESSION['role'];
$can_edit = in_array($role, ['commercial', 'admin', 'accueil', 'directeur', 'charge_de_compte']);
$is_commercial_admin = in_array($role, ['commercial', 'admin', 'directeur']);

$error = "";
$success = "";
$next_client_code = previewNextSequentialCode('client_code', 'SAV_Clients', 'ID_Client', 3427120010, 10);

// Traitement formulaire ajout client
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_client') {
    if (!$can_edit) {
        $error = "Vous n'avez pas les droits pour ajouter un client.";
    } else {
        $nom = $_POST['nom'];
        $adresse = $_POST['adresse'];
        $email = $_POST['email'];

        if (empty($nom)) {
            $error = "Le nom du client est obligatoire.";
        } else {
            $type_contrat = $_POST['type_contrat'] ?? '';
            $ville = $_POST['ville'] ?? '';
            $tel = $_POST['telephone'] ?? '';

            // Nouveaux champs SysGM
            $contact = $_POST['contact'] ?? '';
            $tel2 = $_POST['tel2'] ?? '';
            $tel3 = $_POST['tel3'] ?? '';
            $fax = $_POST['fax'] ?? '';
            $site_web = $_POST['site_web'] ?? '';
            $blocage = $_POST['blocage'] ?? '';
            $modalite = $_POST['modalite_paiement'] ?? '';

            $code_client = getNextSequentialCode('client_code', 'SAV_Clients', 'ID_Client', 3427120010, 10);

            $sql = "INSERT INTO SAV_Clients (ID_Client, Nom, Adresse, Email, Activite, Ville, TEL, Contact, TEL2, TEL3, Fax, Site, Blocage, Modalite_Paiement)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [$code_client, $nom, $adresse, $email, $type_contrat, $ville, $tel, $contact, $tel2, $tel3, $fax, $site_web, $blocage, $modalite];
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt) {
                $success = "Nouveau client créé avec succès : " . htmlspecialchars($nom) . " (Code: " . htmlspecialchars($code_client) . ")";
                $next_client_code = previewNextSequentialCode('client_code', 'SAV_Clients', 'ID_Client', 3427120010, 10);
            } else {
                error_log('[COMMERCIAL_CLIENTS_CREATE] ' . db_last_error_message());
                $error = "Erreur lors de l'ajout du client.";
            }
        }
    }
}

// Recherche
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT ID_Client as id, Nom as nom, ID_Client as code_client, Activite as type_contrat, Ville as ville, TEL as telephone, Contact as contact, Blocage as blocage FROM SAV_Clients WHERE Nom LIKE ? ORDER BY Nom ASC";
$clients = query($sql, ['%' . $search . '%']);

$pageTitle = "Annuaire Clients";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .filter-bar {
            background: var(--surface); padding: 16px 24px; border-radius: var(--r-md); margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
            box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08);
        }

        .search-form { display: flex; gap: 12px; flex: 1; max-width: 450px; }
        .search-form .input-group { flex: 1; position: relative; }
        .search-form .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-form input { width: 100%; padding: 12px 16px 12px 42px; border: 1px solid var(--border); border-radius: 30px; font-family: inherit; transition: all .2s; }
        .search-form input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(155,93,229,.1); }
        .search-actions { display: flex; gap: 12px; }
        .search-actions .btn { border-radius: 30px; }
        .filter-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .filter-actions .btn { border-radius: 30px; white-space: nowrap; }

        .client-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }

        .client-card {
            background: var(--surface); border-radius: var(--r-md); padding: 24px;
            box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08);
            text-decoration: none; color: inherit; transition: all .3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; height: 100%;
        }
        .client-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
            background: linear-gradient(180deg, var(--dark-amethyst), var(--primary));
            opacity: 0; transition: opacity .3s;
        }
        .client-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(58,1,92,.1); border-color: transparent; }
        .client-card:hover::before { opacity: 1; }

        .client-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; gap: 12px; }
        .client-name { font-size: 1.15rem; color: var(--dark-amethyst-3); font-weight: 800; margin: 0; line-height: 1.3; }
        .client-icon { width: 48px; height: 48px; border-radius: 12px; background: rgba(155,93,229,.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .client-card:hover .client-icon { background: var(--primary); color: white; transform: rotate(5deg); transition: all .3s; }

        .client-info { display: flex; flex-direction: column; gap: 8px; font-size: .9rem; color: var(--text-muted); }
        .client-info p { margin: 0; display: flex; align-items: center; gap: 10px; }
        .client-info i { color: var(--primary); width: 16px; text-align: center; }
        .client-info strong { flex-shrink: 0; }

        .client-footer { margin-top: 24px; padding-top: 16px; border-top: 1px dashed rgba(58,1,92,.1); display: flex; justify-content: space-between; align-items: center; color: var(--accent); font-weight: 700; font-size: .9rem; }
        .client-card:hover .client-footer i { transform: translateX(5px); transition: transform .3s; }

        /* Modal styling standard */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(17,0,28,.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .modal-content { background: var(--surface); margin: auto; padding: 0; border: none; width: 90%; max-width: 700px; border-radius: var(--r-md); box-shadow: 0 25px 50px rgba(0,0,0,.2); animation: modalFadeIn .3s ease; overflow: hidden; display: flex; flex-direction: column; max-height: 90vh; }
        .modal-header { padding: 24px; border-bottom: 1px solid rgba(58,1,92,.08); display: flex; justify-content: space-between; align-items: center; background: var(--surface-2); }
        .modal-header h3 { margin: 0; font-size: 1.25rem; color: var(--dark-amethyst-3); display: flex; align-items: center; gap: 10px; }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid rgba(58,1,92,.08); background: var(--surface-2); display: flex; justify-content: flex-end; gap: 12px; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        @keyframes modalFadeIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }

        @media (max-width: 980px) {
            .filter-bar {
                padding: 14px;
                gap: 12px;
            }

            .search-form {
                width: 100%;
                max-width: none;
                flex-wrap: wrap;
            }

            .search-form .input-group {
                min-width: 0;
                flex: 1 1 100%;
            }

            .search-actions {
                width: 100%;
            }

            .search-actions .btn {
                flex: 1 1 auto;
                justify-content: center;
                text-align: center;
            }

            .filter-actions {
                width: 100%;
                gap: 8px;
            }

            .filter-actions .btn {
                flex: 1 1 180px;
                justify-content: center;
                text-align: center;
                font-size: .9rem;
                padding: 10px 12px;
            }

            .client-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        }

        @media (max-width: 640px) {
            .main-content {
                padding: 10px !important;
            }

            .page-content {
                padding: 0 !important;
            }

            .search-actions .btn {
                min-height: 42px;
            }

            .filter-actions .btn {
                flex: 1 1 calc(50% - 8px);
                min-width: 0;
                border-radius: 20px;
            }

            .client-card {
                max-width: 100%;
                padding: 16px;
            }

            .client-header {
                margin-bottom: 14px;
            }

            .client-name {
                font-size: 1.02rem;
            }

            .client-icon {
                width: 42px;
                height: 42px;
                font-size: 1.2rem;
            }

            .client-info {
                font-size: .85rem;
                gap: 7px;
            }
        }

        @media (max-width: 420px) {
            .filter-actions .btn {
                flex: 1 1 100%;
            }

            .search-actions {
                gap: 8px;
            }

            .search-actions .btn {
                flex: 1 1 100%;
            }

            .client-footer {
                font-size: .84rem;
            }
        }

    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">
        <header>
            <div style="display:flex;align-items:center;gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-regular fa-address-book text-accent" style="margin-right:8px;"></i>Annuaire Clients</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Recherche et gestion des fiches clients & sites</span>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <span class="badge badge-normal" style="font-size:1rem; padding:8px 16px;"><i class="fa-solid fa-user-tag text-accent"></i> Rôle: <?= ucfirst($role) ?></span>
                <?php include __DIR__ . '/../../includes/notification_ui.php'; ?>
            </div>
        </header>

        <div class="page-content">

            <?php if($error): ?><div class="alert alert-error alert-auto-dismiss"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div class="filter-bar">
                <form class="search-form" method="GET">
                    <div class="input-group">
                        <i class="fa-solid fa-magnifying-glass input-icon"></i>
                        <input type="text" name="search" placeholder="Rechercher par raison sociale..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="search-actions">
                        <button type="submit" class="btn" style="padding:0 24px;">Filtrer</button>
                        <?php if($search): ?>
                            <a href="clients.php" class="btn btn-secondary" style="padding:0 16px;" title="Effacer"><i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if($can_edit): ?>
                <div class="filter-actions">
                    <a href="../admin/import_csv.php?type=clients" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Importer</a>
                    <a href="../admin/import_csv.php?type=sites" class="btn btn-secondary"><i class="fa-solid fa-map-location-dot"></i> Importer Sites</a>
                    <button class="btn" onclick="openModal('addClientModal')"><i class="fa-solid fa-plus" style="margin-right:8px;"></i>Créer Fiche</button>
                </div>
                <?php endif; ?>
            </div>

            <?php if(sqlsrv_has_rows($clients)): ?>
            <div class="client-grid">
                <?php while($client = sqlsrv_fetch_array($clients, SQLSRV_FETCH_ASSOC)): ?>
                    <a href="client_details.php?id=<?= urlencode($client['id']) ?>" class="client-card">
                        <div>
                            <div class="client-header">
                                <h3 class="client-name"><?= htmlspecialchars($client['nom']) ?></h3>
                                <div class="client-icon"><i class="fa-regular fa-building"></i></div>
                            </div>
                            <div class="client-info">
                                <p><i class="fa-solid fa-hashtag"></i> <strong>CODE:</strong> <?= htmlspecialchars($client['code_client'] ?? 'Non assigné') ?></p>
                                <p><i class="fa-solid fa-location-dot"></i> <strong>VILLE:</strong> <?= htmlspecialchars($client['ville'] ?? 'Non spécifiée') ?></p>
                                <p><i class="fa-solid fa-phone"></i> <strong>TEL:</strong> <?= htmlspecialchars($client['telephone'] ?: 'Non renseigné') ?></p>
                                <?php if(!empty($client['contact'])): ?>
                                    <p><i class="fa-solid fa-user-tie"></i> <strong>CONTACT:</strong> <?= htmlspecialchars($client['contact']) ?></p>
                                <?php endif; ?>
                                <?php if(!empty($client['type_contrat'])): ?>
                                    <p><i class="fa-solid fa-briefcase"></i> <strong>SECTEUR:</strong> <?= htmlspecialchars($client['type_contrat']) ?></p>
                                <?php endif; ?>
                                <?php if(!empty($client['blocage'])): ?>
                                    <p><i class="fa-solid fa-ban text-danger"></i> <strong>STATUT:</strong> <span class="badge badge-error"><?= htmlspecialchars($client['blocage']) ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="client-footer">
                            <span>Ouvrir le dossier</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="background:var(--surface); border-radius:var(--r-md); padding:60px 20px; text-align:center; border:1px dashed rgba(58,1,92,.2);">
                <i class="fa-solid fa-building-circle-xmark" style="font-size:4rem; color:var(--text-muted); opacity:.5; margin-bottom:24px;"></i>
                <h3 style="color:var(--dark-amethyst-3); margin-top:0;">Aucun client correspondant</h3>
                <p style="color:var(--text-muted); max-width:400px; margin:0 auto;">Il n'y a aucun client avec ce nom ou la base est vide. Essayez de modifier vos filtres.</p>
                <?php if($can_edit): ?>
                    <button class="btn" onclick="openModal('addClientModal')" style="border-radius:30px; margin-top:24px;"><i class="fa-solid fa-plus"></i> Créer le premier client</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Modal Ajout Client -->
    <?php if($can_edit): ?>
    <div id="addClientModal" class="modal">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-building-circle-check text-success"></i> Nouvelle Fiche Client</h3>
                <span class="close" onclick="closeModal('addClientModal')" style="cursor:pointer; color:var(--text-muted); font-size:1.5rem;"><i class="fa-solid fa-xmark"></i></span>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="add_client">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Raison Sociale / Nom <span class="text-danger">*</span></label>
                        <div style="position:relative;">
                            <i class="fa-regular fa-building input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                            <input type="text" name="nom" required class="form-control" placeholder="ex: Entreprise ABC" style="padding-left:36px; width:100%;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Code Client (Automatique)</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-hashtag input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                            <input type="text" name="code_client" value="<?= htmlspecialchars($next_client_code) ?>" readonly class="form-control" style="padding-left:36px; width:100%; background:var(--surface-2);">
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Secteur d'Activité / Type</label>
                        <div style="position:relative;">
                            <select name="type_contrat" class="form-control" style="appearance:none; padding-left:36px; width:100%;">
                                <option value="INTERVENTION">Intervention Standard</option>
                                <option value="CONTRAT">Client sous Contrat</option>
                                <option value="PROSPECT">Prospect</option>
                            </select>
                            <i class="fa-solid fa-briefcase input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                            <i class="fa-solid fa-chevron-down" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Téléphone Standard</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-phone input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                            <input type="tel" name="telephone" placeholder="05..." class="form-control" style="padding-left:36px; width:100%;">
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group" style="margin-bottom:20px;">
                        <label>Email principal</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-envelope input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                            <input type="email" name="email" placeholder="contact@entreprise.com" class="form-control" style="padding-left:36px; width:100%;">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:20px;">
                        <label>Contact sur place / Interlocuteur</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-user input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                            <input type="text" name="contact" placeholder="Nom du responsable" class="form-control" style="padding-left:36px; width:100%;">
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Téléphone 2</label>
                        <input type="tel" name="tel2" class="form-control" style="width:100%;">
                    </div>
                    <div class="form-group">
                        <label>Téléphone 3</label>
                        <input type="tel" name="tel3" class="form-control" style="width:100%;">
                    </div>
                    <div class="form-group">
                        <label>Fax</label>
                        <input type="text" name="fax" class="form-control" style="width:100%;">
                    </div>
                </div>

                <div style="border-top:1px dashed rgba(58,1,92,.1); margin:24px 0;"></div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Adresse Postale / Siège</label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-location-dot input-icon" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                        <input type="text" name="adresse" placeholder="123 Avenue Mohamed V..." class="form-control" style="padding-left:36px; width:100%;">
                    </div>
                </div>

                <div class="form-grid" style="margin-bottom:24px;">
                    <div class="form-group">
                        <label>Site Web</label>
                        <input type="text" name="site_web" placeholder="www.exemple.com" class="form-control" style="width:100%;">
                    </div>
                    <div class="form-group">
                        <label>Modalité de paiement</label>
                        <input type="text" name="modalite_paiement" placeholder="Virement 30j..." class="form-control" style="width:100%;">
                    </div>
                    <div class="form-group">
                        <label>Statut Blocage</label>
                        <select name="blocage" class="form-control" style="width:100%;">
                            <option value="">Aucun blocage</option>
                            <option value="Bloqué Secrétariat">Bloqué Secrétariat</option>
                            <option value="Bloqué Comptabilité">Bloqué Comptabilité</option>
                            <option value="Inactif">Inactif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addClientModal')">Annuler</button>
                <button type="submit" class="btn"><i class="fa-solid fa-check"></i> Enregistrer le Client</button>
            </div>
        </form>
    </div>
    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    </script>
    <?php endif; ?>

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
