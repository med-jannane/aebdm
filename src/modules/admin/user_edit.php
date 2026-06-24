<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role('admin');

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit;
}

$id = $_GET['id'];
$error = "";
$success = "";

// Récupérer l'utilisateur
$sql = "SELECT * FROM Users WHERE id = ?";
$user = sqlsrv_fetch_array(query($sql, [$id]), SQLSRV_FETCH_ASSOC);

if (!$user) die("Utilisateur introuvable.");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom_complet = $_POST['nom_complet'];
    $role = $_POST['role'];
    $password = $_POST['password']; // Optionnel

    if (empty($nom_complet) || empty($role)) {
        $error = "Nom Complet et Rôle sont obligatoires.";
    } else {
        $manager_id = ($role === 'tech') ? ($_POST['manager_id'] ?? null) : null;
        if (empty($manager_id)) $manager_id = null;
        
        $email = $_POST['email'] ?? null;
        $region = $_POST['region'] ?? null;

        if (!empty($password)) {
            // Mise à jour avec mot de passe
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE Users SET nom_complet = ?, role = ?, mot_de_passe = ?, manager_id = ?, email = ?, region = ? WHERE id = ?";
            $params = [$nom_complet, $role, $hashed_password, $manager_id, $email, $region, $id];
        } else {
            // Mise à jour sans mot de passe
            $sql = "UPDATE Users SET nom_complet = ?, role = ?, manager_id = ?, email = ?, region = ? WHERE id = ?";
            $params = [$nom_complet, $role, $manager_id, $email, $region, $id];
        }

        if (sqlsrv_query($conn, $sql, $params)) {
            $success = "Profil utilisateur mis à jour avec succès.";
            // Refresh data
            $user['nom_complet'] = $nom_complet;
            $user['role'] = $role;
            $user['manager_id'] = $manager_id;
            $user['email'] = $email;
            $user['region'] = $region;
        } else {
            error_log('[ADMIN_USER_EDIT] ' . db_last_error_message());
            $error = "Erreur lors de la mise a jour de l'utilisateur.";
        }
    }
}

// Fetch chargés de compte for the dropdown
$managers = query("SELECT id, nom_complet FROM Users WHERE role = 'charge_de_compte' AND id != ? ORDER BY nom_complet ASC", [$id]);
$charge_comptes = [];
while ($row = sqlsrv_fetch_array($managers, SQLSRV_FETCH_ASSOC)) {
    $charge_comptes[] = $row;
}

$pageTitle = "Modifier Utilisateur " . htmlspecialchars($user['nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .form-section { background: var(--surface); border-radius: var(--r-md); padding: 32px; box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08); max-width: 800px; margin: 0 auto; }
        .form-section-title { font-size: 1.2rem; color: var(--dark-amethyst-3); margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid rgba(58,1,92,.08); display:flex; align-items:center; gap:10px; font-weight:700;}
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        
        .role-hint { font-size: 0.85rem; color: var(--text-muted); margin-top: 6px; display: block; }
        .disabled-field-group { opacity: 0.8; pointer-events: none; }
        .disabled-field { background-color: var(--surface-2) !important; color: var(--text-muted); border-color: var(--border) !important;}

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-section { padding: 20px; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
             <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-user-pen text-accent" style="margin-right:8px;"></i>Modification Profil</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Edition du compte: <span class="badge badge-normal" style="font-family:monospace; margin-left:8px;"><i class="fa-solid fa-fingerprint text-accent"></i> <?= htmlspecialchars($user['nom']) ?></span></span>
                </div>
            </div>
            <a href="users.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour Liste</a>
        </header>

        <div class="page-content">

            <?php if($error): ?><div class="alert alert-error" style="max-width:800px; margin:0 auto 24px;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success alert-auto-dismiss" style="max-width:800px; margin:0 auto 24px;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div class="form-section">
                <form method="POST">
                    
                    <div class="form-section-title"><i class="fa-solid fa-id-badge text-primary"></i> Identifiants et Profil</div>
                    
                    <div class="form-grid" style="margin-bottom:24px;">
                        <div class="form-group disabled-field-group">
                            <label>Identifiant de connexion (Login) <i class="fa-solid fa-lock text-muted" style="margin-left:4px;" title="Lecture seule"></i></label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-fingerprint input-icon text-muted"></i>
                                <input type="text" value="<?= htmlspecialchars($user['nom'] ?? '') ?>" disabled class="form-control disabled-field" style="padding-left:40px;">
                            </div>
                            <span class="role-hint">L'identifiant de connexion ne peut pas être modifié une fois créé.</span>
                        </div>

                        <div class="form-group">
                            <label>Nom Complet <span style="color:var(--danger);">*</span></label>
                            <div style="position:relative;">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" name="nom_complet" value="<?= htmlspecialchars($user['nom_complet']) ?>" required class="form-control" style="padding-left:40px;" placeholder="ex: Pierre Dupont">
                            </div>
                        </div>
                    </div>

                    <div class="form-grid" style="margin-bottom:32px;">
                        <div class="form-group">
                            <label>Nouveau Mot de passe</label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-key input-icon"></i>
                                <input type="password" name="password" class="form-control" style="padding-left:40px;" placeholder="Laisser vide pour ne pas changer">
                            </div>
                            <span class="role-hint">Remplissez ce champ uniquement pour forcer un nouveau mot de passe.</span>
                        </div>

                        <div class="form-group">
                            <label>Rôle <span style="color:var(--danger);">*</span></label>
                            <div style="position:relative;">
                                <select name="role" id="roleSelect" required class="form-control" style="padding-left:40px; appearance:none;" onchange="toggleManagerField()">
                                    <option value="commercial" <?= $user['role']=='commercial' ? 'selected' : '' ?>>Commercial</option>
                                    <option value="accueil" <?= $user['role']=='accueil' ? 'selected' : '' ?>>Accueil (Hotline)</option>
                                    <option value="tac" <?= $user['role']=='tac' ? 'selected' : '' ?>>TAC (Analyse technique)</option>
                                    <option value="dispatch" <?= $user['role']=='dispatch' ? 'selected' : '' ?>>Dispatch (Planification)</option>
                                    <option value="tech" <?= $user['role']=='tech' ? 'selected' : '' ?>>Technicien (Intervenant)</option>
                                    <option value="charge_de_compte" <?= $user['role']=='charge_de_compte' ? 'selected' : '' ?>>Chargé de Compte</option>
                                    <option value="directeur" <?= $user['role']=='directeur' ? 'selected' : '' ?>>Directeur</option>
                                    <option value="admin" <?= $user['role']=='admin' ? 'selected' : '' ?>>Administrateur Système</option>
                                </select>
                                <i class="fa-solid fa-user-tag input-icon text-accent"></i>
                                <i class="fa-solid fa-chevron-down" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title"><i class="fa-solid fa-address-book text-success"></i> Coordonnées & Secteur</div>

                    <div class="form-grid" style="margin-bottom:24px;">
                        <div class="form-group">
                            <label>Email Professionnel</label>
                            <div style="position:relative;">
                                <i class="fa-regular fa-envelope input-icon"></i>
                                <input type="email" name="email" class="form-control" style="padding-left:40px;" placeholder="exemple@entreprise.com" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Région Associée</label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-location-crosshairs input-icon"></i>
                                <input type="text" name="region" class="form-control" style="padding-left:40px;" placeholder="Ex: Île-de-France, Nord..." value="<?= htmlspecialchars($user['region'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="managerGroup" style="display: none; background:rgba(155,93,229,.05); border:1px solid rgba(155,93,229,.2); padding:20px; border-radius:var(--r-md); margin-bottom:24px;">
                        <label style="color:var(--dark-amethyst-3);"><i class="fa-solid fa-sitemap text-accent"></i> Supervisé par (Chargé de Compte)</label>
                        <select name="manager_id" class="form-control" style="margin-top:10px;">
                            <option value="">-- Aucun / Sélectionner plus tard --</option>
                            <?php foreach($charge_comptes as $cc): ?>
                                <option value="<?= htmlspecialchars($cc['id']) ?>" <?= ($user['manager_id']??'')==$cc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cc['nom_complet']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="role-hint" style="margin-top:10px;"><i class="fa-solid fa-circle-info"></i> Optionnel. Permet au Chargé de Compte de filtrer les interventions de son équipe.</span>
                    </div>

                    <div style="border-top:1px solid rgba(58,1,92,.08); padding-top:24px; display:flex; justify-content:flex-end; gap:16px;">
                        <a href="users.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn" style="padding-left:32px; padding-right:32px;"><i class="fa-solid fa-save"></i> Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
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

        function toggleManagerField() {
            var role = document.getElementById('roleSelect').value;
            var managerGroup = document.getElementById('managerGroup');
            if (role === 'tech') {
                managerGroup.style.display = 'block';
            } else {
                managerGroup.style.display = 'none';
            }
        }
        toggleManagerField();
    </script>
</body>
</html>
