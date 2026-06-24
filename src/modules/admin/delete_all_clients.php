<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['admin', 'directeur']);

$pageTitle = 'Suppression Clients';
$messageType = null;
$messageHtml = '';
$steps = [];

function table_exists($conn, $tableName) {
    $sql = "SELECT OBJECT_ID('dbo." . $tableName . "', 'U') as oid";
    $stmt = sqlsrv_query($conn, $sql);
    if (!$stmt) return false;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return !empty($row['oid']);
}

function count_table($conn, $tableName) {
    if (!table_exists($conn, $tableName)) return 0;
    $stmt = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM " . $tableName);
    if (!$stmt) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}

$totalClients = count_table($conn, 'SAV_Clients');
$totalSites = count_table($conn, 'SAV_Sites');
$totalContrats = count_table($conn, 'CONTRAT');
$totalTickets = count_table($conn, 'TICKET');
$totalInterventions = count_table($conn, 'Interventions');

if (isset($_GET['confirm']) && $_GET['confirm'] === 'oui') {
    $deepReset = isset($_GET['deep']) && $_GET['deep'] === '1';

    if ($deepReset) {
        $queries = [
            ['Interventions', "DELETE FROM Interventions"],
            ['TICKET', "DELETE FROM TICKET"],
            ['CONTRAT_SITE', "DELETE FROM CONTRAT_SITE"],
            ['CONTRAT', "DELETE FROM CONTRAT"],
            ['SAV_Sites', "DELETE FROM SAV_Sites"],
            ['SAV_Clients', "DELETE FROM SAV_Clients"],
        ];
    } else {
        $queries = [
            ['SAV_Clients', "DELETE FROM SAV_Clients"],
        ];
    }

    $okAll = true;
    foreach ($queries as $q) {
        [$tableName, $sql] = $q;
        if (!table_exists($conn, $tableName)) {
            $steps[] = "Table absente: $tableName (ignoree)";
            continue;
        }

        $res = sqlsrv_query($conn, $sql);
        if ($res) {
            $steps[] = "Suppression OK: $tableName";
        } else {
            $okAll = false;
            $steps[] = "Echec: $tableName";
            $messageType = 'error';
            error_log('[ADMIN_DELETE_ALL_CLIENTS_' . $tableName . '] ' . db_last_error_message());
            $messageHtml = 'Erreur interne lors de la suppression des donnees.';
            break;
        }
    }

    if ($okAll) {
        $messageType = 'success';
        if ($deepReset) {
            $messageHtml = 'Reset complet effectue (clients, sites, contrats, tickets et interventions).';
        } else {
            $messageHtml = 'Suppression des clients effectuee.';
        }
        $totalClients = count_table($conn, 'SAV_Clients');
        $totalSites = count_table($conn, 'SAV_Sites');
        $totalContrats = count_table($conn, 'CONTRAT');
        $totalTickets = count_table($conn, 'TICKET');
        $totalInterventions = count_table($conn, 'Interventions');
    }
}
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
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="mobile-toggle" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
                <h1>Suppression des Clients</h1>
            </div>
            <a href="../commercial/clients.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour Clients</a>
        </header>

        <div class="page-content" style="max-width: 980px;">
            <?php if ($messageType === 'success'): ?>
                <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i><span><?= $messageHtml ?></span></div>
            <?php elseif ($messageType === 'error'): ?>
                <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= $messageHtml ?></span></div>
            <?php endif; ?>

            <?php if (!empty($steps)): ?>
                <div class="card">
                    <h3 style="margin-bottom:8px;"><i class="fa-solid fa-list-check"></i> Journal d'execution</h3>
                    <ul style="padding-left:18px; color:var(--text-sub);">
                        <?php foreach ($steps as $s): ?>
                            <li><?= htmlspecialchars($s) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="kpi-grid">
                <div class="kpi-card"><div class="kpi-body"><strong><?= $totalClients ?></strong><span>Clients</span></div></div>
                <div class="kpi-card"><div class="kpi-body"><strong><?= $totalSites ?></strong><span>Sites</span></div></div>
                <div class="kpi-card"><div class="kpi-body"><strong><?= $totalContrats ?></strong><span>Contrats</span></div></div>
                <div class="kpi-card"><div class="kpi-body"><strong><?= $totalTickets ?></strong><span>Tickets</span></div></div>
                <div class="kpi-card"><div class="kpi-body"><strong><?= $totalInterventions ?></strong><span>Interventions</span></div></div>
            </div>

            <div class="card">
                <div class="sec-head">
                    <h3><i class="fa-solid fa-circle-exclamation text-danger"></i> Action Sensible</h3>
                    <span class="badge badge-danger">Irreversible</span>
                </div>

                <p style="margin-bottom:10px; color:var(--text-sub);">
                    Suppression clients uniquement: tentative de suppression de SAV_Clients uniquement.
                </p>
                <p style="margin-bottom:14px; color:var(--text-sub);">
                    Reset complet: supprime aussi les donnees liees (tickets/interventions/contrats/sites) puis les clients.
                </p>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:18px;">
                    <a href="?confirm=oui" class="btn btn-danger">
                        <i class="fa-solid fa-trash"></i>
                        Supprimer seulement les clients
                    </a>
                    <a href="?confirm=oui&deep=1" class="btn btn-danger">
                        <i class="fa-solid fa-bomb"></i>
                        Reset complet import (clients/sites/contrats/tickets)
                    </a>
                    <a href="import_csv.php?type=clients" class="btn">
                        <i class="fa-solid fa-file-import"></i>
                        Aller vers Import Clients
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
