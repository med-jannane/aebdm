<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role('admin');

$error = "";
$success = "";

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate_request()) {
    $id_to_delete = trim((string)($_POST['delete_user_id'] ?? ''));
    if ($id_to_delete !== '' && $id_to_delete != $_SESSION['user_id']) {
        sqlsrv_query($conn, "DELETE FROM Users WHERE id = ?", [$id_to_delete]);
        $success = "Utilisateur supprimé.";
    } else {
        $error = "Vous ne pouvez pas vous supprimer vous-même.";
    }
}

// Message URL
if (isset($_GET['msg']) && $_GET['msg'] == 'created') {
    $success = "Utilisateur ajouté.";
}

$users = query("SELECT * FROM Users ORDER BY role, nom");

$pageTitle = "Gestion des Utilisateurs";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .table-wrap {
            background: var(--surface); border-radius: var(--r-md); padding: 0; overflow: hidden;
            box-shadow: 0 4px 15px rgba(24,8,44,.05); border: 1px solid rgba(58,1,92,.08);
        }
        .table-wrap table { margin: 0; }
        .table-wrap th { background: var(--surface-2); border-bottom: 2px solid rgba(58,1,92,.08); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; color: var(--text-muted); }
        .table-wrap td { vertical-align: middle; padding: 16px; border-bottom: 1px solid rgba(58,1,92,.04); }
        .table-wrap tr:last-child td { border-bottom: none; }
        
        .role-badge { 
            padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; 
            display: inline-flex; align-items: center; justify-content: center; min-width: 100px;
        }
        .role-admin { background: rgba(239, 68, 68, .1); color: var(--danger); border: 1px solid rgba(239, 68, 68, .2); }
        .role-commercial { background: rgba(58, 1, 92, .1); color: var(--dark-amethyst); border: 1px solid rgba(58, 1, 92, .2); }
        .role-accueil { background: rgba(155, 93, 229, .1); color: var(--accent); border: 1px solid rgba(155, 93, 229, .2); }
        .role-tac { background: rgba(71, 85, 105, .1); color: var(--text-muted); border: 1px solid rgba(71, 85, 105, .2); }
        .role-dispatch { background: rgba(245, 158, 11, .1); color: var(--warning); border: 1px solid rgba(245, 158, 11, .2); }
        .role-tech { background: rgba(16, 185, 129, .1); color: var(--success); border: 1px solid rgba(16, 185, 129, .2); }
        .role-default { background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border); }
        
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--dark-amethyst-3), var(--primary)); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 4px 10px rgba(155,93,229,.3); }
        
        .action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: all .2s; cursor: pointer; border: none; }
        .action-edit { background: rgba(58, 1, 92, .05); color: var(--dark-amethyst); }
        .action-edit:hover { background: rgba(58, 1, 92, .1); transform: translateY(-2px); }
        .action-delete { background: rgba(239, 68, 68, .05); color: var(--danger); }
        .action-delete:hover { background: rgba(239, 68, 68, .1); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(239,68,68,.2); }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-users-gear text-accent" style="margin-right:8px;"></i>Gestion Utilisateurs</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Comptes et Rôles d'accès</span>
                </div>
            </div>
            <a href="user_create.php" class="btn"><i class="fa-solid fa-user-plus"></i> Ajouter Utilisateur</a>
        </header>

        <div class="page-content">

            <?php if($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div class="table-wrap">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Identifiant de connexion</th>
                                <th>Rôle / Accès</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(sqlsrv_has_rows($users)): ?>
                                <?php while($u = sqlsrv_fetch_array($users, SQLSRV_FETCH_ASSOC)): 
                                    $roleClasses = [
                                        'admin' => 'role-admin',
                                        'commercial' => 'role-commercial',
                                        'accueil' => 'role-accueil',
                                        'tac' => 'role-tac',
                                        'dispatch' => 'role-dispatch',
                                        'tech' => 'role-tech'
                                    ];
                                    $rc = $roleClasses[$u['role']] ?? 'role-default';
                                    $initials = strtoupper(substr($u['nom_complet'], 0, 1));
                                    if (strpos($u['nom_complet'], ' ') !== false) {
                                        $parts = explode(' ', $u['nom_complet']);
                                        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <div class="user-avatar"><?= $initials ?></div>
                                            <div style="font-weight:700; color:var(--dark-amethyst-3); font-size:1.05rem;"><?= htmlspecialchars($u['nom_complet']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-family:monospace; background:rgba(0,0,0,.03); padding:4px 8px; border-radius:4px; display:inline-block; border:1px solid rgba(0,0,0,.05);"><i class="fa-solid fa-fingerprint text-muted" style="margin-right:4px;"></i> <?= htmlspecialchars($u['nom']) ?></div>
                                    </td>
                                    <td>
                                        <span class="role-badge <?= $rc ?>"><?= $u['role'] ?></span>
                                    </td>
                                    <td class="text-right">
                                        <div style="display:inline-flex; gap:8px;">
                                            <a href="user_edit.php?id=<?= $u['id'] ?>" class="action-btn action-edit" title="Modifier le profil"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="delete_user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                                    <button type="submit" class="action-btn action-delete" onclick="return confirm('Confirmer la suppression irréversible de cet utilisateur ?');" title="Supprimer le compte"><i class="fa-solid fa-trash-can"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <button class="action-btn" style="background:var(--surface-2); color:#ccc; cursor:not-allowed;" title="Vous ne pouvez pas supprimer votre propre compte" disabled><i class="fa-solid fa-trash-can"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted" style="padding:40px;">Aucun utilisateur trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
    </script>
</body>
</html>
