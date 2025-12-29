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
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $password = $_POST['password'] ?? '';
    $region = $_POST['region'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Upload photo
    $photo_profil = '';
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
        $stmt = $pdo->prepare('INSERT INTO users (nom, prenom, email, numero, password, photo_profil, region, ville, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->execute([$nom, $prenom, $email, $numero, $hashed_password, $photo_profil, $region, $ville, $role]);
        header('Location: users.php?message=Utilisateur ajouté avec succès !');
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
    <title>Ajouter un utilisateur - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h2>Ajouter un utilisateur</h2>
            <a href="users.php" class="btn-edit">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="error"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" class="modern-form">
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nom</label>
                    <input type="text" name="nom" required placeholder="Entrez le nom">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Prénom</label>
                    <input type="text" name="prenom" required placeholder="Entrez le prénom">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" required placeholder="Entrez l'email">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Numéro</label>
                    <input type="text" name="numero" placeholder="Entrez le numéro">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Mot de passe</label>
                    <input type="password" name="password" required placeholder="Entrez le mot de passe">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Région</label>
                    <input type="text" name="region" placeholder="Entrez la région">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-city"></i> Ville</label>
                    <input type="text" name="ville" placeholder="Entrez la ville">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Rôle</label>
                    <select name="role" required>
                        <option value="">Sélectionner un rôle</option>
                        <option value="directeur">Directeur</option>
                        <option value="charge_compte">Chargé de compte</option>
                        <option value="ingenieur">Ingénieur</option>
                        <option value="technicien">Technicien</option>
                        <option value="magasinier">Magasinier</option>
                    </select>
                </div>
                
                <div class="form-group full-width">
                    <label><i class="fas fa-camera"></i> Photo de profil</label>
                    <input type="file" name="photo" accept="image/*" class="file-input">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-add">
                    <i class="fas fa-plus"></i> Ajouter l'utilisateur
                </button>
                <a href="users.php" class="btn-edit">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/script.js"></script>
</body>
</html> 