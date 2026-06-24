<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role(['admin', 'directeur']);

$pageTitle = 'Diagnostic CONTRAT';

$columns = [];
$sampleRows = [];
$errors = [];

$cols = sqlsrv_query(
    $conn,
    "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_NAME = 'CONTRAT'
     ORDER BY ORDINAL_POSITION"
);

if ($cols) {
    while ($col = sqlsrv_fetch_array($cols, SQLSRV_FETCH_ASSOC)) {
        $columns[] = $col;
    }
} else {
    error_log('[ADMIN_CHECK_CONTRATS_COLUMNS] ' . db_last_error_message());
    $errors[] = 'Erreur interne lors de la lecture des colonnes.';
}

$data = sqlsrv_query($conn, "SELECT TOP 5 * FROM CONTRAT");
if ($data) {
    while ($row = sqlsrv_fetch_array($data, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $k => $v) {
            if ($v instanceof DateTime) {
                $row[$k] = $v->format('Y-m-d');
            }
        }
        $sampleRows[] = $row;
    }
} else {
    error_log('[ADMIN_CHECK_CONTRATS_DATA] ' . db_last_error_message());
    $errors[] = 'Erreur interne lors de la lecture des donnees.';
}

$total = 0;
$cntStmt = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM CONTRAT");
if ($cntStmt && ($cnt = sqlsrv_fetch_array($cntStmt, SQLSRV_FETCH_ASSOC))) {
    $total = (int)$cnt['total'];
}

$stats = [
    'null_debut' => 0,
    'null_fin' => 0,
    'null_montant' => 0,
    'null_type' => 0,
    'total' => $total,
];

$statsStmt = sqlsrv_query(
    $conn,
    "SELECT
        SUM(CASE WHEN Date_Debut IS NULL THEN 1 ELSE 0 END) as null_debut,
        SUM(CASE WHEN Date_Fin IS NULL THEN 1 ELSE 0 END) as null_fin,
        SUM(CASE WHEN Montant_Contrat IS NULL THEN 1 ELSE 0 END) as null_montant,
        SUM(CASE WHEN TYPE IS NULL OR TYPE = '' THEN 1 ELSE 0 END) as null_type,
        COUNT(*) as total
     FROM CONTRAT"
);

if ($statsStmt && ($rowStats = sqlsrv_fetch_array($statsStmt, SQLSRV_FETCH_ASSOC))) {
    $stats = $rowStats;
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
                <h1>Diagnostic Table CONTRAT</h1>
            </div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        </header>

        <div class="page-content">
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($err) ?></span>
                </div>
            <?php endforeach; ?>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-body">
                        <strong><?= (int)$stats['total'] ?></strong>
                        <span>Contrats Totaux</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-body">
                        <strong><?= (int)$stats['null_fin'] ?></strong>
                        <span>Date Fin NULL</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-body">
                        <strong><?= (int)$stats['null_montant'] ?></strong>
                        <span>Montant NULL</span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-body">
                        <strong><?= (int)$stats['null_type'] ?></strong>
                        <span>Type Vide / NULL</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="sec-head">
                    <h3><i class="fa-solid fa-table-columns"></i> Colonnes de CONTRAT</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Colonne</th>
                                <th>Type</th>
                                <th>Max Length</th>
                                <th>Nullable</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($columns as $col): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($col['COLUMN_NAME']) ?></strong></td>
                                    <td><?= htmlspecialchars($col['DATA_TYPE']) ?></td>
                                    <td><?= htmlspecialchars((string)($col['CHARACTER_MAXIMUM_LENGTH'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars($col['IS_NULLABLE']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="sec-head">
                    <h3><i class="fa-solid fa-magnifying-glass-chart"></i> Echantillon (TOP 5)</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <?php if (!empty($sampleRows)): ?>
                                    <?php foreach (array_keys($sampleRows[0]) as $key): ?>
                                        <th><?= htmlspecialchars($key) ?></th>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <th>Aucune donnée</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sampleRows)): ?>
                                <tr>
                                    <td>Aucun contrat disponible.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sampleRows as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td>
                                                <?php if ($value === null): ?>
                                                    <span class="badge badge-danger">NULL</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars((string)$value) ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
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
