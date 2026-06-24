<?php
require_once __DIR__ . '/_bootstrap.php';

$flags = cc_schema_flags();
$team = cc_fetch_team($cc_id, $flags);
$teamIds = cc_team_ids($team);
$rows = [];
$vpUnavailable = empty($flags['has_vp_tickets']);

if (!$vpUnavailable && count($teamIds) > 0) {
    $ph = cc_placeholders(count($teamIds));

    $sql = "SELECT
        VPT.ID_VP,
        VPT.ID_TICKET,
        T.ETAT AS ticket_statut,
        T.PRIORITE AS ticket_priorite,
        C.Nom AS client_nom,
        S.Nom AS site_nom,
        S.Ville AS ville,
        I.id AS intervention_id,
        I.date_planifiee,
        I.date_intervention,
        I.statut AS statut_intervention,
        U.nom_complet AS tech_nom
    FROM VP_Tickets VPT
    LEFT JOIN TICKET T ON T.ID_TICKET = VPT.ID_TICKET
    LEFT JOIN SAV_Clients C ON C.ID_Client = T.ID_CLIENT
    LEFT JOIN SAV_Sites S ON S.Id_Site = T.ID_SITE
    LEFT JOIN Interventions I ON I.ticket_id = VPT.ID_TICKET
    LEFT JOIN Users U ON U.id = I.tech_id
    WHERE I.tech_id IN ($ph)
    ORDER BY COALESCE(I.date_intervention, I.date_planifiee) DESC";

    $rows = fetchAll(query($sql, $teamIds));
}

$pageTitle = 'Charge de Compte - Visites Preventives';
$ccActivePage = 'visites';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require_once __DIR__ . '/../../includes/head.php'; ?>
    <style>
        .hdr-main { display:flex; align-items:center; gap:14px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <header>
            <div class="hdr-main">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <h1>Visites preventives</h1>
                    <p class="text-muted text-sm">Suivi des visites preventives liees aux tickets de vos techniciens.</p>
                </div>
            </div>
            <div class="hdr-actions"><span class="badge badge-success"><?= count($rows) ?> enregistrements</span></div>
        </header>

        <div class="page-content">
            <?php if ($vpUnavailable): ?>
                <div class="alert alert-warning"><i class="fa-solid fa-circle-info"></i><div>La table VP_Tickets n'existe pas sur cette base. Active la migration preventive pour utiliser cette page.</div></div>
            <?php endif; ?>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID VP</th>
                                <th>Ticket</th>
                                <th>Tech</th>
                                <th>Client / Site</th>
                                <th>Date prevue</th>
                                <th>Date reelle</th>
                                <th>Statut intervention</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Aucune visite preventive reliee a votre equipe.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['ID_VP'] ?? '—') ?></td>
                                        <td>#<?= htmlspecialchars($r['ID_TICKET'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($r['tech_nom'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(($r['client_nom'] ?? '—') . ' / ' . ($r['site_nom'] ?? '—')) ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($r['date_planifiee'] ?? null)) ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($r['date_intervention'] ?? null)) ?></td>
                                        <td><span class="badge badge-normal"><?= strtoupper(htmlspecialchars($r['statut_intervention'] ?? 'N/A')) ?></span></td>
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
