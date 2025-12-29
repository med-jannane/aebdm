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
$client_data = null;

// Récupérer les données du client
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    $client_data = $stmt->fetch();
}

if (!$client_data) {
    header('Location: clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $email = $_POST['email'] ?? '';
    $region = $_POST['region'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $code_client = $_POST['code_client'] ?? '';
    
    try {
        $stmt = $pdo->prepare('UPDATE clients SET nom = ?, email = ?, region = ?, ville = ?, code_client = ? WHERE id = ?');
        $stmt->execute([$nom, $email, $region, $ville, $code_client, $id]);
        $message = 'Client modifié avec succès !';
        
        // Mettre à jour les données affichées
        $client_data = array_merge($client_data, $_POST);
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
    <title>Modifier un client - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Modifier un client</h2>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="post">
        <label>Nom : <input type="text" name="nom" value="<?= htmlspecialchars($client_data['nom']) ?>" required></label><br>
        <label>Email : <input type="email" name="email" value="<?= htmlspecialchars($client_data['email']) ?>" required></label><br>
        <label>Région : <input type="text" name="region" value="<?= htmlspecialchars($client_data['region']) ?>" required></label><br>
        <label>Ville : <input type="text" name="ville" value="<?= htmlspecialchars($client_data['ville']) ?>" required></label><br>
        <label>Code client : <input type="text" name="code_client" value="<?= htmlspecialchars($client_data['code_client']) ?>" required></label><br>
        <button type="submit">Modifier</button>
        <a href="clients.php">Retour</a>
    </form>
</body>
</html> 