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
        $stmt = $pdo->prepare('DELETE FROM contrats WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: contrats.php?message=Contrat supprimé avec succès');
        exit;
    } catch (PDOException $e) {
        $message = 'Erreur : ' . $e->getMessage();
    }
}

// Récupérer les infos du contrat pour confirmation
$contrat_data = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT nom, code_contrat FROM contrats WHERE id = ?');
    $stmt->execute([$id]);
    $contrat_data = $stmt->fetch();
}

if (!$contrat_data) {
    header('Location: contrats.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un contrat - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Supprimer un contrat</h2>
    <?php if ($message): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <p>Êtes-vous sûr de vouloir supprimer le contrat <strong><?= htmlspecialchars($contrat_data['nom']) ?></strong> (<?= htmlspecialchars($contrat_data['code_contrat']) ?>) ?</p>
    
    <form method="post">
        <button type="submit" style="background-color: red;">Confirmer la suppression</button>
        <a href="contrats.php">Annuler</a>
    </form>
</body>
</html> 