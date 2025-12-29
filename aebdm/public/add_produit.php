<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte', 'magasinier'])) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $reference = $_POST['reference'] ?? '';
    $quantite_stock = $_POST['quantite_stock'] ?? 0;
    $prix_achat = $_POST['prix_achat'] ?? 0;
    $prix_vente = $_POST['prix_vente'] ?? 0;
    // Upload photo
    $photo = '';
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
        $stmt = $pdo->prepare('INSERT INTO produits (nom, reference, quantite_stock, prix_achat, prix_vente, photo) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$nom, $reference, $quantite_stock, $prix_achat, $prix_vente, $photo]);
        header('Location: produits.php?message=Produit ajouté avec succès !');
        exit;
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
    <title>Ajouter un produit - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="page-header">
            <h2>Ajouter un produit</h2>
            <a href="produits.php" class="btn-edit"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
        <?php if ($message): ?>
            <div class="error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="modern-form">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-box"></i> Nom</label>
                    <input type="text" name="nom" required placeholder="Nom du produit">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> Référence</label>
                    <input type="text" name="reference" required placeholder="Référence du produit">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-cubes"></i> Quantité en stock</label>
                    <input type="number" name="quantite_stock" min="0" value="0" required placeholder="Quantité">
                </div>
                <div class="form-group">
                    <label>MAD Prix d'achat</label>
                    <input type="number" name="prix_achat" min="0" step="0.01" value="0" required placeholder="Prix d'achat MAD">
                </div>
                <div class="form-group">
                    <label>MAD Prix de vente</label>
                    <input type="number" name="prix_vente" min="0" step="0.01" value="0" required placeholder="Prix de vente MAD">
                </div>
                <div class="form-group full-width">
                    <label><i class="fas fa-image"></i> Photo</label>
                    <input type="file" name="photo" accept="image/*" class="file-input">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Ajouter le produit</button>
                <a href="produits.php" class="btn-edit"><i class="fas fa-times"></i> Annuler</a>
            </div>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="assets/script.js"></script>
</body>
</html> 