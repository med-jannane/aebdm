<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['directeur', 'charge_compte'])) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$message = '';

if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare('DELETE FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: clients.php?message=Client supprimé avec succès');
        exit;
    } catch (PDOException $e) {
        $message = 'Erreur : ' . $e->getMessage();
    }
}

// Récupérer les infos du client pour confirmation
$client_data = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT nom, code_client FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    $client_data = $stmt->fetch();
}

if (!$client_data) {
    header('Location: clients.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un client - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Supprimer un client</h2>
    <?php if ($message): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <p>Êtes-vous sûr de vouloir supprimer le client <strong><?= htmlspecialchars($client_data['nom']) ?></strong> (<?= htmlspecialchars($client_data['code_client']) ?>) ?</p>
    
    <form method="post">
        <button type="submit" style="background-color: red;">Confirmer la suppression</button>
        <a href="clients.php">Annuler</a>
    </form>
</body>
</html> 