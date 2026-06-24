<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['admin', 'directeur']);

// 1. Statistiques Globales
// Tickets par Statut
$sqlStatus = "SELECT ETAT as statut, COUNT(*) as count FROM TICKET GROUP BY ETAT";
$stmtStatus = query($sqlStatus);
$dataStatus = [];
$labelsStatus = [];
while ($row = sqlsrv_fetch_array($stmtStatus, SQLSRV_FETCH_ASSOC)) {
    $labelsStatus[] = strtoupper($row['statut']);
    $dataStatus[] = $row['count'];
}

// Tickets par Type
$sqlType = "SELECT PRIORITE as type_probleme, COUNT(*) as count FROM TICKET GROUP BY PRIORITE";
$stmtType = query($sqlType);
$dataType = [];
$labelsType = [];
while ($row = sqlsrv_fetch_array($stmtType, SQLSRV_FETCH_ASSOC)) {
    $labelsType[] = $row['type_probleme'] ?: 'NON DEFINI';
    $dataType[] = $row['count'];
}

// Interventions par Tech
$sqlTech = "SELECT Users.nom_complet, COUNT(*) as count FROM Interventions JOIN Users ON Interventions.tech_id = Users.id GROUP BY Users.nom_complet";
$stmtTech = query($sqlTech);
$dataTech = [];
$labelsTech = [];
while ($row = sqlsrv_fetch_array($stmtTech, SQLSRV_FETCH_ASSOC)) {
    $labelsTech[] = $row['nom_complet'];
    $dataTech[] = $row['count'];
}

// Stats Sites par Région (Faux Map data)
$sqlRegion = "SELECT Ville as region, COUNT(*) as count FROM SAV_Sites WHERE Ville IS NOT NULL AND Ville != '' GROUP BY Ville ORDER BY count DESC";
$stmtRegion = query($sqlRegion);
$dataRegion = [];
$labelsRegion = [];
while ($row = sqlsrv_fetch_array($stmtRegion, SQLSRV_FETCH_ASSOC)) {
    $labelsRegion[] = strtoupper($row['region']);
    $dataRegion[] = $row['count'];
}

// Chiffre d'affaire (Somme Contrats)
$sqlCA = "SELECT SUM(Montant_Contrat) as total FROM CONTRAT WHERE ETAT = 'actif'";
$stmtCA = query($sqlCA);
$rowCA = sqlsrv_fetch_array($stmtCA, SQLSRV_FETCH_ASSOC);
$caTotal = $rowCA['total'] ?? 0;

// Tendance Tickets (6 derniers mois)
$sqlTrend = "SELECT YEAR([DATE]) as y, MONTH([DATE]) as m, COUNT(*) as count
             FROM TICKET
             WHERE [DATE] >= DATEADD(MONTH, -5, CAST(GETDATE() as DATE))
             GROUP BY YEAR([DATE]), MONTH([DATE])
             ORDER BY YEAR([DATE]), MONTH([DATE])";
$stmtTrend = query($sqlTrend);
$trendMap = [];
while ($row = sqlsrv_fetch_array($stmtTrend, SQLSRV_FETCH_ASSOC)) {
    $key = $row['y'] . '-' . $row['m'];
    $trendMap[$key] = (int)$row['count'];
}

$monthNames = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aou', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
$trendLabels = [];
$trendData = [];
$cursor = new DateTime('first day of this month');
$cursor->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $y = (int)$cursor->format('Y');
    $m = (int)$cursor->format('n');
    $trendLabels[] = ($monthNames[$m] ?? $cursor->format('M')) . ' ' . substr((string)$y, -2);
    $trendData[] = $trendMap[$y . '-' . $m] ?? 0;
    $cursor->modify('+1 month');
}

$pageTitle = "Dashboard Admin - Vue Globale";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card { background: var(--surface); border-radius: var(--r-md); padding: 24px; display: flex; align-items: center; justify-content: space-between; border: 1px solid rgba(58,1,92,.08); box-shadow: 0 4px 15px rgba(24,8,44,.05); transition: transform .2s; position:relative; overflow:hidden; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(155,93,229,.15); }
        .kpi-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .kpi-info { text-align: right; z-index: 2;}
        .kpi-value { font-size: 2rem; font-weight: 800; color: var(--dark-amethyst-3); margin-bottom: 4px; font-family: 'Rajdhani', sans-serif;}
        .kpi-label { font-size: 0.9rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }

        .module-card { background: var(--surface); border-radius: var(--r-md); padding: 24px; text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; border: 1px solid rgba(58,1,92,.08); box-shadow: 0 4px 10px rgba(24,8,44,.05); transition: all .3s cubic-bezier(0.25, 0.8, 0.25, 1); position:relative; overflow:hidden;}
        .module-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: var(--primary); transform: scaleX(0); transition: transform .3s; transform-origin: left; }
        .module-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(58,1,92,.1); border-color:transparent;}
        .module-card:hover::before { transform: scaleX(1); }
        .module-icon { font-size: 2.5rem; color: var(--primary); transition: transform .3s; }
        .module-card:hover .module-icon { transform: scale(1.1); color: var(--accent); }
        .module-title { font-size: 1.2rem; font-weight: 700; color: var(--dark-amethyst-3); margin:0;}
        .module-desc { font-size: 0.85rem; color: var(--text-muted); font-weight:600; text-align:center;}

        .chart-wrap { background: var(--surface); border-radius: var(--r-md); padding: 24px; border: 1px solid rgba(58,1,92,.08); box-shadow: 0 4px 15px rgba(24,8,44,.05); display:flex; flex-direction:column; align-items:center;}
        .chart-header { width:100%; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(58,1,92,.05); padding-bottom:10px;}
        .chart-title { font-size: 1.1rem; color: var(--dark-amethyst-3); font-weight: 700; display:flex; align-items:center; gap:8px;}

        h2.section-heading { font-size: 1.4rem; color: var(--dark-amethyst-3); margin: 32px 0 24px; display:flex; align-items:center; gap:12px; font-weight:800; }
        h2.section-heading::after { content:''; flex:1; height:2px; background:linear-gradient(90deg, rgba(155,93,229,.2) 0%, transparent 100%); margin-left:16px;}
        .hdr-main { display:flex; align-items:center; gap:20px; }
        .hdr-col { display:flex; flex-direction:column; }
        .title-no-margin { margin:0; }
        .subtitle-sm { font-size:.9rem; color:var(--text-muted); font-weight:600; }
        .badge-header { font-size:1rem; padding:8px 16px; }
        .kpi-grid-admin { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:24px; }
        .kpi-bg-icon { position:absolute; right:-20px; bottom:-20px; font-size:8rem; opacity:0.03; z-index:0; }
        .kpi-bg-default { color:var(--dark-amethyst-3); }
        .kpi-bg-accent { color:var(--accent); }
        .kpi-bg-success { color:var(--success); }
        .kpi-ico-default { background:rgba(58,1,92,.1); color:var(--dark-amethyst); }
        .kpi-ico-accent { background:rgba(155,93,229,.1); color:var(--accent); }
        .kpi-ico-success { background:rgba(16,185,129,.1); color:var(--success); }
        .module-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:24px; margin-bottom:40px; }
        .module-ic-info { color:var(--info); }
        .module-ic-secondary { color:var(--secondary); }
        .module-ic-warning { color:var(--warning); }
        .module-ic-success { color:var(--success); }
        .chart-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:24px; margin-bottom:40px; }
        .chart-size-pie { width:100%; max-width:350px; aspect-ratio:1; }
        .chart-span-two { grid-column: span 2 / auto; }
        .chart-size-default { width:100%; height:300px; }
        .chart-size-line { width:100%; height:320px; }
        .page-content { gap:26px; }
        @media (max-width: 1100px) {
            .chart-grid { grid-template-columns: 1fr; }
            .chart-span-two { grid-column: auto; }
        }
        .export-wrap { margin-top:20px; text-align:center; padding:40px; background:linear-gradient(135deg, rgba(58,1,92,.05), rgba(155,93,229,.1)); border-radius:var(--r-md); border:1px solid rgba(155,93,229,.2); }
        .export-icon { font-size:3rem; color:var(--dark-amethyst-3); margin-bottom:16px; }
        .export-title { color:var(--dark-amethyst-3); margin-top:0; }
        .export-desc { color:var(--text-muted); margin-bottom:24px; max-width:600px; margin-left:auto; margin-right:auto; }
        .export-btn { padding:15px 32px; font-size:1.05rem; box-shadow:0 10px 20px rgba(155,93,229,.3); }
        .export-btn i { margin-right:8px; }
        .bottom-space { height:40px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div class="hdr-col">
                    <h1 class="title-no-margin"><i class="fa-solid fa-chart-pie text-accent"></i> Centre de Commandement</h1>
                    <span class="subtitle-sm">Vue Globale pour <?= strtoupper($_SESSION['role']) ?></span>
                </div>
            </div>
            <span class="badge badge-normal badge-header"><i class="fa-solid fa-user-tie"></i> Direction & Info</span>
        </header>

        <div class="page-content">

            <!-- KPI -->
            <div class="kpi-grid-admin">
                <div class="kpi-card">
                    <i class="fa-solid fa-chart-line kpi-bg-icon kpi-bg-default"></i>
                    <div class="kpi-icon kpi-ico-default"><i class="fa-solid fa-ticket"></i></div>
                    <div class="kpi-info">
                        <div class="kpi-value"><?= array_sum($dataStatus) ?></div>
                        <div class="kpi-label">Tickets Totaux</div>
                    </div>
                </div>
                <div class="kpi-card">
                    <i class="fa-solid fa-gears kpi-bg-icon kpi-bg-accent"></i>
                    <div class="kpi-icon kpi-ico-accent"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                    <div class="kpi-info">
                        <div class="kpi-value"><?= array_sum($dataTech) ?></div>
                        <div class="kpi-label">Interventions Tech</div>
                    </div>
                </div>
                <div class="kpi-card">
                    <i class="fa-solid fa-sack-dollar kpi-bg-icon kpi-bg-success"></i>
                    <div class="kpi-icon kpi-ico-success"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <div class="kpi-info">
                        <div class="kpi-value"><?= number_format($caTotal, 2, ',', ' ') ?></div>
                        <div class="kpi-label">CA Contrats Actifs (MAD)</div>
                    </div>
                </div>
            </div>

            <!-- Accès Modules (Directeur / Admin) -->
            <h2 class="section-heading"><i class="fa-solid fa-layer-group text-primary"></i> Accès Rapides (Modules)</h2>
            <div class="module-grid">
                <a href="../commercial/dashboard.php" class="module-card">
                    <i class="fa-solid fa-briefcase module-icon"></i>
                    <h3 class="module-title">Commercial</h3>
                    <span class="module-desc">Gestion Clients & Contrats</span>
                </a>
                <a href="../accueil/dashboard.php" class="module-card">
                    <i class="fa-solid fa-headset module-icon module-ic-info"></i>
                    <h3 class="module-title">Accueil</h3>
                    <span class="module-desc">Création Tickets & SAV</span>
                </a>
                <a href="../tac/dashboard.php" class="module-card">
                    <i class="fa-solid fa-user-doctor module-icon module-ic-secondary"></i>
                    <h3 class="module-title">TAC (N1)</h3>
                    <span class="module-desc">Diagnostic Technique</span>
                </a>
                <a href="../dispatch/dashboard.php" class="module-card">
                    <i class="fa-solid fa-calendar-days module-icon"></i>
                    <h3 class="module-title">Dispatch</h3>
                    <span class="module-desc">Planification & Techs</span>
                </a>
                <a href="../tech/dashboard.php" class="module-card">
                    <i class="fa-solid fa-truck-fast module-icon module-ic-success"></i>
                    <h3 class="module-title">Technicien</h3>
                    <span class="module-desc">Vue Terrain</span>
                </a>
            </div>

            <!-- Graphiques -->
            <h2 class="section-heading"><i class="fa-solid fa-chart-area text-accent"></i> Analyses Statistiques</h2>
            
            <div class="chart-grid">
                
                <!-- Chart 1 -->
                <div class="chart-wrap">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-chart-pie text-accent"></i> Répartition des Tickets</div>
                        <span class="badge badge-normal">Global</span>
                    </div>
                    <div class="chart-size-pie">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2 -->
                <div class="chart-wrap chart-span-two">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-chart-column text-primary"></i> Répartition par Type (Priorité/Problème)</div>
                        <span class="badge badge-normal">Alerte</span>
                    </div>
                    <div class="chart-size-default">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>

                <!-- Chart 3 (Nouveau : Villes/Map Stats) -->
                <div class="chart-wrap">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-map-location-dot text-success"></i> Sites par Région / Ville</div>
                        <span class="badge badge-normal">Géographie</span>
                    </div>
                    <div class="chart-size-default">
                        <canvas id="regionChart"></canvas>
                    </div>
                </div>

                <!-- Chart 4 -->
                <div class="chart-wrap">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-list-check text-warning"></i> Performance Interventions Tech</div>
                        <span class="badge badge-normal">Terrain</span>
                    </div>
                    <div class="chart-size-default">
                        <canvas id="techChart"></canvas>
                    </div>
                </div>

                <!-- Chart 5 (Courbe de tendance) -->
                <div class="chart-wrap chart-span-two">
                    <div class="chart-header">
                        <div class="chart-title"><i class="fa-solid fa-chart-line text-info"></i> Tendance Tickets (6 derniers mois)</div>
                        <span class="badge badge-normal">Courbe</span>
                    </div>
                    <div class="chart-size-line">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- Export -->
            <div class="export-wrap">
                <i class="fa-solid fa-file-pdf export-icon"></i>
                <h3 class="export-title">Générer des Rapports Complets</h3>
                <p class="export-desc">Accédez aux exports CSV et PDF détaillés pour les analyses financières et rapports de performance d'équipe.</p>
                 <a href="statistics.php" class="btn export-btn">
                     <i class="fa-solid fa-download"></i> Espace Statistiques & Export
                 </a>
            </div>
            
            <div class="bottom-space"></div>

        </div>
    </div>

    <!-- Script Charts -->
    <script>
        document.getElementById('menuBtn') && document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay') && document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });

        // Couleurs Premium
        Chart.defaults.color = '#718096';
        Chart.defaults.font.family = "'Inter', sans-serif";
        const palette = [
            '#9b5de5', // accent
            '#f15bb5', 
            '#fee440', 
            '#00bbf9', 
            '#00f5d4',
            '#3a015c', // dark-amethyst
            '#2196F3',
            '#10b981'
        ];

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: {weight:'600'} } }
            }
        };

        // 1. Graphique Statut (Doughnut)
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labelsStatus) ?>,
                datasets: [{
                    data: <?= json_encode($dataStatus) ?>,
                    backgroundColor: palette,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                ...commonOptions,
                cutout: '65%',
                plugins: {
                    ...commonOptions.plugins,
                    legend: { position: 'right' }
                }
            }
        });

        // 2. Graphique Type (Bar)
        new Chart(document.getElementById('typeChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($labelsType) ?>,
                datasets: [{
                    label: 'Nombre de demandes',
                    data: <?= json_encode($dataType) ?>,
                    backgroundColor: 'rgba(58, 1, 92, 0.8)',
                    borderColor: 'var(--primary)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 3. Graphique Régions (Polar Area ou Bar)
        new Chart(document.getElementById('regionChart'), {
            type: 'polarArea',
            data: {
                labels: <?= json_encode($labelsRegion) ?>,
                datasets: [{
                    label: 'Nombre de sites',
                    data: <?= json_encode($dataRegion) ?>,
                    backgroundColor: [
                        'rgba(155, 93, 229, 0.7)',
                        'rgba(241, 91, 181, 0.7)',
                        'rgba(0, 187, 249, 0.7)',
                        'rgba(0, 245, 212, 0.7)',
                        'rgba(254, 228, 64, 0.7)',
                        'rgba(58, 1, 92, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                ...commonOptions,
                scales: { r: { ticks: { display: false } } }
            }
        });

        // 4. Graphique Tech (Bar Horizontal)
        new Chart(document.getElementById('techChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($labelsTech) ?>,
                datasets: [{
                    label: 'Interventions Réalisées',
                    data: <?= json_encode($dataTech) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderRadius: 6
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    y: { grid: { display: false } }
                }
            }
        });

        // 5. Tendance 6 mois (Line)
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'Tickets crees',
                    data: <?= json_encode($trendData) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.14)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#2563eb'
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>
