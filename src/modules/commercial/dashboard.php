<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin']);

// Stats
$nbClients   = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM SAV_Clients"), SQLSRV_FETCH_ASSOC)['c'] ?? 0;
$nbContrats  = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM CONTRAT"), SQLSRV_FETCH_ASSOC)['c'] ?? 0;
$nbContActif = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM CONTRAT WHERE ETAT='actif'"), SQLSRV_FETCH_ASSOC)['c'] ?? 0;
$nbSites     = sqlsrv_fetch_array(query("SELECT COUNT(*) as c FROM SAV_Sites"), SQLSRV_FETCH_ASSOC)['c'] ?? 0;
$caTotal     = sqlsrv_fetch_array(query("SELECT SUM(Montant_Contrat) as t FROM CONTRAT"), SQLSRV_FETCH_ASSOC)['t'] ?? 0;

// Derniers clients
$recentClients = query("SELECT TOP 6 Nom, Ville, TEL, ID_Client AS Code_Client FROM SAV_Clients ORDER BY ID_Client DESC");

// Contrats expirant bientôt
$expSoon = query("SELECT TOP 6 c.CODE_CONTRAT AS numero_contrat, c.Date_Fin AS date_fin, cl.Nom as client_nom FROM CONTRAT c JOIN SAV_Clients cl ON c.ID_Client = cl.ID_Client WHERE c.Date_Fin BETWEEN CAST(GETDATE() as DATE) AND DATEADD(day,30,CAST(GETDATE() as DATE)) ORDER BY c.Date_Fin ASC");
$hasExpSoon = sqlsrv_has_rows($expSoon);

$pageTitle = "Commercial — Tableau de Bord";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0eef8; }
        .hdr-main { display:flex; align-items:center; gap:14px; }
        .hdr-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px,1fr)); gap: 14px; }
        .kpi-card { background: var(--surface); border: 1px solid rgba(58,1,92,.08); border-radius: 18px; padding: 20px; display: flex; align-items: flex-start; gap: 14px; box-shadow: 0 3px 12px rgba(24,8,44,.07); transition: transform .22s, box-shadow .22s; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(34,1,53,.14); }
        .kpi-icon { width: 46px; height: 46px; border-radius: 13px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: #fff; }
        .kpi-body strong { display: block; font-size: 1.8rem; font-weight: 800; line-height: 1; color: var(--text); letter-spacing: -.03em; }
        .kpi-body span { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text-muted); }
        .sec-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
        .sec-head h2, .sec-head h3 { font-size: 1rem; font-weight: 700; color: var(--text-sub); display: flex; align-items: center; gap: 9px; }
        .sec-head h2 i, .sec-head h3 i { color: var(--accent); }
        .quick-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px,1fr)); gap: 12px; }
        .quick-card { background: var(--surface); border: 1px solid rgba(58,1,92,.08); border-radius: 14px; padding: 18px 14px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; text-decoration: none; color: var(--text); transition: all .22s; box-shadow: 0 2px 8px rgba(24,8,44,.05); }
        .quick-card:hover { background: linear-gradient(145deg,var(--dark-amethyst),var(--dark-amethyst-3)); color:#fff; transform:translateY(-3px) scale(1.03); box-shadow:0 14px 38px rgba(58,1,92,.35); border-color:transparent; }
        .quick-card:hover .q-ic { background:rgba(255,255,255,.15); color:#fff; }
        .quick-card:hover small { color:rgba(255,255,255,.7); }
        .q-ic { width:48px; height:48px; border-radius:13px; background:#f0eaf8; color:var(--dark-amethyst); display:flex; align-items:center; justify-content:center; font-size:1.25rem; transition:all .22s; }
        .quick-card h3 { font-size:.9rem; font-weight:700; margin:0; }
        .quick-card small { font-size:.72rem; color:var(--text-muted); }
        .kpi-ic-clients { background:linear-gradient(135deg,#3a015c,#9b5de5); }
        .kpi-ic-contracts { background:linear-gradient(135deg,#1d4ed8,#3b82f6); }
        .kpi-ic-active { background:linear-gradient(135deg,#166534,#22c55e); }
        .kpi-ic-sites { background:linear-gradient(135deg,#065f46,#10b981); }
        .kpi-ic-ca { background:linear-gradient(135deg,#92400e,#f59e0b); }
        .warn-card { border-left:4px solid var(--warning); background:linear-gradient(135deg,#fffbeb,#fff); }
        .warn-head { margin-bottom:12px; }
        .warn-title { color:var(--warning); }
        .client-name-cell i { color:var(--accent); margin-right:8px; }
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
                    <h1>Bonjour, <?= htmlspecialchars($_SESSION['nom_complet'] ?? 'Commercial') ?></h1>
                    <p class="text-muted text-sm"><?= date('l d F Y') ?></p>
                </div>
            </div>
            <div class="hdr-actions">
                <span class="badge badge-client"><i class="fa-solid fa-briefcase"></i> Commercial</span>
                <a href="clients.php?action=new" class="btn btn-sm"><i class="fa-solid fa-plus"></i> Nouveau Client</a>
            </div>
        </header>

        <div class="page-content">

            <!-- KPIs -->
            <div>
                <div class="sec-head">
                    <h2><i class="fa-solid fa-gauge-high"></i> Indicateurs</h2>
                </div>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-clients"><i class="fa-solid fa-building-user"></i></div>
                        <div class="kpi-body"><strong><?= $nbClients ?></strong><span>Clients</span></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-contracts"><i class="fa-solid fa-file-contract"></i></div>
                        <div class="kpi-body"><strong><?= $nbContrats ?></strong><span>Contrats Total</span></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-active"><i class="fa-solid fa-file-signature"></i></div>
                        <div class="kpi-body"><strong><?= $nbContActif ?></strong><span>Contrats Actifs</span></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-sites"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="kpi-body"><strong><?= $nbSites ?></strong><span>Sites</span></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon kpi-ic-ca"><i class="fa-solid fa-money-bill-wave"></i></div>
                        <div class="kpi-body"><strong><?= number_format($caTotal, 0, ',', ' ') ?></strong><span>CA (MAD)</span></div>
                    </div>
                </div>
            </div>

            <!-- ACCÈS RAPIDE -->
            <div>
                <div class="sec-head"><h2><i class="fa-solid fa-bolt"></i> Accès Rapide</h2></div>
                <div class="quick-grid">
                    <a href="client_edit.php" class="quick-card">
                        <div class="q-ic"><i class="fa-solid fa-user-plus"></i></div>
                        <h3>Ajouter Client</h3>
                        <small>Nouveau dossier</small>
                    </a>
                    <a href="contrat_create.php" class="quick-card">
                        <div class="q-ic"><i class="fa-solid fa-file-circle-plus"></i></div>
                        <h3>Créer Contrat</h3>
                        <small>Nouveau contrat</small>
                    </a>
                    <a href="clients.php" class="quick-card">
                        <div class="q-ic"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <h3>Rechercher Client</h3>
                        <small>Base clients</small>
                    </a>
                    <a href="contrats.php" class="quick-card">
                        <div class="q-ic"><i class="fa-solid fa-file-contract"></i></div>
                        <h3>Gérer Contrats</h3>
                        <small>Liste complète</small>
                    </a>
                </div>
            </div>

            <!-- ALERTE CONTRATS EXPIRANT -->
            <?php if ($hasExpSoon): ?>
            <div class="card warn-card">
                <div class="sec-head warn-head">
                    <h3 class="warn-title"><i class="fa-solid fa-hourglass-half"></i> Contrats Expirant dans 30 Jours</h3>
                    <span class="badge badge-warning">Urgent</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>N° Contrat</th><th>Client</th><th>Date Fin</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php while ($ex = sqlsrv_fetch_array($expSoon, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ex['numero_contrat']) ?></strong></td>
                            <td><?= htmlspecialchars($ex['client_nom']) ?></td>
                            <td><span class="badge badge-warning"><?= ($ex['date_fin'] instanceof DateTime) ? $ex['date_fin']->format('d/m/Y') : '' ?></span></td>
                            <td><a href="contrats.php" class="btn btn-sm"><i class="fa-solid fa-eye"></i> Voir</a></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- DERNIERS CLIENTS -->
            <div class="card">
                <div class="sec-head">
                    <h3><i class="fa-solid fa-user-plus"></i> Derniers Clients</h3>
                    <a href="clients.php" class="btn btn-sm btn-secondary"><i class="fa-solid fa-arrow-right"></i> Voir tout</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Nom</th><th>Code</th><th>Ville</th><th>Téléphone</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php while ($c = sqlsrv_fetch_array($recentClients, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td class="client-name-cell"><i class="fa-solid fa-building"></i><strong><?= htmlspecialchars($c['Nom']) ?></strong></td>
                            <td><span class="badge badge-admin"><?= htmlspecialchars($c['Code_Client'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($c['Ville'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($c['TEL'] ?? '—') ?></td>
                            <td><a href="clients.php" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i></a></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.getElementById('menuBtn').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        document.getElementById('sidebarOverlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('sidebarOverlay').classList.remove('active');
        });
    </script>
</body>
</html>
