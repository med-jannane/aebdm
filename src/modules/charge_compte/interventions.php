<?php
require_once __DIR__ . '/_bootstrap.php';

$flags = cc_schema_flags();
$team = cc_fetch_team($cc_id, $flags);
$teamIds = cc_team_ids($team);
$rows = [];

if (count($teamIds) > 0) {
    $ph = cc_placeholders(count($teamIds));
    $subjectExpr = cc_ticket_subject_sql($flags);

    $sql = "SELECT
        I.id,
        I.statut,
        I.date_planifiee,
        I.date_intervention,
        I.instructions,
        U.nom_complet AS tech_nom,
        T.ID_TICKET AS ticket_id,
        T.PRIORITE AS priorite,
        T.ETAT AS statut_ticket,
        $subjectExpr AS sujet,
        C.Nom AS client_nom,
        S.Nom AS site_nom,
        S.Ville AS ville
    FROM Interventions I
    JOIN Users U ON U.id = I.tech_id
    LEFT JOIN TICKET T ON T.ID_TICKET = I.ticket_id
    LEFT JOIN SAV_Clients C ON C.ID_Client = T.ID_CLIENT
    LEFT JOIN SAV_Sites S ON S.Id_Site = T.ID_SITE
    WHERE I.tech_id IN ($ph)
    ORDER BY COALESCE(I.date_intervention, I.date_planifiee) DESC";

    $rows = fetchAll(query($sql, $teamIds));
}

$pageTitle = 'Charge de Compte - Interventions';
$ccActivePage = 'interventions';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .hdr-main { display:flex; align-items:center; gap:14px; }
        .hdr-actions { display:flex; align-items:center; gap:10px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>Interventions assignees a mon equipe</h1>
                    <p class="text-muted text-sm">Suivi detaille de toutes les interventions des techniciens rattachés.</p>
                </div>
            </div>
            <div class="hdr-actions">
                <span class="badge badge-info"><?= count($rows) ?> interventions</span>
            </div>
        </header>

        <div class="page-content">
            <?php if (!$flags['has_manager']): ?>
                <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><div>La relation manager_id n'est pas disponible sur cette base.</div></div>
            <?php endif; ?>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tech</th>
                                <th>Intervention</th>
                                <th>Ticket</th>
                                <th>Client / Site</th>
                                <th>Sujet</th>
                                <th>Date prevue</th>
                                <th>Date reelle</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="8" class="text-center text-muted">Aucune intervention a afficher.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($r['tech_nom'] ?? '—') ?></strong></td>
                                        <td>#<?= htmlspecialchars($r['id'] ?? '—') ?></td>
                                        <td>#<?= htmlspecialchars($r['ticket_id'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(($r['client_nom'] ?? '—') . ' / ' . ($r['site_nom'] ?? '—')) ?></td>
                                        <td><?= htmlspecialchars($r['sujet'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($r['date_planifiee'] ?? null)) ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($r['date_intervention'] ?? null)) ?></td>
                                        <td><span class="badge badge-normal"><?= strtoupper(htmlspecialchars($r['statut'] ?? 'N/A')) ?></span></td>
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
