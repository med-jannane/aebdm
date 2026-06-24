<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['commercial', 'admin']);

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Vérifier les dépendances (Tickets)
    // Selon le schéma DB installé, la liaison contrat<->ticket peut être:
    // - une colonne Tickets.contrat_id (ancien schéma)
    // - encodée dans Tickets.COMMENT sous la forme "[Contrat: XXX]" (schéma SYSGM / table TICKET)
    $hasContratIdCol = sqlsrv_fetch_array(
        query("SELECT CASE WHEN COL_LENGTH('Tickets', 'contrat_id') IS NULL THEN 0 ELSE 1 END AS has_col"),
        SQLSRV_FETCH_ASSOC
    );

    if (!empty($hasContratIdCol['has_col'])) {
        $check = sqlsrv_fetch_array(
            query("SELECT COUNT(*) as c FROM Tickets WHERE contrat_id = ?", [$id]),
            SQLSRV_FETCH_ASSOC
        );
    } else {
        // Fallback: recherche dans le champ COMMENT
        $like = '%[Contrat: ' . $id . ']%';
        $check = sqlsrv_fetch_array(
            query("SELECT COUNT(*) as c FROM Tickets WHERE COMMENT LIKE ?", [$like]),
            SQLSRV_FETCH_ASSOC
        );
    }
    
    if ($check['c'] > 0) {
        $pageTitle = "Erreur Suppression";
        require_once __DIR__ . '/../../includes/head.php';
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <?php echo $headContent ?? ''; // Fallback if head.php doesn't output directly (it usually does) ?> 
        </head>
        <body>
            <div class="main-content" style="margin-left:0; max-width:600px; margin:50px auto;">
                <div class="card" style="text-align:center; border-left:5px solid var(--danger);">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size:3rem; color:var(--danger); margin-bottom:20px;"></i>
                    <h2 style="color:var(--danger);">Impossible de supprimer ce contrat</h2>
                    <p>Ce contrat est lié à <strong><?php echo $check['c']; ?></strong> ticket(s) existant(s).</p>
                    <p>Veuillez d'abord supprimer ou réassigner les tickets associés.</p>
                    <a href="contrats.php" class="btn btn-secondary" style="margin-top:20px;"><i class="fa-solid fa-arrow-left"></i> Retour aux contrats</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    $sql = "DELETE FROM Contrats WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    
    if ($stmt) {
        header("Location: contrats.php?msg=deleted");
    } else {
        error_log('[COMMERCIAL_CONTRAT_DELETE] ' . db_last_error_message());
        die("Erreur interne lors de la suppression du contrat.");
    }
} else {
    header("Location: contrats.php");
}
exit;

