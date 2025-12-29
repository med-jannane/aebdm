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
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: users.php?message=Utilisateur supprimé avec succès');
        exit;
    } catch (PDOException $e) {
        $message = 'Erreur : ' . $e->getMessage();
    }
}

// Récupérer les infos de l'utilisateur pour confirmation
$user_data = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT nom, prenom FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user_data = $stmt->fetch();
}

if (!$user_data) {
    header('Location: users.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un utilisateur - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Supprimer un utilisateur</h2>
    <?php if ($message): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong><?= htmlspecialchars($user_data['prenom'] . ' ' . $user_data['nom']) ?></strong> ?</p>
    
    <form method="post">
        <button type="submit" style="background-color: red;">Confirmer la suppression</button>
        <a href="users.php">Annuler</a>
    </form>
</body>
</html> 