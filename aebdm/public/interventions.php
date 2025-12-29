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

// Récupérer les interventions selon le rôle
if (in_array($user['role'], ['ingenieur', 'technicien', 'charge_compte'])) {
    // Filtrer par région et ville pour ces rôles
    $stmt = $pdo->prepare('
    SELECT i.*, 
    cl.nom as client_nom,
    c.nom as contrat_nom,
    u.prenom, u.nom as user_nom,
    i.produit_ajoute as produit_nom
    FROM interventions i 
    LEFT JOIN clients cl ON i.code_client = cl.code_client 
    LEFT JOIN contrats c ON i.code_contrat = c.code_contrat
    LEFT JOIN users u ON i.user_id = u.id
    WHERE cl.region = ? AND cl.ville = ?
    ORDER BY i.id DESC
    ');
    $stmt->execute([$user['region'], $user['ville']]);
} else {
    // Toutes les interventions pour directeur
    $stmt = $pdo->query('
    SELECT i.*, 
    cl.nom as client_nom,
    c.nom as contrat_nom,
    u.prenom, u.nom as user_nom,
    i.produit_ajoute as produit_nom
    FROM interventions i 
    LEFT JOIN clients cl ON i.code_client = cl.code_client 
    LEFT JOIN contrats c ON i.code_contrat = c.code_contrat
    LEFT JOIN users u ON i.user_id = u.id
    ORDER BY i.id DESC
    ');
}
$interventions = $stmt->fetchAll();

$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interventions - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Gestion des Interventions</h2>
            <?php if (in_array($user['role'], ['directeur', 'charge_compte', 'magasinier'])): ?>
                <a href="add_intervention.php" class="btn-add">Ajouter une intervention</a>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="search-filters">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher une intervention..." class="search-input">
            </div>
            <div class="filters">
                <select id="clientFilter" class="filter-select">
                    <option value="">Tous les clients</option>
                    <?php
                    $clients = array_unique(array_column($interventions, 'client_nom'));
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
                    <option value="en_cours">En cours</option>
                    <option value="terminee">Terminée</option>
                    <option value="annulee">Annulée</option>
                </select>
                <select id="userFilter" class="filter-select">
                    <option value="">Tous les utilisateurs</option>
                    <?php
                    $users = array_unique(array_map(function($intervention) {
                        return $intervention['user_nom'] . ' ' . $intervention['prenom'];
                    }, $interventions));
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
                        <th>Produit ajouté</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($interventions as $intervention): ?>
                        <tr data-client="<?= htmlspecialchars($intervention['client_nom'] ?? '') ?>" data-status="<?= htmlspecialchars($intervention['statut']) ?>" data-user="<?= htmlspecialchars($intervention['user_nom'] . ' ' . $intervention['prenom']) ?>">
                            <td><strong><?= htmlspecialchars($intervention['client_nom']) ?></strong></td>
                            <td><?= htmlspecialchars($intervention['contrat_nom']) ?></td>
                            <td><?= htmlspecialchars($intervention['details']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $intervention['statut'] ?>">
                                    <?= ucfirst($intervention['statut']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($intervention['user_nom']) ?></td>
                            <td><?= htmlspecialchars($intervention['produit_nom'] ?? '-') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($intervention['created_at'])) ?></td>
                            <td class="actions">
                                <?php if (in_array($user['role'], ['directeur', 'charge_compte', 'magasinier'])): ?>
                                    <a href="edit_intervention.php?id=<?= $intervention['id'] ?>" class="btn-edit">Modifier</a>
                                    <a href="delete_intervention.php?id=<?= $intervention['id'] ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette intervention ?')">Supprimer</a>
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