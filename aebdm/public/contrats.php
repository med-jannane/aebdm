<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte'])) {
    header('Location: dashboard.php');
    exit;
}
$user = $_SESSION['user'];

// Récupérer la liste des contrats avec les infos du client et filtrage par rôle
$where_conditions = [];
$params = [];

if ($user['role'] === 'charge_compte') {
    $where_conditions[] = "c.region = ?";
    $params[] = $user['region'];
}

if ($user['role'] === 'ingenieur') {
    $where_conditions[] = "c.region = ? AND c.ville = ?";
    $params[] = $user['region'];
    $params[] = $user['ville'];
}

if ($user['role'] === 'technicien') {
    $where_conditions[] = "c.region = ? AND c.ville = ?";
    $params[] = $user['region'];
    $params[] = $user['ville'];
}

$sql = '
    SELECT c.*, cl.nom as client_nom 
    FROM contrats c 
    LEFT JOIN clients cl ON c.code_client = cl.code_client 
';
if (!empty($where_conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
}
$sql .= ' ORDER BY c.nom';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contrats = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrats - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Gestion des contrats</h2>
            <a href="add_contrat.php" class="btn-add">Ajouter un contrat</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="search-filters">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher un contrat..." class="search-input">
            </div>
            <div class="filters">
                <select id="clientFilter" class="filter-select">
                    <option value="">Tous les clients</option>
                    <?php
                    $clients = array_unique(array_column($contrats, 'client_nom'));
                    foreach ($clients as $client):
                        if ($client):
                    ?>
                        <option value="<?= htmlspecialchars($client) ?>"><?= htmlspecialchars($client) ?></option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
                <select id="statusFilter" class="filter-select">
                    <option value="">Tous les statuts</option>
                    <option value="actif">Actif</option>
                    <option value="expire">Expiré</option>
                    <option value="en_cours">En cours</option>
                </select>
            </div>
        </div>
        
        <div class="table-container scroll-x">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code contrat</th>
                        <th>Nom</th>
                        <th>Client</th>
                        <th>Date début</th>
                        <th>Date fin</th>
                        <th>Statut</th>
                        <th>Fichiers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($contrats as $contrat): ?>
                        <?php 
                        $date_fin = new DateTime($contrat['date_fin']);
                        $aujourd_hui = new DateTime();
                        $status = $date_fin < $aujourd_hui ? 'expire' : 'actif';
                        ?>
                        <tr data-client="<?= htmlspecialchars($contrat['client_nom'] ?? '') ?>" data-status="<?= $status ?>">
                            <td><strong><?= htmlspecialchars($contrat['code_contrat']) ?></strong></td>
                            <td><?= htmlspecialchars($contrat['nom']) ?></td>
                            <td><?= htmlspecialchars($contrat['client_nom'] ?? 'Client inconnu') ?></td>
                            <td><?= htmlspecialchars($contrat['date_debut']) ?></td>
                            <td><?= htmlspecialchars($contrat['date_fin']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $status ?>">
                                    <?= $status === 'expire' ? 'Expiré' : 'Actif' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($contrat['photo']): ?>
                                    <a href="../<?= htmlspecialchars($contrat['photo']) ?>" target="_blank">Photo</a>
                                <?php endif; ?>
                                <?php if ($contrat['pdf']): ?>
                                    <a href="../<?= htmlspecialchars($contrat['pdf']) ?>" target="_blank">PDF</a>
                                <?php endif; ?>
                                <?php if (!$contrat['photo'] && !$contrat['pdf']): ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="edit_contrat.php?id=<?= $contrat['id'] ?>" class="btn-edit">Modifier</a>
                                <a href="delete_contrat.php?id=<?= $contrat['id'] ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr ?')">Supprimer</a>
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