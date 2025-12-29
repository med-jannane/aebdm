<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte', 'magasinier'])) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$message = '';

if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare('DELETE FROM produits WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: produits.php?message=Produit supprimé avec succès');
        exit;
    } catch (PDOException $e) {
        $message = 'Erreur : ' . $e->getMessage();
    }
}

// Récupérer les infos du produit pour confirmation
$produit_data = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT nom, reference, quantite_stock FROM produits WHERE id = ?');
    $stmt->execute([$id]);
    $produit_data = $stmt->fetch();
}

if (!$produit_data) {
    header('Location: produits.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un produit - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Supprimer un produit</h2>
    <?php if ($message): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <p>Êtes-vous sûr de vouloir supprimer le produit <strong><?= htmlspecialchars($produit_data['nom']) ?></strong> ?</p>
    <p><strong>Référence :</strong> <?= htmlspecialchars($produit_data['reference']) ?></p>
    <p><strong>Quantité en stock :</strong> <?= htmlspecialchars($produit_data['quantite_stock']) ?></p>
    
    <form method="post">
        <button type="submit" style="background-color: red;">Confirmer la suppression</button>
        <a href="produits.php">Annuler</a>
    </form>
</body>
</html> 