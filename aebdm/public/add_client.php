<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte'])) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user'];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $email = $_POST['email'] ?? '';
    $region = $_POST['region'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $code_client = $_POST['code_client'] ?? '';
    
    try {
        $stmt = $pdo->prepare('INSERT INTO clients (nom, email, region, ville, code_client) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$nom, $email, $region, $ville, $code_client]);
        header('Location: clients.php?message=Client ajouté avec succès !');
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
    <title>Ajouter un client - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="page-header">
            <h2>Ajouter un client</h2>
            <a href="clients.php" class="btn-edit"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
        <?php if ($message): ?>
            <div class="error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post" class="modern-form">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nom</label>
                    <input type="text" name="nom" required placeholder="Nom du client">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" required placeholder="Email du client">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Région</label>
                    <input type="text" name="region" required placeholder="Région du client">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-city"></i> Ville</label>
                    <input type="text" name="ville" required placeholder="Ville du client">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-barcode"></i> Code client</label>
                    <input type="text" name="code_client" required placeholder="Code client unique">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Ajouter le client</button>
                <a href="clients.php" class="btn-edit"><i class="fas fa-times"></i> Annuler</a>
            </div>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="assets/script.js"></script>
</body>
</html> 