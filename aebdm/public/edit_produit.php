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
$produit_data = null;

// Récupérer les données du produit
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM produits WHERE id = ?');
    $stmt->execute([$id]);
    $produit_data = $stmt->fetch();
}

if (!$produit_data) {
    header('Location: produits.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $reference = $_POST['reference'] ?? '';
    $quantite_stock = $_POST['quantite_stock'] ?? 0;
    $prix_achat = $_POST['prix_achat'] ?? 0;
    $prix_vente = $_POST['prix_vente'] ?? 0;
    
    // Upload photo si nouvelle
    $photo = $produit_data['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = '../uploads/produits/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
            $photo = 'uploads/produits/' . $file_name;
        }
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE produits SET nom = ?, reference = ?, quantite_stock = ?, prix_achat = ?, prix_vente = ?, photo = ? WHERE id = ?');
        $stmt->execute([$nom, $reference, $quantite_stock, $prix_achat, $prix_vente, $photo, $id]);
        $message = 'Produit modifié avec succès !';
        
        // Mettre à jour les données affichées
        $produit_data = array_merge($produit_data, $_POST);
        $produit_data['photo'] = $photo;
    } catch (PDOException $e) {
        $message = 'Erreur : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un produit - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Modifier un produit</h2>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <label>Nom : <input type="text" name="nom" value="<?= htmlspecialchars($produit_data['nom']) ?>" required></label><br>
        <label>Référence : <input type="text" name="reference" value="<?= htmlspecialchars($produit_data['reference']) ?>" required></label><br>
        <label>Quantité en stock : <input type="number" name="quantite_stock" min="0" value="<?= htmlspecialchars($produit_data['quantite_stock']) ?>" required></label><br>
        <label>MAD Prix d'achat : <input type="number" name="prix_achat" min="0" step="0.01" value="<?= htmlspecialchars($produit_data['prix_achat']) ?>" required></label><br>
        <label>MAD Prix de vente : <input type="number" name="prix_vente" min="0" step="0.01" value="<?= htmlspecialchars($produit_data['prix_vente']) ?>" required></label><br>
        
        <?php if ($produit_data['photo']): ?>
            <label>Photo actuelle : <img src="../<?= htmlspecialchars($produit_data['photo']) ?>" width="100"></label><br>
        <?php endif; ?>
        <label>Nouvelle photo : <input type="file" name="photo" accept="image/*"></label><br>
        
        <button type="submit">Modifier</button>
        <a href="produits.php">Retour</a>
    </form>
</body>
</html> 