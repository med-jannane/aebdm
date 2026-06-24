<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role('admin');

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $nom_complet = $_POST['nom_complet'];

    $email = $_POST['email'] ?? null;
    $region = $_POST['region'] ?? null;

    if (empty($username) || empty($password) || empty($role) || empty($nom_complet)) {
        $error = "Tous les champs (avec *) sont obligatoires.";
    } else {
        // Vérifier si username existe déjà
        $check = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM Users WHERE nom = ?", [$username]), SQLSRV_FETCH_ASSOC);
        
        if ($check['c'] > 0) {
            $error = "Ce nom d'utilisateur (login) existe déjà.";
        } else {
            $manager_id = ($role === 'tech') ? ($_POST['manager_id'] ?? null) : null;
            if (empty($manager_id)) $manager_id = null;
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $new_id = uniqid('U-');
            $sql = "INSERT INTO Users (id, nom, mot_de_passe, role, nom_complet, manager_id, email, region) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = sqlsrv_query($conn, $sql, [$new_id, $username, $hashed_password, $role, $nom_complet, $manager_id, $email, $region]);
            
            if ($stmt) {
                header("Location: users.php?msg=created");
                exit;
            } else {
                    error_log('[ADMIN_USER_CREATE] ' . db_last_error_message());
                    $error = "Erreur lors de la création de l'utilisateur.";
            }
        }
    }
}

// Fetch chargés de compte for the dropdown
$managers = query("SELECT id, nom_complet FROM Users WHERE role = 'charge_de_compte' ORDER BY nom_complet ASC");
$charge_comptes = [];
while ($row = sqlsrv_fetch_array($managers, SQLSRV_FETCH_ASSOC)) {
    $charge_comptes[] = $row;
}

$pageTitle = "Nouvel Utilisateur";
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
                    <h1 style="margin:0;"><i class="fa-solid fa-user-plus text-accent" style="margin-right:8px;"></i>Création de Compte</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Ajouter un nouvel utilisateur au système</span>
                </div>
            </div>
            <a href="users.php" class="btn btn-secondary" style="border-radius:30px;"><i class="fa-solid fa-arrow-left"></i> Retour Liste</a>
        </header>

        <div class="page-content">

            <?php if($error): ?><div class="alert alert-error" style="max-width:800px; margin:0 auto 24px;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="form-section">
                <form method="POST">
                    
                    <div class="form-section-title"><i class="fa-solid fa-id-badge text-primary"></i> Identifiants et Profil</div>
                    
                    <div class="form-grid" style="margin-bottom:24px;">
                        <div class="form-group">
                            <label>Identifiant de connexion (Login) <span style="color:var(--danger);">*</span></label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-fingerprint input-icon"></i>
                                <input type="text" name="username" required class="form-control" style="padding-left:40px;" placeholder="ex: p.dupont">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nom Complet <span style="color:var(--danger);">*</span></label>
                            <div style="position:relative;">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" name="nom_complet" required class="form-control" style="padding-left:40px;" placeholder="ex: Pierre Dupont">
                            </div>
                        </div>
                    </div>

                    <div class="form-grid" style="margin-bottom:32px;">
                        <div class="form-group">
                            <label>Mot de passe <span style="color:var(--danger);">*</span></label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input type="password" name="password" required class="form-control" style="padding-left:40px;" placeholder="••••••••">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Rôle <span style="color:var(--danger);">*</span></label>
                            <div style="position:relative;">
                                <select name="role" id="roleSelect" required class="form-control" style="padding-left:40px; appearance:none;" onchange="toggleManagerField()">
                                    <option value="commercial">Commercial</option>
                                    <option value="accueil">Accueil (Hotline)</option>
                                    <option value="tac">TAC (Analyse technique)</option>
                                    <option value="dispatch">Dispatch (Planification)</option>
                                    <option value="tech">Technicien (Intervenant)</option>
                                    <option value="charge_de_compte">Chargé de Compte</option>
                                    <option value="directeur">Directeur</option>
                                    <option value="admin">Administrateur Système</option>
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
                                <input type="email" name="email" class="form-control" style="padding-left:40px;" placeholder="exemple@entreprise.com">
                            </div>
                            <span class="role-hint">Utile pour l'envoi de rapports et notifications</span>
                        </div>

                        <div class="form-group">
                            <label>Région Associée</label>
                            <div style="position:relative;">
                                <i class="fa-solid fa-location-crosshairs input-icon"></i>
                                <input type="text" name="region" class="form-control" style="padding-left:40px;" placeholder="Ex: Île-de-France, Nord...">
                            </div>
                            <span class="role-hint">Secteur géographique principal</span>
                        </div>
                    </div>

                    <div class="form-group" id="managerGroup" style="display: none; background:rgba(155,93,229,.05); border:1px solid rgba(155,93,229,.2); padding:20px; border-radius:var(--r-md); margin-bottom:24px;">
                        <label style="color:var(--dark-amethyst-3);"><i class="fa-solid fa-sitemap text-accent"></i> Supervisé par (Chargé de Compte)</label>
                        <select name="manager_id" class="form-control" style="margin-top:10px;">
                            <option value="">-- Aucun / Sélectionner plus tard --</option>
                            <?php foreach($charge_comptes as $cc): ?>
                                <option value="<?= htmlspecialchars($cc['id']) ?>"><?= htmlspecialchars($cc['nom_complet']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="role-hint" style="margin-top:10px;"><i class="fa-solid fa-circle-info"></i> Optionnel. Permet au Chargé de Compte de filtrer les interventions de son équipe.</span>
                    </div>

                    <div style="border-top:1px solid rgba(58,1,92,.08); padding-top:24px; display:flex; justify-content:flex-end; gap:16px;">
                        <a href="users.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn" style="padding-left:32px; padding-right:32px;"><i class="fa-solid fa-check"></i> Créer ce compte</button>
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
