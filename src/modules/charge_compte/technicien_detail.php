<?php
require_once __DIR__ . '/_bootstrap.php';

$flags = cc_schema_flags();
$techId = trim($_GET['id'] ?? '');
if ($techId === '') {
    die('Technicien introuvable.');
}

$params = [$techId];
$sqlTech = "SELECT id, nom, nom_complet, " . (!empty($flags['has_phone']) ? 'telephone' : "''") . " AS telephone, " . (!empty($flags['has_region']) ? 'region' : "''") . " AS region";
if (!empty($flags['has_last_login'])) {
    $sqlTech .= ', last_login';
}
$sqlTech .= " FROM Users WHERE id = ?";

if (!empty($flags['has_manager'])) {
    $sqlTech .= " AND manager_id = ?";
    $params[] = $cc_id;
}

$tech = sqlsrv_fetch_array(query($sqlTech, $params), SQLSRV_FETCH_ASSOC);
if (!$tech) {
    die('Ce technicien n\'est pas assigne a votre compte.');
}

$subjectExpr = cc_ticket_subject_sql($flags);
$interventions = fetchAll(query("SELECT TOP 30
    I.id,
    I.statut,
    I.date_planifiee,
    I.date_intervention,
    I.instructions,
    T.ID_TICKET AS ticket_id,
    $subjectExpr AS sujet,
    C.Nom AS client_nom,
    S.Nom AS site_nom,
    S.Ville AS ville
FROM Interventions I
LEFT JOIN TICKET T ON T.ID_TICKET = I.ticket_id
LEFT JOIN SAV_Clients C ON C.ID_Client = T.ID_CLIENT
LEFT JOIN SAV_Sites S ON S.Id_Site = T.ID_SITE
WHERE I.tech_id = ?
ORDER BY COALESCE(I.date_intervention, I.date_planifiee) DESC", [$techId]));

$lastConnection = null;
$logs = [];
if (!empty($flags['has_system_logs'])) {
    $lastRow = sqlsrv_fetch_array(query("SELECT TOP 1 created_at
        FROM SystemLogs
        WHERE action LIKE '%connexion%'
          AND (description LIKE ? OR CONVERT(NVARCHAR(100), user_id) = ?)
        ORDER BY created_at DESC", ['%' . ($tech['nom'] ?? $tech['nom_complet']) . '%', $techId]), SQLSRV_FETCH_ASSOC);
    $lastConnection = $lastRow['created_at'] ?? null;

    $logs = fetchAll(query("SELECT TOP 20 created_at, action, description
        FROM SystemLogs
        WHERE description LIKE ? OR CONVERT(NVARCHAR(100), user_id) = ?
        ORDER BY created_at DESC", ['%' . ($tech['nom'] ?? $tech['nom_complet']) . '%', $techId]));
}

$pageTitle = 'Charge de Compte - Detail Technicien';
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
                    <h1><?= htmlspecialchars($tech['nom_complet']) ?></h1>
                    <p class="text-muted text-sm">Telephone: <?= htmlspecialchars($tech['telephone'] ?: '—') ?> | Region: <?= htmlspecialchars($tech['region'] ?: '—') ?></p>
                </div>
            </div>
            <div class="hdr-actions"><a href="techniciens.php" class="btn btn-sm btn-secondary">Retour</a></div>
        </header>

        <div class="page-content">
            <div class="kpi-grid">
                <div class="kpi-card"><strong><?= count($interventions) ?></strong><span>Interventions (TOP 30)</span></div>
                <div class="kpi-card"><strong><?= htmlspecialchars(cc_format_date($lastConnection, 'd/m/Y H:i')) ?></strong><span>Derniere connexion detectee</span></div>
                <div class="kpi-card"><strong><?= htmlspecialchars(cc_format_date($tech['last_login'] ?? null, 'd/m/Y H:i')) ?></strong><span>Dernier login (Users.last_login)</span></div>
            </div>

            <div class="card">
                <h3>Historique interventions</h3>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ticket</th>
                                <th>Sujet</th>
                                <th>Client / Site</th>
                                <th>Date prevue</th>
                                <th>Date reelle</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($interventions)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Aucune intervention.</td></tr>
                            <?php else: ?>
                                <?php foreach ($interventions as $i): ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($i['id'] ?? '—') ?></td>
                                        <td>#<?= htmlspecialchars($i['ticket_id'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($i['sujet'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(($i['client_nom'] ?? '—') . ' / ' . ($i['site_nom'] ?? '—')) ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($i['date_planifiee'] ?? null)) ?></td>
                                        <td><?= htmlspecialchars(cc_format_date($i['date_intervention'] ?? null)) ?></td>
                                        <td><span class="badge badge-normal"><?= strtoupper(htmlspecialchars($i['statut'] ?? 'N/A')) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3>Logs d'activite (SystemLogs)</h3>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Date</th><th>Action</th><th>Description</th></tr></thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Aucun log disponible pour ce technicien.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(cc_format_date($l['created_at'] ?? null)) ?></td>
                                        <td><?= htmlspecialchars($l['action'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($l['description'] ?? '—') ?></td>
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
