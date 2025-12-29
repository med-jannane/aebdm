<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte'])) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

// Récupérer les clients selon le rôle
if (in_array($user['role'], ['ingenieur', 'technicien', 'charge_compte'])) {
    // Filtrer par région et ville pour ces rôles
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE region = ? AND ville = ? ORDER BY nom');
    $stmt->execute([$user['region'], $user['ville']]);
} else {
    // Tous les clients pour directeur
    $stmt = $pdo->query('SELECT * FROM clients ORDER BY nom');
}
$clients = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Gestion des Clients</h2>
            <a href="add_client.php" class="btn-add">Ajouter un client</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="search-filters">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher un client..." class="search-input">
            </div>
            <div class="filters">
                <select id="regionFilter" class="filter-select">
                    <option value="">Toutes les régions</option>
                    <?php
                    $regions = array_unique(array_column($clients, 'region'));
                    foreach ($regions as $region):
                        if ($region):
                    ?>
                        <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
                <select id="villeFilter" class="filter-select">
                    <option value="">Toutes les villes</option>
                    <?php
                    $villes = array_unique(array_column($clients, 'ville'));
                    foreach ($villes as $ville):
                        if ($ville):
                    ?>
                        <option value="<?= htmlspecialchars($ville) ?>"><?= htmlspecialchars($ville) ?></option>
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
                        <th>Code Client</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Région</th>
                        <th>Ville</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($clients as $client): ?>
                        <tr data-region="<?= htmlspecialchars($client['region']) ?>" data-ville="<?= htmlspecialchars($client['ville']) ?>">
                            <td><strong><?= htmlspecialchars($client['code_client']) ?></strong></td>
                            <td><?= htmlspecialchars($client['nom']) ?></td>
                            <td><?= htmlspecialchars($client['email']) ?></td>
                            <td><?= htmlspecialchars($client['region']) ?></td>
                            <td><?= htmlspecialchars($client['ville']) ?></td>
                            <td class="actions">
                                <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn-edit">Modifier</a>
                                <a href="delete_client.php?id=<?= $client['id'] ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?')">Supprimer</a>
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