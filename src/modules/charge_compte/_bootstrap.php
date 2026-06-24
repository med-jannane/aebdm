<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

check_role('charge_de_compte');

$cc_id = $_SESSION['user_id'] ?? '';
$cc_nom = $_SESSION['nom_complet'] ?? $_SESSION['nom'] ?? $_SESSION['username'] ?? 'Charge de compte';

function cc_schema_flags() {
    static $flags = null;
    if ($flags !== null) {
        return $flags;
    }

    $row = sqlsrv_fetch_array(query("SELECT
        CASE WHEN COL_LENGTH('Users', 'manager_id') IS NULL THEN 0 ELSE 1 END AS has_manager,
        CASE WHEN COL_LENGTH('Users', 'telephone') IS NULL THEN 0 ELSE 1 END AS has_phone,
        CASE WHEN COL_LENGTH('Users', 'region') IS NULL THEN 0 ELSE 1 END AS has_region,
        CASE WHEN COL_LENGTH('Users', 'last_login') IS NULL THEN 0 ELSE 1 END AS has_last_login,
        CASE WHEN COL_LENGTH('TICKET', 'OBJET') IS NULL THEN 0 ELSE 1 END AS has_objet,
        CASE WHEN COL_LENGTH('TICKET', 'sujet') IS NULL THEN 0 ELSE 1 END AS has_sujet,
        CASE WHEN COL_LENGTH('TICKET', 'COMMENT') IS NULL THEN 0 ELSE 1 END AS has_comment,
        CASE WHEN OBJECT_ID('VP_Tickets', 'U') IS NULL THEN 0 ELSE 1 END AS has_vp_tickets,
        CASE WHEN OBJECT_ID('VP', 'U') IS NULL THEN 0 ELSE 1 END AS has_vp,
        CASE WHEN OBJECT_ID('SystemLogs', 'U') IS NULL THEN 0 ELSE 1 END AS has_system_logs"), SQLSRV_FETCH_ASSOC);

    $flags = [
        'has_manager' => ((int)($row['has_manager'] ?? 0) === 1),
        'has_phone' => ((int)($row['has_phone'] ?? 0) === 1),
        'has_region' => ((int)($row['has_region'] ?? 0) === 1),
        'has_last_login' => ((int)($row['has_last_login'] ?? 0) === 1),
        'has_objet' => ((int)($row['has_objet'] ?? 0) === 1),
        'has_sujet' => ((int)($row['has_sujet'] ?? 0) === 1),
        'has_comment' => ((int)($row['has_comment'] ?? 0) === 1),
        'has_vp_tickets' => ((int)($row['has_vp_tickets'] ?? 0) === 1),
        'has_vp' => ((int)($row['has_vp'] ?? 0) === 1),
        'has_system_logs' => ((int)($row['has_system_logs'] ?? 0) === 1),
    ];

    return $flags;
}

function cc_ticket_subject_sql($flags) {
    if (!empty($flags['has_objet'])) {
        return 'T.OBJET';
    }
    if (!empty($flags['has_sujet'])) {
        return 'T.sujet';
    }
    if (!empty($flags['has_comment'])) {
        return 'T.COMMENT';
    }
    return "'Sans sujet'";
}

function cc_fetch_team($cc_id, $flags) {
    if (empty($flags['has_manager'])) {
        return [];
    }

    $phoneExpr = !empty($flags['has_phone']) ? 'telephone' : "''";
    $regionExpr = !empty($flags['has_region']) ? 'region' : "''";
    $lastExpr = !empty($flags['has_last_login']) ? 'last_login' : 'NULL';

    $sql = "SELECT id, nom, nom_complet, {$phoneExpr} AS telephone, {$regionExpr} AS region, {$lastExpr} AS last_login
            FROM Users
            WHERE manager_id = ?
            ORDER BY nom_complet ASC";

    return fetchAll(query($sql, [$cc_id]));
}

function cc_team_ids($teamRows) {
    return array_values(array_map(static function ($row) {
        return $row['id'];
    }, $teamRows));
}

function cc_placeholders($count) {
    if ($count <= 0) {
        return '';
    }
    return implode(',', array_fill(0, $count, '?'));
}

function cc_format_date($value, $format = 'd/m/Y H:i') {
    if ($value instanceof DateTime) {
        return $value->format($format);
    }
    if (empty($value)) {
        return '—';
    }
    $ts = strtotime((string)$value);
    if ($ts !== false) {
        return date($format, $ts);
    }
    return (string)$value;
}
