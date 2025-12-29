<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte'])) {
    header('Location: dashboard.php');
    exit;
}
$user = $_SESSION['user'];

// Récupérer la liste des utilisateurs avec filtrage par rôle
$where_conditions = [];
$params = [];

if ($user['role'] === 'charge_compte') {
    $where_conditions[] = "region = ?";
    $params[] = $user['region'];
}

if ($user['role'] === 'ingenieur') {
    $where_conditions[] = "region = ? AND ville = ?";
    $params[] = $user['region'];
    $params[] = $user['ville'];
}

if ($user['role'] === 'technicien') {
    $where_conditions[] = "region = ? AND ville = ?";
    $params[] = $user['region'];
    $params[] = $user['ville'];
}

$sql = 'SELECT * FROM users';
if (!empty($where_conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
}
$sql .= ' ORDER BY nom, prenom';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Gestion des utilisateurs</h2>
            <a href="add_user.php" class="btn-add">Ajouter un utilisateur</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="search-filters">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher un utilisateur..." class="search-input">
            </div>
            <div class="filters">
                <select id="roleFilter" class="filter-select">
                    <option value="">Tous les rôles</option>
                    <option value="directeur">Directeur</option>
                    <option value="charge_compte">Chargé de compte</option>
                    <option value="ingenieur">Ingénieur</option>
                    <option value="technicien">Technicien</option>
                    <option value="magasinier">Magasinier</option>
                </select>
                <select id="regionFilter" class="filter-select">
                    <option value="">Toutes les régions</option>
                    <?php
                    $regions = array_unique(array_column($users, 'region'));
                    foreach ($regions as $region):
                        if ($region):
                    ?>
                        <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
            </div>
        </div>
        
        <div class="table-container scroll-x">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Région</th>
                        <th>Ville</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($users as $u): ?>
                        <tr data-role="<?= htmlspecialchars($u['role']) ?>" data-region="<?= htmlspecialchars($u['region']) ?>">
                            <td>
                                <?php if ($u['photo_profil']): ?>
                                    <img src="../<?= htmlspecialchars($u['photo_profil']) ?>" width="50" style="border-radius:25px;">
                                <?php else: ?>
                                    <img src="assets/default.png" width="50" style="border-radius:25px;">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['nom']) ?></td>
                            <td><?= htmlspecialchars($u['prenom']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="role-badge role-<?= $u['role'] ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                            <td><?= htmlspecialchars($u['region']) ?></td>
                            <td><?= htmlspecialchars($u['ville']) ?></td>
                            <td class="actions">
                                <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn-edit">Modifier</a>
                                <a href="delete_user.php?id=<?= $u['id'] ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr ?')">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/script.js"></script>
</body>
</html> 