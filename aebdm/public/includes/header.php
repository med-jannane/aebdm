<?php
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'AEBDM' ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-user-role="<?= htmlspecialchars($user['role']) ?>" data-user-region="<?= htmlspecialchars($user['region']) ?>" data-user-ville="<?= htmlspecialchars($user['ville']) ?>">
    
    <!-- Header principal -->
    <header>
        <div class="header-content">
            <div class="user-info">
                <span><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
                <?php
                    $photo = $user['photo_profil'] ?? 'assets/default.png';
                    if (strpos($photo, 'uploads/') === 0) {
                        $photo = '../' . $photo;
                    }
                ?>
                <img src="<?= htmlspecialchars($photo) ?>" alt="Photo de profil" width="40" style="border-radius:20px;">
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </header>
    
    <!-- Navigation principale -->
    <nav class="main-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="dashboard.php">AEBDM</a>
            </div>
            
            <div class="nav-menu">
                <?php if (in_array($user['role'], ['directeur', 'charge_compte'])): ?>
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Utilisateurs</span>
                    </a>
                    <a href="clients.php" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span>Clients</span>
                    </a>
                    <a href="contrats.php" class="nav-link">
                        <i class="fas fa-file-contract"></i>
                        <span>Contrats</span>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array($user['role'], ['directeur', 'charge_compte', 'magasinier', 'ingenieur', 'technicien'])): ?>
                    <a href="interventions.php" class="nav-link">
                        <i class="fas fa-tools"></i>
                        <span>Interventions</span>
                    </a>
                    <a href="visites.php" class="nav-link">
                        <i class="fas fa-search"></i>
                        <span>Visites</span>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array($user['role'], ['directeur', 'charge_compte', 'magasinier'])): ?>
                    <a href="produits.php" class="nav-link">
                        <i class="fas fa-box"></i>
                        <span>Produits</span>
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="nav-actions">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Container principal --> 