<?php
require_once __DIR__ . '/_bootstrap.php';

$flags = cc_schema_flags();
$team = cc_fetch_team($cc_id, $flags);
$teamIds = cc_team_ids($team);
$techStats = [];
$global = ['interventions' => 0, 'terminees' => 0, 'planifiees' => 0, 'annulees' => 0];

if (count($teamIds) > 0) {
    $ph = cc_placeholders(count($teamIds));

    $sqlGlobal = "SELECT
        COUNT(*) AS c,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS done_count,
        SUM(CASE WHEN statut IN ('planifie','en_route','en_cours') THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) AS cancel_count
      FROM Interventions
      WHERE tech_id IN ($ph)";
    $g = sqlsrv_fetch_array(query($sqlGlobal, $teamIds), SQLSRV_FETCH_ASSOC);

    $global['interventions'] = (int)($g['c'] ?? 0);
    $global['terminees'] = (int)($g['done_count'] ?? 0);
    $global['planifiees'] = (int)($g['open_count'] ?? 0);
    $global['annulees'] = (int)($g['cancel_count'] ?? 0);

    $sqlPerTech = "SELECT
        U.id,
        U.nom_complet,
        COUNT(I.id) AS total_inter,
        SUM(CASE WHEN I.statut = 'termine' THEN 1 ELSE 0 END) AS done_inter,
        SUM(CASE WHEN I.statut IN ('planifie','en_route','en_cours') THEN 1 ELSE 0 END) AS open_inter,
        MAX(COALESCE(I.date_intervention, I.date_planifiee)) AS last_inter
      FROM Users U
      LEFT JOIN Interventions I ON I.tech_id = U.id
      WHERE U.id IN ($ph)
      GROUP BY U.id, U.nom_complet
      ORDER BY U.nom_complet ASC";

    $techStats = fetchAll(query($sqlPerTech, $teamIds));
}

$pageTitle = 'Charge de Compte - Statistiques';
$ccActivePage = 'stats';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; }
        .kpi-card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:16px; }
        .kpi-card strong { display:block; font-size:1.75rem; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>Statistiques de mon equipe</h1>
                    <p class="text-muted text-sm">Vision complete des performances interventions par technicien.</p>
                </div>
            </div>
        </header>

        <div class="page-content">
            <div class="kpi-grid">
                <div class="kpi-card"><strong><?= $global['interventions'] ?></strong><span>Total interventions</span></div>
                <div class="kpi-card"><strong><?= $global['terminees'] ?></strong><span>Terminees</span></div>
                <div class="kpi-card"><strong><?= $global['planifiees'] ?></strong><span>Planifiees / En cours</span></div>
                <div class="kpi-card"><strong><?= $global['annulees'] ?></strong><span>Annulees</span></div>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Technicien</th>
                                <th>Total</th>
                                <th>Terminees</th>
                                <th>En cours</th>
                                <th>Derniere intervention</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($techStats)): ?>
                                <tr><td colspan="6" class="text-center text-muted">Aucune statistique disponible.</td></tr>
                            <?php else: ?>
                                <?php foreach ($techStats as $t): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($t['nom_complet']) ?></strong></td>
                                        <td><?= (int)($t['total_inter'] ?? 0) ?></td>
                                        <td><?= (int)($t['done_inter'] ?? 0) ?></td>
                                        <td><?= (int)($t['open_inter'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($t['last_inter'] ?? null)) ?></td>
                                        <td><a href="technicien_detail.php?id=<?= urlencode($t['id']) ?>" class="btn btn-sm btn-secondary">Detail</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
