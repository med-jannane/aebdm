<?php
require_once __DIR__ . '/_bootstrap.php';

$flags = cc_schema_flags();
$team = cc_fetch_team($cc_id, $flags);
$teamIds = cc_team_ids($team);
$rows = [];

if (count($teamIds) > 0) {
    $ph = cc_placeholders(count($teamIds));
    $sql = "SELECT
        U.id,
        U.nom_complet,
        " . (!empty($flags['has_phone']) ? "U.telephone" : "''") . " AS telephone,
        " . (!empty($flags['has_region']) ? "U.region" : "''") . " AS region,
        COUNT(I.id) AS inter_total,
        SUM(CASE WHEN I.statut = 'termine' THEN 1 ELSE 0 END) AS inter_done,
        MAX(COALESCE(I.date_intervention, I.date_planifiee)) AS last_inter
      FROM Users U
      LEFT JOIN Interventions I ON I.tech_id = U.id
      WHERE U.id IN ($ph)
      GROUP BY U.id, U.nom_complet, " . (!empty($flags['has_phone']) ? "U.telephone" : "''") . ", " . (!empty($flags['has_region']) ? "U.region" : "''") . "
      ORDER BY U.nom_complet ASC";

    $rows = fetchAll(query($sql, $teamIds));
}

$pageTitle = 'Charge de Compte - Techniciens';
$ccActivePage = 'techs';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>Techniciens assignes</h1>
                    <p class="text-muted text-sm">Acces complet aux informations et activites de vos techniciens.</p>
                </div>
            </div>
        </header>

        <div class="page-content">
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Telephone</th>
                                <th>Region</th>
                                <th>Total interventions</th>
                                <th>Terminees</th>
                                <th>Derniere activite</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Aucun technicien affecte.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($r['nom_complet']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['telephone'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($r['region'] ?: '—') ?></td>
                                        <td><?= (int)($r['inter_total'] ?? 0) ?></td>
                                        <td><?= (int)($r['inter_done'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($r['last_inter'] ?? null)) ?></td>
                                        <td><a class="btn btn-sm" href="technicien_detail.php?id=<?= urlencode($r['id']) ?>">Voir detail</a></td>
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
