<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

$pageTitle = "Audit Importations";

// Récupérer les statistiques pour chaque entité
$clientsStats = query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Nom IS NULL OR Nom = '' THEN 1 ELSE 0 END) as missing_nom,
        SUM(CASE WHEN Email IS NULL OR Email = '' THEN 1 ELSE 0 END) as missing_email,
        SUM(CASE WHEN TEL IS NULL OR TEL = '' THEN 1 ELSE 0 END) as missing_tel
    FROM SAV_Clients
");

$sitesStats = query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Nom IS NULL OR Nom = '' THEN 1 ELSE 0 END) as missing_nom,
        SUM(CASE WHEN Adresse IS NULL OR Adresse = '' THEN 1 ELSE 0 END) as missing_address,
        SUM(CASE WHEN Ville IS NULL OR Ville = '' THEN 1 ELSE 0 END) as missing_ville
    FROM SAV_Sites
");

$contractsStats = query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN Ref_Contrat IS NULL OR Ref_Contrat = '' THEN 1 ELSE 0 END) as missing_ref,
        SUM(CASE WHEN Montant IS NULL OR Montant = 0 THEN 1 ELSE 0 END) as missing_amount,
        SUM(CASE WHEN Date_Fin IS NULL THEN 1 ELSE 0 END) as missing_end_date
    FROM CONTRAT
");

$ticketsStats = query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN SUJET IS NULL OR SUJET = '' THEN 1 ELSE 0 END) as missing_subject,
        SUM(CASE WHEN ID_CLIENT IS NULL THEN 1 ELSE 0 END) as missing_client,
        SUM(CASE WHEN ID_SITE IS NULL THEN 1 ELSE 0 END) as missing_site
    FROM TICKET
");

$statsArray = [];
if ($clientsStats) {
    $row = sqlsrv_fetch_array($clientsStats, SQLSRV_FETCH_ASSOC);
    $statsArray['clients'] = $row;
}
if ($sitesStats) {
    $row = sqlsrv_fetch_array($sitesStats, SQLSRV_FETCH_ASSOC);
    $statsArray['sites'] = $row;
}
if ($contractsStats) {
    $row = sqlsrv_fetch_array($contractsStats, SQLSRV_FETCH_ASSOC);
    $statsArray['contracts'] = $row;
}
if ($ticketsStats) {
    $row = sqlsrv_fetch_array($ticketsStats, SQLSRV_FETCH_ASSOC);
    $statsArray['tickets'] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .audit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .audit-card {
            background: var(--surface);
            border: 1px solid rgba(58,1,92,.08);
            border-radius: var(--r-lg);
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }

        .audit-card h3 {
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .audit-card i {
            color: var(--accent);
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,.05);
            font-size: 0.9rem;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: var(--text-muted);
        }

        .stat-value {
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-missing {
            color: var(--danger);
            font-weight: 700;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(0,0,0,.1);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-link {
            padding: 8px 16px;
            font-size: 0.85rem;
            background: var(--surface-2);
            border: 1px solid rgba(58,1,92,.12);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--primary);
            font-weight: 600;
        }

        .btn-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        @media (max-width: 767px) {
            .audit-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .audit-card {
                padding: 16px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-link {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div style="display:flex; align-items:center; gap:20px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div style="display:flex; flex-direction:column;">
                    <h1 style="margin:0;"><i class="fa-solid fa-clipboard-list text-accent" style="margin-right:8px;"></i>Audit Importations</h1>
                    <span style="font-size:.9rem; color:var(--text-muted); font-weight:600;">Qualité des données</span>
                </div>
            </div>
            <div style="display:flex; align-items:center;">
                <?php include __DIR__ . '/../../includes/notification_ui.php'; ?>
            </div>
        </header>

        <div class="page-content">
            <div class="audit-grid">
                <!-- CLIENTS Card -->
                <div class="audit-card">
                    <h3>
                        <i class="fa-solid fa-users"></i>
                        Clients
                    </h3>
                    <?php if (isset($statsArray['clients'])): 
                        $c = $statsArray['clients'];
                        $completeness = $c['total'] > 0 ? round((($c['total'] - ($c['missing_nom'] + $c['missing_email'] + $c['missing_tel']) / 3) / $c['total']) * 100) : 0;
                    ?>
                        <div class="stat-row">
                            <span class="stat-label">Total</span>
                            <span class="stat-value"><?= number_format($c['total']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Nom manquant</span>
                            <span class="stat-missing"><?= number_format($c['missing_nom']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Email manquant</span>
                            <span class="stat-missing"><?= number_format($c['missing_email']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Tél manquant</span>
                            <span class="stat-missing"><?= number_format($c['missing_tel']) ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $completeness ?>%"></div>
                        </div>
                        <div class="action-buttons">
                            <a href="../commercial/clients.php" class="btn-link"><i class="fa-solid fa-eye"></i> Voir</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SITES Card -->
                <div class="audit-card">
                    <h3>
                        <i class="fa-solid fa-map-pin"></i>
                        Sites
                    </h3>
                    <?php if (isset($statsArray['sites'])): 
                        $s = $statsArray['sites'];
                        $completeness = $s['total'] > 0 ? round((($s['total'] - ($s['missing_nom'] + $s['missing_address'] + $s['missing_ville']) / 3) / $s['total']) * 100) : 0;
                    ?>
                        <div class="stat-row">
                            <span class="stat-label">Total</span>
                            <span class="stat-value"><?= number_format($s['total']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Nom manquant</span>
                            <span class="stat-missing"><?= number_format($s['missing_nom']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Adresse manquante</span>
                            <span class="stat-missing"><?= number_format($s['missing_address']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Ville manquante</span>
                            <span class="stat-missing"><?= number_format($s['missing_ville']) ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $completeness ?>%"></div>
                        </div>
                        <div class="action-buttons">
                            <a href="../commercial/clients.php" class="btn-link"><i class="fa-solid fa-eye"></i> Voir</a>
                            <a href="../admin/import_csv.php?type=sites" class="btn-link"><i class="fa-solid fa-upload"></i> Importer</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TICKETS Card -->
                <div class="audit-card">
                    <h3>
                        <i class="fa-solid fa-ticket"></i>
                        Tickets
                    </h3>
                    <?php if (isset($statsArray['tickets'])): 
                        $t = $statsArray['tickets'];
                        $completeness = $t['total'] > 0 ? round((($t['total'] - ($t['missing_subject'] + $t['missing_client'] + $t['missing_site']) / 3) / $t['total']) * 100) : 0;
                    ?>
                        <div class="stat-row">
                            <span class="stat-label">Total</span>
                            <span class="stat-value"><?= number_format($t['total']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Sujet manquant</span>
                            <span class="stat-missing"><?= number_format($t['missing_subject']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Client manquant</span>
                            <span class="stat-missing"><?= number_format($t['missing_client']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Site manquant</span>
                            <span class="stat-missing"><?= number_format($t['missing_site']) ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $completeness ?>%"></div>
                        </div>
                        <div class="action-buttons">
                            <a href="tickets.php" class="btn-link"><i class="fa-solid fa-eye"></i> Voir</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CONTRATS Card -->
                <div class="audit-card">
                    <h3>
                        <i class="fa-solid fa-file-contract"></i>
                        Contrats
                    </h3>
                    <?php if (isset($statsArray['contracts'])): 
                        $ct = $statsArray['contracts'];
                        $completeness = $ct['total'] > 0 ? round((($ct['total'] - ($ct['missing_ref'] + $ct['missing_amount'] + $ct['missing_end_date']) / 3) / $ct['total']) * 100) : 0;
                    ?>
                        <div class="stat-row">
                            <span class="stat-label">Total</span>
                            <span class="stat-value"><?= number_format($ct['total']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Réf manquante</span>
                            <span class="stat-missing"><?= number_format($ct['missing_ref']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Montant manquant</span>
                            <span class="stat-missing"><?= number_format($ct['missing_amount']) ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Date fin manquante</span>
                            <span class="stat-missing"><?= number_format($ct['missing_end_date']) ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $completeness ?>%"></div>
                        </div>
                        <div class="action-buttons">
                            <a href="../commercial/contrats.php" class="btn-link"><i class="fa-solid fa-eye"></i> Voir</a>
                            <a href="../admin/import_csv.php?type=contrats" class="btn-link"><i class="fa-solid fa-upload"></i> Importer</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('menuBtn') && document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay') && document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });
    </script>
</body>
</html>
