<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role('admin');

// Configuration des Objectifs Mensuels (A stocker en DB idealement)
$obj_tickets_mois = 100;
$obj_interventions_mois = 80;
$obj_ca_mois = 50000;

$mois_actuel = date('m');
$annee_actuelle = date('Y');

// --- 1. KPIs GLOBAUX TOUT TEMPS ---
$sqlTotalT = "SELECT COUNT(*) as total, 
                     SUM(CASE WHEN ETAT = 'cloture' THEN 1 ELSE 0 END) as clotures,
                     SUM(CASE WHEN ETAT != 'cloture' THEN 1 ELSE 0 END) as en_cours
              FROM TICKET";
$statsT = sqlsrv_fetch_array(query($sqlTotalT), SQLSRV_FETCH_ASSOC);

$sqlTotalI = "SELECT COUNT(*) as total,
                     SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as terminees
              FROM Interventions";
$statsI = sqlsrv_fetch_array(query($sqlTotalI), SQLSRV_FETCH_ASSOC);

$sqlTotalC = "SELECT COUNT(*) as total, SUM(montant_ht) as ca_total FROM Commandes";
$statsC = sqlsrv_fetch_array(query($sqlTotalC), SQLSRV_FETCH_ASSOC);
$caTotalG = $statsC['ca_total'] ?? 0;

// --- 2. CHIFFRES COMPLEMENTAIRES (Dispatch vs Tech) ---
$sqlCloturesTech = "SELECT COUNT(DISTINCT t.ID_TICKET) as tot_tech 
                    FROM TICKET t 
                    JOIN Interventions i ON t.ID_TICKET = i.ticket_id
                    WHERE t.ETAT = 'cloture' AND i.statut = 'termine'";
$totCloturesTech = sqlsrv_fetch_array(query($sqlCloturesTech))['tot_tech'] ?? 0;
$totCloturesDisp = max(0, $statsT['clotures'] - $totCloturesTech);

// --- 3. OBJECTIFS MENSUELS (Mois Courant) ---
$sqlMoisT = "SELECT COUNT(*) as count FROM TICKET WHERE MONTH(DATE) = ? AND YEAR(DATE) = ?";
$countMoisT = sqlsrv_fetch_array(query($sqlMoisT, [$mois_actuel, $annee_actuelle]))['count'] ?? 0;
$prog_t = min(100, round(($countMoisT / $obj_tickets_mois) * 100));

$sqlMoisI = "SELECT COUNT(*) as count FROM Interventions WHERE MONTH(date_planifiee) = ? AND YEAR(date_planifiee) = ?";
$countMoisI = sqlsrv_fetch_array(query($sqlMoisI, [$mois_actuel, $annee_actuelle]))['count'] ?? 0;
$prog_i = min(100, round(($countMoisI / $obj_interventions_mois) * 100));

$sqlMoisC = "SELECT SUM(montant_ht) as total FROM Commandes WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?";
$caMoisC = sqlsrv_fetch_array(query($sqlMoisC, [$mois_actuel, $annee_actuelle]))['total'] ?? 0;
$prog_c = min(100, round(($caMoisC / $obj_ca_mois) * 100));

// --- 4. DATA POUR GRAPHIQUES (CHART.JS) ---
// Courbe : Tickets des 14 derniers jours
$sqlCourbe = "SELECT CONVERT(date, DATE) as dt, COUNT(*) as nb 
              FROM TICKET 
              WHERE DATE >= DATEADD(day, -14, GETDATE())
              GROUP BY CONVERT(date, DATE)
              ORDER BY dt ASC";
$resCourbe = query($sqlCourbe);
$chartDates = [];
$chartCounts = [];
while($row = sqlsrv_fetch_array($resCourbe, SQLSRV_FETCH_ASSOC)) {
    $chartDates[] = $row['dt']->format('d/m');
    $chartCounts[] = $row['nb'];
}

// Repartition Statuts
$sqlStatuts = "SELECT ETAT as statut, COUNT(*) as nb FROM TICKET GROUP BY ETAT";
$resStatuts = query($sqlStatuts);
$statutLabels = [];
$statutCounts = [];
$statutColors = [];
while($row = sqlsrv_fetch_array($resStatuts, SQLSRV_FETCH_ASSOC)) {
    $statutLabels[] = ucfirst(str_replace('_', ' ', $row['statut']));
    $statutCounts[] = $row['nb'];
    // Couleurs selon statut
    if($row['statut'] == 'nouveau') $statutColors[] = '#e74c3c';
    elseif($row['statut'] == 'attente_dispatch') $statutColors[] = '#f1c40f';
    elseif($row['statut'] == 'assigne' || $row['statut'] == 'en_cours') $statutColors[] = '#3498db';
    elseif($row['statut'] == 'cloture') $statutColors[] = '#2ecc71';
    else $statutColors[] = '#95a5a6';
}

// --- 5. TABLEAUX DETAILLES ---
// Tickets au TAC (Nouveaux)
$sqlTicketsTAC = "SELECT t.ID_TICKET as id, t.OBJET as sujet, t.PRIORITE as priorite, t.DATE as cree_le, c.Nom as client_nom, DATEDIFF(day, t.DATE, GETDATE()) as age_jours
                  FROM TICKET t JOIN SAV_Clients c ON t.ID_CLIENT = c.ID_Client
                  WHERE t.ETAT = 'nouveau'
                  ORDER BY t.PRIORITE DESC, t.DATE ASC";
$ticketsTAC = query($sqlTicketsTAC);

// Tickets En Cours (partout ailleurs sauf cloturés)
$sqlTicketsEnCours = "SELECT t.ID_TICKET as id, t.OBJET as sujet, t.ETAT as statut, t.DATE as cree_le, c.Nom as client_nom, u.nom_complet as tech_nom
                      FROM TICKET t 
                      JOIN SAV_Clients c ON t.ID_CLIENT = c.ID_Client
                      LEFT JOIN Interventions i ON t.ID_TICKET = i.ticket_id
                      LEFT JOIN Users u ON i.tech_id = u.id
                      WHERE t.ETAT NOT IN ('cloture', 'resolu', 'nouveau')
                      ORDER BY t.DATE DESC";
$ticketsEnCours = query($sqlTicketsEnCours);

// Performance Tech
$sqlTechStats = "SELECT u.nom_complet, COUNT(i.id) as total_inter, SUM(CASE WHEN i.statut = 'termine' THEN 1 ELSE 0 END) as inter_terminees
                 FROM Users u LEFT JOIN Interventions i ON u.id = i.tech_id
                 WHERE u.role = 'tech' GROUP BY u.nom_complet ORDER BY total_inter DESC";
$techStats = query($sqlTechStats);

$pageTitle = "Statistiques Détaillées & Export (MAD)";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .progress-container { margin-top: 10px; background: #e2e8f0; border-radius: 10px; height: 10px; overflow: hidden; }
        .progress-bar { height: 100%; background: var(--primary); transition: width 0.5s ease; }
        .obj-label { display: flex; justify-content: space-between; font-size: 0.85em; font-weight: 600; color: var(--text-muted); margin-top: 5px; }
        .chart-box { background: var(--surface); padding: 20px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
        .table-container { max-height: 400px; overflow-y: auto; }
        .stats-grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stats-grid-split { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .stats-grid-two { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }

        @media (max-width: 992px) {
            .stats-grid-split,
            .stats-grid-two {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        }

        /* Correction Sidebar Admin */
        .sidebar ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
        .sidebar ul li a {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: rgba(255,255,255,.7);
            text-decoration: none; border-radius: 12px; font-weight: 600; font-size: .95rem;
            transition: all .3s cubic-bezier(.4,0,.2,1);
        }
        .sidebar ul li a i { width: 22px; text-align: center; font-size: 1.1rem; opacity: .8; transition: transform .3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background: rgba(255,255,255,.1); color: #fff; transform: translateX(6px);
        }
        .sidebar ul li a:hover i, .sidebar ul li a.active i { color: var(--accent-light); opacity: 1; transform: scale(1.1); }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1>Statistiques Détaillées & Reporting</h1>
            </div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour au tableau</a>
        </header>

        <!-- OBJECTIFS MENSUELS -->
        <h2><i class="fa-solid fa-bullseye"></i> Objectifs du Mois Courant</h2>
        <div class="stats-grid" style="margin-bottom: 30px;">
            <div class="card">
                <h3><i class="fa-solid fa-ticket" style="color:var(--primary);"></i> Tickets Créés</h3>
                <div class="stat-number" style="font-size:2rem;"><?php echo $countMoisT; ?> / <?php echo $obj_tickets_mois; ?></div>
                <div class="progress-container"><div class="progress-bar" style="width: <?php echo $prog_t; ?>%;"></div></div>
                <div class="obj-label"><span>0%</span><span><?php echo $prog_t; ?>% de l'objectif</span></div>
            </div>
            <div class="card">
                <h3><i class="fa-solid fa-screwdriver-wrench" style="color:var(--info);"></i> Interventions Planifiées</h3>
                <div class="stat-number" style="font-size:2rem;"><?php echo $countMoisI; ?> / <?php echo $obj_interventions_mois; ?></div>
                <div class="progress-container"><div class="progress-bar" style="background:var(--info); width: <?php echo $prog_i; ?>%;"></div></div>
                <div class="obj-label"><span>0%</span><span><?php echo $prog_i; ?>% de l'objectif</span></div>
            </div>
            <div class="card" style="border:1px solid var(--success);">
                <h3><i class="fa-solid fa-money-bill-wave" style="color:var(--success);"></i> CA Commandes (NAV)</h3>
                <div class="stat-number" style="font-size:2rem; color:var(--success);"><?php echo number_format($caMoisC, 2, ',', ' '); ?> / <?php echo number_format($obj_ca_mois, 0, ',', ' '); ?> MAD</div>
                <div class="progress-container"><div class="progress-bar" style="background:var(--success); width: <?php echo $prog_c; ?>%;"></div></div>
                <div class="obj-label"><span>0%</span><span><?php echo $prog_c; ?>% de l'objectif</span></div>
            </div>
        </div>

        <hr style="border-top:1px solid var(--border); margin: 30px 0;">

        <!-- 4 BLOCS KPI AVANCES -->
        <div class="stats-grid-4">
            <div class="card" style="text-align:center;">
                <h3 style="color:var(--text-muted); font-size:1em;">Tickets Historiques</h3>
                <div style="font-size:2.5rem; font-weight:bold; color:var(--primary);"><?php echo $statsT['total']; ?></div>
            </div>
            <div class="card" style="text-align:center;">
                <h3 style="color:var(--text-muted); font-size:1em;">Tickets En Cours</h3>
                <div style="font-size:2.5rem; font-weight:bold; color:var(--warning);"><?php echo $statsT['en_cours']; ?></div>
            </div>
            <div class="card" style="text-align:center;">
                <h3 style="color:var(--text-muted); font-size:1em;">Tickets Clôturés</h3>
                <div style="font-size:2.5rem; font-weight:bold; color:var(--success);"><?php echo $statsT['clotures']; ?></div>
                <div style="font-size:0.8em; color:var(--text-muted); margin-top:5px;">
                    <strong><?php echo $totCloturesDisp; ?></strong> par Dispatch | <strong><?php echo $totCloturesTech; ?></strong> par Techs
                </div>
            </div>
            <div class="card" style="text-align:center;">
                <h3 style="color:var(--text-muted); font-size:1em;">CA Historique</h3>
                <div style="font-size:2.3rem; font-weight:bold; color:var(--success);"><?php echo number_format($caTotalG, 2, ',', ' '); ?></div>
                <div style="font-size:0.9em; font-weight:bold;">MAD</div>
            </div>
        </div>

        <!-- GRAPHIQUES -->
        <div class="stats-grid-split">
            <div class="chart-box">
                <h3 style="margin-top:0;"><i class="fa-solid fa-chart-area"></i> Création de Tickets (14 derniers jours)</h3>
                <canvas id="lineChart" height="100"></canvas>
            </div>
            <div class="chart-box">
                <h3 style="margin-top:0;"><i class="fa-solid fa-chart-pie"></i> Répartition des Statuts Actuels</h3>
                <canvas id="pieChart" height="200"></canvas>
            </div>
        </div>

        <div class="stats-grid-two">
            <!-- TICKETS AU TAC -->
            <div class="card table-container">
                <h3 style="margin-top:0; color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> En Attente au TAC (Nouveaux)</h3>
                <table>
                    <thead><tr><th>ID</th><th>Client</th><th>Sujet</th><th>Ancienneté</th></tr></thead>
                    <tbody>
                        <?php while($t = sqlsrv_fetch_array($ticketsTAC, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td>#<?php echo $t['id']; ?></td>
                            <td><?php echo htmlspecialchars($t['client_nom']); ?></td>
                            <td style="font-size:0.9em;"><?php echo htmlspecialchars($t['sujet']); ?></td>
                            <td style="color:var(--danger); font-weight:bold;"><?php echo $t['age_jours']; ?> j</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- TICKETS EN COURS (DISPATCH/TECH) -->
            <div class="card table-container">
                <h3 style="margin-top:0; color:var(--warning);"><i class="fa-solid fa-bars-progress"></i> En Cours (Dispatch / Tech)</h3>
                <table>
                    <thead><tr><th>ID</th><th>Client</th><th>Statut</th><th>Tech Assigné</th></tr></thead>
                    <tbody>
                        <?php while($t = sqlsrv_fetch_array($ticketsEnCours, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td>#<?php echo $t['id']; ?></td>
                            <td><?php echo htmlspecialchars($t['client_nom']); ?></td>
                            <td><span class="badge" style="background:#f1c40f; color:#000;"><?php echo ucfirst(str_replace('_', ' ', $t['statut'])); ?></span></td>
                            <td style="font-size:0.9em; color:var(--text-muted);"><?php echo $t['tech_nom'] ? htmlspecialchars($t['tech_nom']) : '- Non assigné -'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stats-grid-two">
            <!-- PERF TECH -->
            <div class="card">
                <h3 style="margin-top:0;"><i class="fa-solid fa-users"></i> Performance Techniciens</h3>
                <table style="font-size:0.9em;">
                    <thead><tr><th>Technicien</th><th>Inter. Total</th><th>Inter. Terminées</th><th>Taux Réussite</th></tr></thead>
                    <tbody>
                        <?php while($tech = sqlsrv_fetch_array($techStats, SQLSRV_FETCH_ASSOC)): 
                            $taux = $tech['total_inter'] > 0 ? round(($tech['inter_terminees'] / $tech['total_inter']) * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($tech['nom_complet']); ?></strong></td>
                            <td style="text-align:center;"><?php echo $tech['total_inter']; ?></td>
                            <td style="text-align:center; color:var(--success); font-weight:bold;"><?php echo $tech['inter_terminees']; ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="progress-container" style="margin:0; width:60px; height:8px;"><div class="progress-bar" style="width:<?php echo $taux; ?>%; background: <?php echo ($taux>80)?'var(--success)':'var(--warning)'; ?>"></div></div>
                                    <span style="font-size:0.8em;"><?php echo $taux; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- EXPORT FORM -->
            <div class="card" style="border:2px solid var(--accent);">
                <h2 style="color:var(--accent); margin-top:0;"><i class="fa-solid fa-file-export"></i> Exporter Rapport Détaillé</h2>
                <p style="font-size:0.9em; color:var(--text-muted); margin-bottom:20px;">Générez un rapport complet listant tout le cycle de vie des tickets et interventions sur une période donnée.</p>
                <form action="export_stats.php" method="POST" target="_blank">
                    <div style="display:flex; gap:15px; margin-bottom:20px;">
                        <div class="form-group" style="flex:1;">
                            <label>Date de Début</label>
                            <input type="date" name="date_debut" required class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label>Date de Fin</label>
                            <input type="date" name="date_fin" required class="form-control" value="<?php echo date('Y-m-t'); ?>">
                        </div>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="submit" name="format" value="pdf" class="btn" style="flex:1; background-color: #e74c3c; border:none; padding:15px;">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="submit" name="format" value="csv" class="btn" style="flex:1; background-color: #27ae60; border:none; padding:15px;">
                            <i class="fa-solid fa-file-csv"></i> Excel / CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>

    <!-- SCRIPT CHART.JS -->
    <script>
        const chartColor = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#6818A5';

        // Line Chart (14 Jours)
        const ctxLine = document.getElementById('lineChart').getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartDates); ?>,
                datasets: [{
                    label: 'Nouveaux Tickets',
                    data: <?php echo json_encode($chartCounts); ?>,
                    borderColor: chartColor,
                    backgroundColor: 'rgba(104, 24, 165, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Pie/Doughnut Chart (Statuts)
        const ctxPie = document.getElementById('pieChart').getContext('2d');
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statutLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statutCounts); ?>,
                    backgroundColor: <?php echo json_encode($statutColors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
            }
        });
    </script>
</body>
</html>
