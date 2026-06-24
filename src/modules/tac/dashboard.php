<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['tac', 'admin']);

$routeModeKey = 'route_accueil_direct_dispatch';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_direct_dispatch_mode') {
    $newMode = ($_POST['mode'] ?? '0') === '1' ? '1' : '0';
    set_app_setting($routeModeKey, $newMode);
    header('Location: dashboard.php');
    exit;
}

$isDirectDispatchMode = get_app_setting($routeModeKey, '0') === '1';

// 1. Tickets à prendre (Nouveaux / Ouverts)
$sql_new = "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, 'Demande SAV' as sujet, 'Général' as type_probleme, SAV_Clients.Nom as client_nom, SAV_Sites.Ville as ville
        FROM TICKET
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE TICKET.ETAT IN ('nouveau', 'ouvert')
        ORDER BY TICKET.PRIORITE DESC, TICKET.DATE ASC";
$tickets_new = query($sql_new);

// 2. Mes Dossiers (En cours TAC)
$sql_progress = "SELECT TICKET.ID_TICKET as id, TICKET.DATE as cree_le, TICKET.ETAT as statut, TICKET.PRIORITE as priorite, 'Demande SAV' as sujet, SAV_Clients.Nom as client_nom, SAV_Sites.Ville as ville
        FROM TICKET
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE TICKET.ETAT = 'en_cours_tac'
        ORDER BY TICKET.PRIORITE DESC, TICKET.DATE ASC";
$tickets_progress = query($sql_progress);

// 3. Tickets Traités (Résolu / Clôturé / Attente Dispatch) - Derniers 20
$sql_done = "SELECT TOP 20 TICKET.ID_TICKET as id, TICKET.DATE as tac_date_traitement, TICKET.ETAT as statut, 'Demande SAV' as sujet, SAV_Clients.Nom as client_nom, SAV_Sites.Ville as ville
        FROM TICKET
        JOIN SAV_Clients ON TICKET.ID_CLIENT = SAV_Clients.ID_Client
        LEFT JOIN SAV_Sites ON TICKET.ID_SITE = SAV_Sites.Id_Site
        WHERE TICKET.ETAT IN ('resolu', 'traite', 'cloture', 'attente_dispatch', 'attente_devis')
        ORDER BY TICKET.DATE DESC";
$tickets_done = query($sql_done);

$pageTitle = "Dashboard TAC";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .urgent { border-left: 6px solid var(--danger); background: linear-gradient(to right, rgba(239,68,68,.05), transparent); }
        .haute { border-left: 6px solid var(--warning); background: linear-gradient(to right, rgba(245,158,11,.05), transparent); }

        .ticket-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: var(--r-md);
            padding: 24px;
            display: flex; justify-content: space-between; align-items: center;
            border: 1px solid rgba(58,1,92,.05);
            background: var(--surface);
        }
        .ticket-card:hover {
            transform: translateX(6px) translateY(-2px);
            box-shadow: 0 10px 25px rgba(24,8,44,.06);
            border-color: rgba(155,93,229,.2);
        }

        .section-header {
            display: flex; align-items: center; gap: 12px;
            margin: 40px 0 20px;
            padding-bottom: 12px; border-bottom: 2px solid rgba(58,1,92,.08);
        }
        .section-header h2 { font-size: 1.3rem; margin: 0; font-weight: 800; letter-spacing: -0.02em; }
        .hdr-main { display:flex; align-items:center; gap:16px; }
        .hdr-title { margin:0; }
        .hdr-title i { color:var(--accent); margin-right:8px; }
        .hdr-actions { display:flex; gap:10px; align-items:center; }
        .section-top { margin-top:10px; }
        .section-danger { color:var(--danger); }
        .section-primary { color:var(--primary); }
        .section-success { color:var(--success); }
        .section-icon { font-size:1.4rem; }
        .section-title-fill { color:var(--text); flex:1; }
        .section-title-default { color:var(--text); }
        .ticket-list { display:grid; gap:16px; }
        .ticket-main { flex:1; }
        .ticket-top { display:flex; align-items:center; gap:12px; margin-bottom:8px; flex-wrap:wrap; }
        .badge-ticket-id { font-family:monospace; font-size:1rem; }
        .ticket-client-title { margin:0; font-size:1.15rem; color:var(--dark-amethyst-3); }
        .ticket-meta { color:var(--text-muted); font-size:.95rem; display:flex; gap:16px; align-items:center; }
        .empty-dashed-strong { padding:40px; border:2px dashed rgba(16,185,129,.3); }
        .empty-dashed-soft { padding:30px; border:1px dashed rgba(58,1,92,.2); }
        .empty-icon-mug { font-size:3rem; margin-bottom:14px; opacity:.7; }
        .ticket-card-progress { border-left: 6px solid var(--primary); background:linear-gradient(to right, rgba(155,93,229,.03), transparent); }
        .ticket-subtle { color:var(--text-muted); font-size:.95rem; }
        .quote-icon { opacity:.4; }
        .cell-empty { padding:30px; }
        .ticket-id-cell { font-family:monospace; }

        @media (max-width: 768px) {
            .ticket-card { flex-direction: column; align-items: flex-start; gap: 16px; }
            .ticket-card > div:last-child { width: 100%; }
            .ticket-card .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <h1 class="hdr-title"><i class="fa-solid fa-headset"></i>Tableau de Bord N2 (TAC)</h1>
            </div>
            <div class="hdr-actions">
                <span class="badge badge-admin"><i class="fa-solid fa-user-shield"></i> Support Expert</span>
                <?php include __DIR__ . '/../../includes/notification_ui.php'; ?>
            </div>
        </header>

        <div class="page-content">

            <!-- SECTION 1: NOUVEAUX TICKETS -->
            <div class="section-header section-danger section-top">
                <i class="fa-solid fa-inbox section-icon"></i>
                <h2 class="section-title-fill">À Prendre (Ouverts)</h2>
                <?php $has_new = sqlsrv_has_rows($tickets_new); ?>
                <span class="badge badge-<?= $has_new ? 'urgence' : 'normal' ?>"><?= $has_new ? 'Action Requise' : 'Aucun ticket en attente' ?></span>
            </div>

            <?php if($has_new): ?>
            <div class="ticket-list">
                <?php while($t = sqlsrv_fetch_array($tickets_new, SQLSRV_FETCH_ASSOC)):
                    $class = ($t['priorite'] == 'urgente') ? 'urgent' : (($t['priorite'] == 'haute') ? 'haute' : '');
                ?>
                <div class="ticket-card <?= $class ?>">
                    <div class="ticket-main">
                        <div class="ticket-top">
                            <span class="badge badge-normal badge-ticket-id">#<?= $t['id'] ?></span>
                            <h3 class="ticket-client-title"><i class="fa-regular fa-building text-muted"></i> <?= htmlspecialchars($t['client_nom']) ?></h3>
                            <span class="badge badge-<?= strtolower($t['priorite']) ?>"><i class="fa-solid fa-triangle-exclamation"></i> <?= strtoupper($t['priorite']) ?></span>
                        </div>
                        <div class="ticket-meta">
                            <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($t['type_probleme']) ?></span>
                            <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($t['ville']) ?></span>
                            <span><i class="fa-regular fa-clock"></i> <?= $t['cree_le']->format('d/m H:i') ?></span>
                        </div>
                    </div>
                    <div>
                        <a href="ticket_process.php?id=<?= $t['id'] ?>" class="btn"><i class="fa-solid fa-hand-holding-medical"></i> Prendre en charge</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="card text-center empty-dashed-strong">
                    <i class="fa-solid fa-mug-hot text-success empty-icon-mug"></i>
                    <h3 class="text-success">Boîte de réception vide</h3>
                    <p class="text-muted">Aucun nouveau ticket N2 en attente de prise en charge.</p>
                </div>
            <?php endif; ?>

            <!-- SECTION 2: MES DOSSIERS EN COURS -->
            <div class="section-header section-primary">
                <i class="fa-solid fa-business-time section-icon"></i>
                <h2 class="section-title-default">En Cours de Traitement (Mes Dossiers)</h2>
            </div>

            <?php if(sqlsrv_has_rows($tickets_progress)): ?>
            <div class="ticket-list">
                <?php while($t = sqlsrv_fetch_array($tickets_progress, SQLSRV_FETCH_ASSOC)): ?>
                <div class="ticket-card ticket-card-progress">
                    <div class="ticket-main">
                        <div class="ticket-top">
                            <span class="badge badge-normal badge-ticket-id">#<?= $t['id'] ?></span>
                            <h3 class="ticket-client-title"><i class="fa-regular fa-building text-muted"></i> <?= htmlspecialchars($t['client_nom']) ?></h3>
                        </div>
                        <div class="ticket-subtle">
                            <i class="fa-solid fa-quote-left quote-icon"></i> <?= htmlspecialchars($t['sujet']) ?>
                        </div>
                    </div>
                    <div>
                        <a href="ticket_process.php?id=<?= $t['id'] ?>" class="btn btn-secondary"><i class="fa-solid fa-play"></i> Continuer</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="card text-center empty-dashed-soft">
                    <p class="text-muted title-no-margin">Aucun dossier en cours de résolution.</p>
                </div>
            <?php endif; ?>

            <!-- SECTION 3: TICKETS TRAITES -->
            <div class="section-header section-success">
                <i class="fa-solid fa-check-double section-icon"></i>
                <h2 class="section-title-default">Derniers Traités (Historique)</h2>
            </div>

            <div class="card p-0">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Ticket</th>
                                <th>Client / Site</th>
                                <th>Sujet</th>
                                <th>Statut Final</th>
                                <th>Date du traitement</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(sqlsrv_has_rows($tickets_done)): ?>
                                <?php while($t = sqlsrv_fetch_array($tickets_done, SQLSRV_FETCH_ASSOC)):
                                    $s = strtolower($t['statut']);
                                    $bClass = 'badge-normal';
                                    if($s == 'resolu' || $s == 'cloture' || $s == 'traite') $bClass = 'badge-resolu';
                                    if(strpos($s, 'attente') !== false) $bClass = 'badge-info';
                                ?>
                                <tr>
                                    <td><span class="badge badge-normal ticket-id-cell">#<?= $t['id'] ?></span></td>
                                    <td><strong><?= htmlspecialchars($t['client_nom']) ?></strong><div class="text-sm text-muted"><?= htmlspecialchars($t['ville']??'') ?></div></td>
                                    <td><?= htmlspecialchars(substr($t['sujet'], 0, 45)) ?><?= strlen($t['sujet'])>45?'...':'' ?></td>
                                    <td><span class="badge <?= $bClass ?>"><?= strtoupper(htmlspecialchars($t['statut'])) ?></span></td>
                                    <td><?= $t['tac_date_traitement'] ? $t['tac_date_traitement']->format('d/m/Y H:i') : '—' ?></td>
                                    <td class="text-right">
                                        <a href="../accueil/ticket_details.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted cell-empty">Aucun ticket traité récemment.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- JS Basics -->
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
