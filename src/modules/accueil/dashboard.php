<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['accueil', 'admin']);

// Auto-update contract statuses
require_once __DIR__ . '/../../modules/automation/update_contract_status.php';
updateExpiredContracts($conn);

// Stats
$rOpen    = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM TICKET WHERE ETAT IN ('nouveau','ouvert')"), SQLSRV_FETCH_ASSOC);
$rAll     = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM TICKET"), SQLSRV_FETCH_ASSOC);
$rClients = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM SAV_Clients"), SQLSRV_FETCH_ASSOC);
$rPend    = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM TICKET WHERE ETAT = 'en_cours_tac'"), SQLSRV_FETCH_ASSOC);
$rHC      = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM TICKET WHERE ETAT = 'attente_devis'"), SQLSRV_FETCH_ASSOC);

$ticketsOpen   = $rOpen['c']    ?? 0;
$ticketsAll    = $rAll['c']     ?? 0;
$totalClients  = $rClients['c'] ?? 0;
$ticketsTac    = $rPend['c']    ?? 0;
$ticketsHC     = $rHC['c']      ?? 0;

$analyticsBars = [
    'Ouverts' => min(100, max(8, $ticketsOpen * 6)),
    'TAC' => min(100, max(8, $ticketsTac * 8)),
    'Hors Contrat' => min(100, max(8, $ticketsHC * 10)),
    'Clients' => min(100, max(8, (int)ceil($totalClients / 2))),
];

$today = new DateTime();
$startOfWeek = clone $today;
$startOfWeek->modify('monday this week');
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $startOfWeek;
    $d->modify('+' . $i . ' day');
    $weekDays[] = $d;
}

// Derniers tickets
$latestTickets = query("SELECT TOP 8 t.ID_TICKET, t.ETAT, t.PRIORITE, t.DATE, t.COMMENT, c.Nom as client_nom FROM TICKET t LEFT JOIN SAV_Clients c ON t.ID_CLIENT = c.ID_Client ORDER BY t.DATE DESC");

// Retour Hors Contrat
$retourTickets = query("SELECT t.ID_TICKET, t.COMMENT, t.DATE, c.Nom as client_nom FROM TICKET t JOIN SAV_Clients c ON t.ID_CLIENT = c.ID_Client WHERE t.ETAT = 'attente_devis' ORDER BY t.DATE DESC");
$hasRetours    = sqlsrv_has_rows($retourTickets);

// Derniers clients
$latestClients = query("SELECT TOP 5 Nom, Ville, TEL FROM SAV_Clients ORDER BY ID_Client DESC");

$pageTitle = "Accueil — Tableau de Bord";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        body {
            background: radial-gradient(circle at 80% 20%, rgba(184, 166, 255, 0.08) 0%, transparent 40%),
                        radial-gradient(circle at 10% 80%, rgba(167, 216, 255, 0.1) 0%, transparent 40%),
                        #F4F4F6;
            color: #1D1D1F;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .hdr-main { display:flex; align-items:center; gap:14px; }
        .hdr-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

        /* Liquid Glass KPIs */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 20px;
            padding: 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04), inset 0 1px 1px rgba(255, 255, 255, 0.8);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06), inset 0 1px 1px rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.55);
        }
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        /* Gradients for KPI icons based on color palette */
        .kpi-ic-open { background: linear-gradient(135deg, #A7D8FF 0%, #76B4FF 100%); } /* Soft Blue */
        .kpi-ic-total { background: linear-gradient(135deg, #B8A6FF 0%, #8D76FF 100%); } /* Soft Purple */
        .kpi-ic-tac { background: linear-gradient(135deg, #FFD6E7 0%, #FF9EBD 100%); color: #D6336C !important; } /* Soft Pink */
        .kpi-ic-hc { background: linear-gradient(135deg, #FF9EBD 0%, #EF4444 100%); } /* Warm Red-Pink */
        .kpi-ic-clients { background: linear-gradient(135deg, #E5E5E5 0%, #ADB5BD 100%); color: #1D1D1F !important; }

        .kpi-body strong { display: block; font-size: 1.8rem; font-weight: 800; line-height: 1; color: #1D1D1F; letter-spacing: -.03em; }
        .kpi-body span { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #86868B; }

        /* Liquid Glass Cards */
        .card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04), inset 0 1px 1px rgba(255, 255, 255, 0.8);
            padding: 24px;
            margin-bottom: 24px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.06), inset 0 1px 1px rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.55);
        }

        .sec-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .sec-head h2, .sec-head h3 { font-size: 1.1rem; font-weight: 700; color: #1D1D1F; display: flex; align-items: center; gap: 9px; margin: 0; }
        .sec-head h2 i, .sec-head h3 i { color: #8D76FF; }

        /* Tables inside Liquid Glass */
        .table-wrapper {
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: rgba(255, 255, 255, 0.5) !important;
            color: #515154;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            color: #1D1D1F;
            background: transparent !important;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background: rgba(255, 255, 255, 0.3) !important;
        }

        .ticket-row td { font-size: .88rem; }
        .priority-dot {
            width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px;
        }
        .p-haute    { background: #FF9EBD; box-shadow: 0 0 8px #FF9EBD; }
        .p-normale  { background: #A7D8FF; box-shadow: 0 0 8px #A7D8FF; }
        .p-basse    { background: #E5E5E5; }
        .p-urgence  { background: #FF3B30; box-shadow: 0 0 8px #FF3B30; }

        .dash-analytics {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .trend-grid {
            display: grid;
            gap: 16px;
        }
        .trend-row {
            display: grid;
            grid-template-columns: 120px 1fr 40px;
            align-items: center;
            gap: 12px;
            font-size: .88rem;
        }
        .trend-track {
            height: 10px;
            border-radius: 99px;
            background: rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .trend-bar {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #B8A6FF 0%, #A7D8FF 100%);
            box-shadow: 0 0 10px rgba(184, 166, 255, 0.3);
            transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .trend-bar[data-width] { width: 0; }
        
        .mini-calendar {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 10px;
            margin-top: 8px;
        }
        .mini-day {
            border-radius: 12px;
            padding: 10px 4px;
            text-align: center;
            background: rgba(255, 255, 255, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.35);
            transition: all 0.3s ease;
        }
        .mini-day small {
            display: block;
            color: #86868B;
            font-size: .68rem;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .mini-day strong {
            font-size: 1rem;
            color: #1D1D1F;
        }
        .mini-day.is-today {
            border-color: #B8A6FF;
            box-shadow: 0 0 12px rgba(184, 166, 255, 0.25);
            background: rgba(255, 255, 255, 0.6);
        }

        /* Hors Contrat alert layout */
        .hc-card {
            border-left: 5px solid #FF9EBD;
            background: rgba(255, 214, 231, 0.25);
            box-shadow: 0 8px 32px rgba(255, 214, 231, 0.08), inset 0 1px 1px rgba(255, 255, 255, 0.8);
        }
        .hc-title { color: #D6336C !important; }

        .truncate-220 { max-width:220px; }
        .truncate-200 { max-width:200px; }
        .cell-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .client-icon-cell i { color: #8D76FF; margin-right:8px; }

        /* Liquid Glass buttons styling */
        .btn {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            color: #1D1D1F;
            font-weight: 600;
            border-radius: 12px;
            padding: 8px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn:hover {
            background: #B8A6FF;
            color: #FFFFFF;
            border-color: #B8A6FF;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(184, 166, 255, 0.3);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.35);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.6);
            color: #1D1D1F;
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Badge design update */
        .badge {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
        }
        .badge-nouveau { background: #A7D8FF; color: #1D4ED8; }
        .badge-ouvert { background: #B8A6FF; color: #5B21B6; }
        .badge-en_cours_tac { background: #FFD6E7; color: #C2185B; }
        .badge-attente_dispatch { background: #FEF3C7; color: #D97706; }
        .badge-cloture { background: #E5E7EB; color: #374151; }

        @media (max-width: 980px) {
            .dash-analytics {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">

        <!-- HEADER -->
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>Bonjour, <?= htmlspecialchars($_SESSION['nom_complet']) ?> 👋</h1>
                    <p class="text-muted text-sm"><?= date('l d F Y') ?></p>
                </div>
            </div>
            <div class="hdr-actions">
                <?php include __DIR__ . '/../../includes/notification_ui.php'; ?>
                <a href="ticket_create.php" class="btn"><i class="fa-solid fa-plus"></i> Nouveau Ticket</a>
            </div>
        </header>

        <div class="page-content">

            <!-- KPIs -->
            <div>
                <div class="sec-head">
                    <h2><i class="fa-solid fa-gauge-high"></i> Indicateurs du Jour</h2>
                    <span class="text-muted text-sm"><i class="fa-regular fa-clock"></i> Temps réel</span>
                </div>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-open"><i class="fa-solid fa-envelope-open-text"></i></div>
                        <div class="kpi-body">
                            <strong><?= $ticketsOpen ?></strong>
                            <span>Tickets Ouverts</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-total"><i class="fa-solid fa-list-ol"></i></div>
                        <div class="kpi-body">
                            <strong><?= $ticketsAll ?></strong>
                            <span>Total Tickets</span>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-tac"><i class="fa-solid fa-clock"></i></div>
                        <div class="kpi-body">
                            <strong><?= $ticketsTac ?></strong>
                            <span>En Cours TAC</span>
                        </div>
                    </div>
                    <?php if ($ticketsHC > 0): ?>
                    <div class="kpi-card kpi-hc-border">
                        <div class="kpi-icon kpi-ic-hc"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="kpi-body">
                            <strong><?= $ticketsHC ?></strong>
                            <span>Hors Contrat</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-clients"><i class="fa-solid fa-users"></i></div>
                        <div class="kpi-body">
                            <strong><?= $totalClients ?></strong>
                            <span>Clients</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dash-analytics">
                <div class="card">
                    <div class="sec-head">
                        <h3><i class="fa-solid fa-chart-column"></i> Tendances opérationnelles</h3>
                        <span class="text-muted text-sm">Aperçu rapide</span>
                    </div>
                    <div class="trend-grid">
                        <?php foreach ($analyticsBars as $label => $value): ?>
                            <div class="trend-row">
                                <span><?= htmlspecialchars($label) ?></span>
                                <div class="trend-track"><div class="trend-bar" data-width="<?= (int)$value ?>"></div></div>
                                <strong class="text-muted"><?= (int)$value ?>%</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="sec-head">
                        <h3><i class="fa-regular fa-calendar-days"></i> Planning semaine</h3>
                        <span class="text-muted text-sm"><?= $today->format('F Y') ?></span>
                    </div>
                    <div class="mini-calendar">
                        <?php foreach ($weekDays as $day): ?>
                            <?php $isToday = $day->format('Y-m-d') === $today->format('Y-m-d'); ?>
                            <div class="mini-day <?= $isToday ? 'is-today' : '' ?>">
                                <small><?= $day->format('D') ?></small>
                                <strong><?= $day->format('d') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ALERTE HORS CONTRAT -->
            <?php if ($hasRetours): ?>
            <div class="card hc-card">
                <div class="sec-head hc-head">
                    <h2 class="hc-title"><i class="fa-solid fa-triangle-exclamation"></i> Tickets Hors Contrat — Attente Devis</h2>
                    <span class="badge badge-danger"><?= $ticketsHC ?></span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Client</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($rt = sqlsrv_fetch_array($retourTickets, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($rt['ID_TICKET'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($rt['client_nom']) ?></td>
                                <td class="truncate truncate-220"><?= htmlspecialchars(substr($rt['COMMENT'], 0, 60)) ?>…</td>
                                <td class="text-muted text-sm"><?= ($rt['DATE'] instanceof DateTime) ? $rt['DATE']->format('d/m/Y') : '' ?></td>
                                <td class="cell-actions">
                                    <a href="commandes.php?ticket_id=<?= htmlspecialchars($rt['ID_TICKET'] ?? '') ?>" class="btn btn-sm"><i class="fa-solid fa-file-invoice-dollar"></i> Devis</a>
                                    <a href="ticket_details.php?id=<?= htmlspecialchars($rt['ID_TICKET'] ?? '') ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- DERNIERS TICKETS -->
            <div class="card">
                <div class="sec-head">
                    <h3><i class="fa-solid fa-clock-rotate-left"></i> Derniers Tickets</h3>
                    <a href="tickets.php" class="btn btn-sm btn-secondary"><i class="fa-solid fa-arrow-right"></i> Voir tout</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Description</th>
                                <th>Priorité</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tk = sqlsrv_fetch_array($latestTickets, SQLSRV_FETCH_ASSOC)): ?>
                            <?php
                                $prio = strtolower($tk['PRIORITE'] ?? 'normale');
                                $dotClass = in_array($prio, ['haute','urgence']) ? 'p-'.$prio : (($prio=='basse') ? 'p-basse' : 'p-normale');
                            ?>
                            <tr class="ticket-row">
                                <td><strong>#<?= htmlspecialchars($tk['ID_TICKET'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($tk['client_nom'] ?? '—') ?></td>
                                <td class="truncate truncate-200"><?= htmlspecialchars(substr($tk['COMMENT'], 0, 55)) ?>…</td>
                                <td><span class="priority-dot <?= $dotClass ?>"></span><?= htmlspecialchars($tk['PRIORITE'] ?? '—') ?></td>
                                <td><span class="badge badge-<?= strtolower($tk['ETAT'] ?? '') ?>"><?= htmlspecialchars($tk['ETAT'] ?? '') ?></span></td>
                                <td class="text-muted text-sm"><?= ($tk['DATE'] instanceof DateTime) ? $tk['DATE']->format('d/m/Y') : '' ?></td>
                                <td><a href="ticket_details.php?id=<?= htmlspecialchars($tk['ID_TICKET'] ?? '') ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DERNIERS CLIENTS -->
            <div class="card">
                <div class="sec-head">
                    <h3><i class="fa-solid fa-user-plus"></i> Derniers Clients Ajoutés</h3>
                    <a href="../commercial/clients.php" class="btn btn-sm btn-secondary"><i class="fa-solid fa-arrow-right"></i> Voir tout</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Ville</th>
                                <th>Téléphone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($c = sqlsrv_fetch_array($latestClients, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td class="client-icon-cell"><i class="fa-solid fa-building-user"></i><strong><?= htmlspecialchars($c['Nom']) ?></strong></td>
                                <td><?= htmlspecialchars($c['Ville'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($c['TEL'] ?? '—') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /page-content -->
    </div><!-- /main-content -->

    <script>
        document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });

        document.querySelectorAll('.trend-bar[data-width]').forEach((bar) => {
            const value = parseInt(bar.dataset.width || '0', 10);
            const clamped = Math.max(0, Math.min(100, value));
            bar.style.width = `${clamped}%`;
        });
    </script>
</body>
</html>
