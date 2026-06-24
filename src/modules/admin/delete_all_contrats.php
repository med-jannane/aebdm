<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['admin', 'directeur']);

$pageTitle = 'Suppression Contrats';
$messageType = null;
$messageHtml = '';

$cntStmt = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM CONTRAT");
$countRow = $cntStmt ? sqlsrv_fetch_array($cntStmt, SQLSRV_FETCH_ASSOC) : ['total' => 0];
$totalContrats = (int)($countRow['total'] ?? 0);

if (isset($_GET['confirm']) && $_GET['confirm'] === 'oui') {
    $result = sqlsrv_query($conn, "DELETE FROM CONTRAT");
    if ($result) {
        $messageType = 'success';
        $messageHtml = "<strong>$totalContrats contrats</strong> ont ete supprimes avec succes. Vous pouvez relancer un import CSV.";
        $totalContrats = 0;
    } else {
        $messageType = 'error';
        error_log('[ADMIN_DELETE_ALL_CONTRATS] ' . db_last_error_message());
        $messageHtml = 'Erreur interne lors de la suppression des contrats.';
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
                <h1>Suppression des Contrats</h1>
            </div>
            <a href="../commercial/contrats.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour Contrats</a>
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
                    <span class="badge badge-danger">Irréversible</span>
                </div>

                <p style="margin-bottom:10px; color:var(--text-sub);">
                    Cette action va supprimer tous les contrats de la base de donnees.
                </p>
                <p style="margin-bottom:18px; color:var(--text-sub);">
                    Contrats detectes actuellement: <strong><?= $totalContrats ?></strong>
                </p>

                <div class="grid-2">
                    <div class="card" style="padding:14px; border:1px dashed var(--border-strong);">
                        <h4 style="margin-bottom:8px;">Avant de continuer</h4>
                        <ul style="padding-left:18px; color:var(--text-sub);">
                            <li>Verifier que vous avez une sauvegarde.</li>
                            <li>Informer les equipes commerciales.</li>
                            <li>Planifier l'import CSV immediatement apres.</li>
                        </ul>
                    </div>
                    <div class="card" style="padding:14px; border:1px dashed var(--border-strong);">
                        <h4 style="margin-bottom:8px;">Apres suppression</h4>
                        <ul style="padding-left:18px; color:var(--text-sub);">
                            <li>Relancer l'import contrats.</li>
                            <li>Verifier les liaisons client/site/contrat.</li>
                            <li>Tester la creation ticket et la selection contrat.</li>
                        </ul>
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:18px;">
                    <a href="?confirm=oui" class="btn btn-danger">
                        <i class="fa-solid fa-trash"></i>
                        Oui, supprimer tous les contrats
                    </a>
                    <a href="../commercial/contrats.php" class="btn btn-secondary">
                        <i class="fa-solid fa-ban"></i>
                        Annuler
                    </a>
                    <a href="import_csv.php?type=contrats" class="btn">
                        <i class="fa-solid fa-file-import"></i>
                        Aller vers l'import CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
