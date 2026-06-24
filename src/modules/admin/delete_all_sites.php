<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['admin', 'directeur']);

$pageTitle = 'Suppression Sites';
$messageType = null;
$messageHtml = '';

$cntStmt = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM SAV_Sites");
$countRow = $cntStmt ? sqlsrv_fetch_array($cntStmt, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$totalSites = (int)($countRow['total'] ?? 0);

if (isset($_GET['confirm']) && $_GET['confirm'] === 'oui') {
    $detachTickets = isset($_GET['detach']) && $_GET['detach'] === '1';

    if ($detachTickets) {
        @sqlsrv_query($conn, "UPDATE TICKET SET ID_SITE = NULL WHERE ID_SITE IS NOT NULL");
    }

    $result = sqlsrv_query($conn, "DELETE FROM SAV_Sites");
    if ($result) {
        $messageType = 'success';
        $messageHtml = "<strong>$totalSites sites</strong> ont ete supprimes avec succes. Vous pouvez relancer l'import des sites.";
        $totalSites = 0;
    } else {
        $messageType = 'error';
        error_log('[ADMIN_DELETE_ALL_SITES] ' . db_last_error_message());
        $messageHtml = 'Erreur interne lors de la suppression des sites.';
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
                <h1>Suppression des Sites</h1>
            </div>
            <a href="../commercial/clients.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour Clients</a>
        </header>

        <div class="page-content" style="max-width: 980px;">
            <?php if ($messageType === 'success'): ?>
                <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i><span><?= $messageHtml ?></span></div>
            <?php elseif ($messageType === 'error'): ?>
                <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= $messageHtml ?></span></div>
            <?php endif; ?>

            <div class="card">
                <div class="sec-head">
                    <h3><i class="fa-solid fa-circle-exclamation text-danger"></i> Action Sensible</h3>
                    <span class="badge badge-danger">Irreversible</span>
                </div>

                <p style="margin-bottom:10px; color:var(--text-sub);">
                    Cette action va supprimer tous les sites importes.
                </p>
                <p style="margin-bottom:18px; color:var(--text-sub);">
                    Sites detectes actuellement: <strong><?= $totalSites ?></strong>
                </p>

                <div class="alert alert-warning" style="margin-bottom:14px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Si des tickets referencent des sites, activez l'option de detachement avant suppression.</span>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:18px;">
                    <a href="?confirm=oui" class="btn btn-danger">
                        <i class="fa-solid fa-trash"></i>
                        Supprimer tous les sites
                    </a>
                    <a href="?confirm=oui&detach=1" class="btn btn-danger">
                        <i class="fa-solid fa-link-slash"></i>
                        Detacher tickets puis supprimer
                    </a>
                    <a href="import_csv.php?type=sites" class="btn">
                        <i class="fa-solid fa-file-import"></i>
                        Aller vers Import Sites
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
