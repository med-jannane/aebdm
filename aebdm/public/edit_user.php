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
$user_data = null;

// Récupérer les données de l'utilisateur
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user_data = $stmt->fetch();
}

if (!$user_data) {
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $region = $_POST['region'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Upload photo si nouvelle
    $photo_profil = $user_data['photo_profil'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = '../uploads/users/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
            $photo_profil = 'uploads/users/' . $file_name;
        }
    }
    
    try {
        $stmt = $pdo->prepare('UPDATE users SET nom = ?, prenom = ?, email = ?, numero = ?, photo_profil = ?, region = ?, ville = ?, role = ? WHERE id = ?');
        $stmt->execute([$nom, $prenom, $email, $numero, $photo_profil, $region, $ville, $role, $id]);
        $message = 'Utilisateur modifié avec succès !';
        
        // Mettre à jour les données affichées
        $user_data = array_merge($user_data, $_POST);
        $user_data['photo_profil'] = $photo_profil;
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
    <title>Modifier un utilisateur - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h2>Modifier un utilisateur</h2>
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <label>Nom : <input type="text" name="nom" value="<?= htmlspecialchars($user_data['nom']) ?>" required></label><br>
        <label>Prénom : <input type="text" name="prenom" value="<?= htmlspecialchars($user_data['prenom']) ?>" required></label><br>
        <label>Email : <input type="email" name="email" value="<?= htmlspecialchars($user_data['email']) ?>" required></label><br>
        <label>Numéro : <input type="text" name="numero" value="<?= htmlspecialchars($user_data['numero']) ?>"></label><br>
        <label>Région : <input type="text" name="region" value="<?= htmlspecialchars($user_data['region']) ?>"></label><br>
        <label>Ville : <input type="text" name="ville" value="<?= htmlspecialchars($user_data['ville']) ?>"></label><br>
        <label>Rôle : 
            <select name="role" required>
                <option value="">Sélectionner</option>
                <option value="directeur" <?= $user_data['role'] === 'directeur' ? 'selected' : '' ?>>Directeur</option>
                <option value="charge_compte" <?= $user_data['role'] === 'charge_compte' ? 'selected' : '' ?>>Chargé de compte</option>
                <option value="ingenieur" <?= $user_data['role'] === 'ingenieur' ? 'selected' : '' ?>>Ingénieur</option>
                <option value="technicien" <?= $user_data['role'] === 'technicien' ? 'selected' : '' ?>>Technicien</option>
                <option value="magasinier" <?= $user_data['role'] === 'magasinier' ? 'selected' : '' ?>>Magasinier</option>
            </select>
        </label><br>
        <?php if ($user_data['photo_profil']): ?>
            <label>Photo actuelle : <img src="../<?= htmlspecialchars($user_data['photo_profil']) ?>" width="50"></label><br>
        <?php endif; ?>
        <label>Nouvelle photo : <input type="file" name="photo" accept="image/*"></label><br>
        <button type="submit">Modifier</button>
        <a href="users.php">Retour</a>
    </form>
</body>
</html> 