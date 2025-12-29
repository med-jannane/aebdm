<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

$roles_allowed = ['directeur', 'charge_compte', 'magasinier', 'ingenieur', 'technicien'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], $roles_allowed)) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

// Récupérer les visites selon le rôle
if (in_array($user['role'], ['ingenieur', 'technicien', 'charge_compte'])) {
    // Filtrer par région et ville pour ces rôles
    $stmt = $pdo->prepare('
    SELECT v.*, 
    cl.nom as client_nom,
    c.nom as contrat_nom,
    u.prenom, u.nom as user_nom
    FROM visites_preventives v 
    LEFT JOIN clients cl ON v.code_client = cl.code_client 
    LEFT JOIN contrats c ON v.code_contrat = c.code_contrat
    LEFT JOIN users u ON v.user_id = u.id
    WHERE cl.region = ? AND cl.ville = ?
    ORDER BY v.id DESC
    ');
    $stmt->execute([$user['region'], $user['ville']]);
} else {
    // Toutes les visites pour directeur
    $stmt = $pdo->query('
    SELECT v.*, 
    cl.nom as client_nom,
    c.nom as contrat_nom,
    u.prenom, u.nom as user_nom
    FROM visites_preventives v 
    LEFT JOIN clients cl ON v.code_client = cl.code_client 
    LEFT JOIN contrats c ON v.code_contrat = c.code_contrat
    LEFT JOIN users u ON v.user_id = u.id
    ORDER BY v.id DESC
    ');
}
$visites = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visites Préventives - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Gestion des Visites Préventives</h2>
            <?php if (in_array($user['role'], ['directeur', 'charge_compte', 'magasinier'])): ?>
                <a href="add_visite.php" class="btn-add">Ajouter une visite</a>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="search-filters">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher une visite..." class="search-input">
            </div>
            <div class="filters">
                <select id="clientFilter" class="filter-select">
                    <option value="">Tous les clients</option>
                    <?php
                    $clients = array_unique(array_column($visites, 'client_nom'));
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
                    <option value="planifiee">Planifiée</option>
                    <option value="en_cours">En cours</option>
                    <option value="terminee">Terminée</option>
                    <option value="annulee">Annulée</option>
                </select>
                <select id="userFilter" class="filter-select">
                    <option value="">Tous les utilisateurs</option>
                    <?php
                    $users = array_unique(array_map(function($visite) {
                        return $visite['user_nom'] . ' ' . $visite['prenom'];
                    }, $visites));
                    foreach ($users as $user_name):
                        if ($user_name):
                    ?>
                        <option value="<?= htmlspecialchars($user_name) ?>"><?= htmlspecialchars($user_name) ?></option>
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
                        <th>Client</th>
                        <th>Contrat</th>
                        <th>Détails</th>
                        <th>Statut</th>
                        <th>Assigné à</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($visites as $visite): ?>
                        <tr data-client="<?= htmlspecialchars($visite['client_nom'] ?? '') ?>" data-status="<?= htmlspecialchars($visite['statut']) ?>" data-user="<?= htmlspecialchars($visite['user_nom'] . ' ' . $visite['prenom']) ?>">
                            <td><strong><?= htmlspecialchars($visite['client_nom']) ?></strong></td>
                            <td><?= htmlspecialchars($visite['contrat_nom']) ?></td>
                            <td><?= htmlspecialchars($visite['details']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $visite['statut'] ?>">
                                    <?= ucfirst($visite['statut']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($visite['user_nom']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($visite['created_at'])) ?></td>
                            <td class="actions">
                                <?php if (in_array($user['role'], ['directeur', 'charge_compte', 'magasinier'])): ?>
                                    <a href="edit_visite.php?id=<?= $visite['id'] ?>" class="btn-edit">Modifier</a>
                                    <a href="delete_visite.php?id=<?= $visite['id'] ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette visite ?')">Supprimer</a>
                                <?php endif; ?>
                                <!-- Pour ingénieur/technicien : emplacement case à cocher statut et ajout produit/PDF/photo (à faire) -->
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