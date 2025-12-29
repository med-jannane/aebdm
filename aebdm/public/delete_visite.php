<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

$roles_allowed = ['directeur', 'charge_compte', 'magasinier', 'ingenieur', 'technicien'];
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], $roles_allowed)) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$message = '';

if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Supprimer d'abord les fichiers associés
        $stmt = $pdo->prepare('DELETE FROM fichiers_visite WHERE visite_id = ?');
        $stmt->execute([$id]);
        
        // Puis supprimer la visite
        $stmt = $pdo->prepare('DELETE FROM visites_preventives WHERE id = ?');
        $stmt->execute([$id]);
        
        header('Location: visites.php?message=Visite préventive supprimée avec succès');
        exit;
    } catch (PDOException $e) {
        $message = 'Erreur : ' . $e->getMessage();
    }
}

// Récupérer les infos de la visite pour confirmation
$visite_data = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT details, statut FROM visites_preventives WHERE id = ?');
    $stmt->execute([$id]);
    $visite_data = $stmt->fetch();
}

if (!$visite_data) {
    header('Location: visites.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer une visite préventive - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Supprimer une visite préventive</h2>
    <?php if ($message): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <p>Êtes-vous sûr de vouloir supprimer cette visite préventive ?</p>
    <p><strong>Détails :</strong> <?= htmlspecialchars($visite_data['details']) ?></p>
    <p><strong>Statut :</strong> <?= htmlspecialchars($visite_data['statut']) ?></p>
    
    <form method="post">
        <button type="submit" style="background-color: red;">Confirmer la suppression</button>
        <a href="visites.php">Annuler</a>
    </form>
</body>
</html> 