<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte', 'magasinier'])) {
    header('Location: dashboard.php');
    exit;
}
$user = $_SESSION['user'];

// Récupérer la liste des produits avec filtrage par rôle
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

$sql = 'SELECT * FROM produits';
if (!empty($where_conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
}
$sql .= ' ORDER BY nom';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Gestion des produits</h2>
            <?php if (in_array($user['role'], ['magasinier', 'directeur', 'charge_compte'])): ?>
                <a href="add_produit.php" class="btn-add">Ajouter un produit</a>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="search-filters">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher un produit..." class="search-input">
            </div>
            <div class="filters">
                <select id="stockFilter" class="filter-select">
                    <option value="">Tous les stocks</option>
                    <option value="ok">Stock OK (>10)</option>
                    <option value="warning">Stock faible (1-10)</option>
                    <option value="empty">Stock vide (0)</option>
                </select>
                <select id="referenceFilter" class="filter-select">
                    <option value="">Toutes les références</option>
                    <?php
                    $references = array_unique(array_column($produits, 'reference'));
                    foreach ($references as $reference):
                        if ($reference):
                    ?>
                        <option value="<?= htmlspecialchars($reference) ?>"><?= htmlspecialchars($reference) ?></option>
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
                        <th>Référence</th>
                        <th>Quantité</th>
                        <th>Prix achat</th>
                        <th>Prix vente</th>
                        <th>Marge</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($produits as $produit): ?>
                        <tr data-stock="<?= $produit['quantite_stock'] > 10 ? 'ok' : ($produit['quantite_stock'] > 0 ? 'warning' : 'empty') ?>" data-reference="<?= htmlspecialchars($produit['reference']) ?>">
                            <td>
                                <?php if ($produit['photo']): ?>
                                    <img src="../<?= htmlspecialchars($produit['photo']) ?>" width="50" style="border-radius:5px;">
                                <?php else: ?>
                                    <img src="assets/default.png" width="50" style="border-radius:5px;">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($produit['nom']) ?></td>
                            <td><strong><?= htmlspecialchars($produit['reference']) ?></strong></td>
                            <td>
                                <span class="stock-<?= $produit['quantite_stock'] > 10 ? 'ok' : ($produit['quantite_stock'] > 0 ? 'warning' : 'empty') ?>">
                                    <?= htmlspecialchars($produit['quantite_stock']) ?>
                                </span>
                            </td>
                            <td><?= number_format($produit['prix_achat'], 2) ?> MAD</td>
                            <td><?= number_format($produit['prix_vente'], 2) ?> MAD</td>
                            <td><?= number_format($produit['prix_vente'] - $produit['prix_achat'], 2) ?> MAD</td>
                            <td class="actions">
                                <?php if (in_array($user['role'], ['magasinier'])): ?>
                                    <a href="edit_produit.php?id=<?= $produit['id'] ?>" class="btn-edit">Modifier</a>
                                    <a href="delete_produit.php?id=<?= $produit['id'] ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr ?')">Supprimer</a>
                                <?php endif; ?>
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