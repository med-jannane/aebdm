<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$user = $_SESSION['user'];

// Récupérer les statistiques avancées selon le rôle
$stats = [];
$charts = [];

if (in_array($user['role'], ['directeur', 'charge_compte'])) {
    // Statistiques complètes pour directeur et chargé de compte
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM users');
    $stats['users'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM clients');
    $stats['clients'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM contrats');
    $stats['contrats'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM interventions');
    $stats['interventions'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM visites_preventives');
    $stats['visites'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM produits');
    $stats['produits'] = $stmt->fetch()['total'];
    
    // Statistiques par statut
    $stmt = $pdo->query('SELECT statut, COUNT(*) as total FROM interventions GROUP BY statut');
    $charts['interventions_status'] = $stmt->fetchAll();
    
    $stmt = $pdo->query('SELECT statut, COUNT(*) as total FROM visites_preventives GROUP BY statut');
    $charts['visites_status'] = $stmt->fetchAll();
    
    // Top 5 produits par stock
    $stmt = $pdo->query('SELECT nom, quantite_stock FROM produits ORDER BY quantite_stock DESC LIMIT 5');
    $charts['top_products'] = $stmt->fetchAll();
    
    // Interventions par mois (derniers 6 mois)
    $stmt = $pdo->query('SELECT DATE_FORMAT(created_at, "%b") as mois, COUNT(*) as total FROM interventions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY mois ORDER BY mois');
    $charts['interventions_monthly'] = $stmt->fetchAll();
} else {
    // Statistiques pour les autres rôles
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM interventions WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $stats['interventions'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM visites_preventives WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $stats['visites'] = $stmt->fetch()['total'];
    
    // Statut interventions
    $stmt = $pdo->prepare('SELECT statut, COUNT(*) as total FROM interventions WHERE user_id = ? GROUP BY statut');
    $stmt->execute([$user['id']]);
    $charts['my_interventions_status'] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <span class="sidebar-logo-text">AEBDM</span>
            </div>
            <nav class="sidebar-menu">
                <a href="#" class="sidebar-link active"><i class="fas fa-home"></i> <span>Overview</span></a>
                <a href="#" class="sidebar-link"><i class="fas fa-exchange-alt"></i> <span>Transactions</span></a>
                <a href="#" class="sidebar-link"><i class="fas fa-users"></i> <span>Customers</span></a>
                <a href="#" class="sidebar-link"><i class="fas fa-chart-bar"></i> <span>Reports</span></a>
                <a href="#" class="sidebar-link"><i class="fas fa-cog"></i> <span>Settings</span></a>
                <a href="#" class="sidebar-link"><i class="fas fa-code"></i> <span>Developer</span></a>
            </nav>
            <div class="sidebar-bottom">
                <a href="logout.php" class="sidebar-link logout-link"><i class="fas fa-sign-out-alt"></i> <span>Log out</span></a>
            </div>
        </aside>
        <!-- Main content -->
        <main class="dashboard-main">
            <!-- Header -->
            <header class="dashboard-header">
                <h1>Dashboard</h1>
                <div class="dashboard-header-right">
                    <form class="dashboard-search">
                        <input type="text" placeholder="Search transactions, customers, subscriptions">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    <div class="dashboard-user">
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </header>
            <!-- Stat cards -->
            <section class="dashboard-cards">
                <div class="stat-card stat-mrr">
                    <div class="stat-label">Current MRR</div>
                    <div class="stat-value">$12.4k</div>
                </div>
                <div class="stat-card stat-customers">
                    <div class="stat-label">Current Customers</div>
                    <div class="stat-value">16,601</div>
                </div>
                <div class="stat-card stat-active">
                    <div class="stat-label">Active Customers</div>
                    <div class="stat-value">33%</div>
                </div>
                <div class="stat-card stat-churn">
                    <div class="stat-label">Churn Rate</div>
                    <div class="stat-value">2%</div>
                </div>
            </section>
            <!-- Main grid -->
            <section class="dashboard-grid">
                <div class="dashboard-widget trend-widget">
                    <div class="widget-title">Trend</div>
                    <div class="widget-chart-placeholder">[Graphique barres]</div>
                </div>
                <div class="dashboard-widget sales-widget">
                    <div class="widget-title">Sales</div>
                    <div class="widget-chart-placeholder">[Graphique circulaire]</div>
                </div>
                <div class="dashboard-widget transactions-widget">
                    <div class="widget-title">Transactions</div>
                    <div class="widget-list-placeholder">[Liste transactions]</div>
                </div>
                <div class="dashboard-widget tickets-widget">
                    <div class="widget-title">Support Tickets</div>
                    <div class="widget-list-placeholder">[Liste tickets support]</div>
                </div>
                <div class="dashboard-widget map-widget">
                    <div class="widget-title">Customer Demographic</div>
                    <div class="widget-map-placeholder">[Carte monde]</div>
                </div>
            </section>
        </main>
    </div>
</body>
</html> 