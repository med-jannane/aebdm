<?php

// Configuration de la base de donnees (sans .env)
$serverName = 'localhost\\SQLEXPRESS';
$dbPort = '';
$dbHost = '';

$dbName = 'SAV_DB';
$dbUser = '';
$dbPassword = '';
$dbTrustServerCertificate = true;
$dbEncrypt = true;

$connectionOptions = [
    "Database" => $dbName,
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => $dbTrustServerCertificate,
    "Encrypt" => $dbEncrypt,
    "LoginTimeout" => 5,
];

// Si DB_USER est vide, on laisse l'authentification Windows SQL Server.
if ($dbUser !== '') {
    if ($dbPassword === '') {
        throw new InvalidArgumentException('DB_PASSWORD est requis lorsque DB_USER est configure.');
    }
    $connectionOptions['Uid'] = $dbUser;
    $connectionOptions['PWD'] = $dbPassword;
}

// Etablir la connexion
function build_sqlsrv_server_targets(string $serverName, string $dbHost, string $dbPort): array {
    $targets = [];

    if ($serverName !== '') {
        $targets[] = $serverName;
        // Also try server name without instance (e.g., AERO-SRV) if instance part present
        if (strpos($serverName, '\\') !== false) {
            $base = strstr($serverName, '\\', true);
            if ($base !== false && $base !== '') {
                $targets[] = $base;
            }
        }
    }

    if ($dbHost !== '') {
        if ($dbPort !== '') {
            $targets[] = 'tcp:' . $dbHost . ',' . $dbPort;
        }
        $targets[] = $dbHost;
    }

    if (strpos($serverName, '\\') !== false) {
        $hostOnly = strstr($serverName, '\\', true);
        if ($hostOnly !== false && $hostOnly !== '') {
            if ($dbPort !== '') {
                $targets[] = 'tcp:' . $hostOnly . ',' . $dbPort;
            }
            $targets[] = $hostOnly;
        }
    }

    return array_values(array_unique(array_filter($targets, static fn($target) => $target !== '')));
}

$conn = false;
$connectionErrors = [];

$maxAttempts = 3; // avoid long hangs
$attempts = 0;
foreach (build_sqlsrv_server_targets($serverName, $dbHost, $dbPort) as $serverTarget) {
    $attempts++;
    $conn = sqlsrv_connect($serverTarget, $connectionOptions);
    if ($conn !== false) {
        break;
    }
    $connectionErrors[] = $serverTarget . ' => ' . db_last_error_message();
    if ($attempts >= $maxAttempts) {
        // stop trying further targets
        break;
    }
}
// No fallback to localhost; rely on configured targets only

if ($conn === false) {
    error_log('[DB_CONNECT_ERROR] ' . implode(' || ', $connectionErrors));
    http_response_code(500);
    exit('Erreur interne. Veuillez contacter l\'administrateur.');
}

class DatabaseOperationException extends RuntimeException {}

// Fonction helper pour exécuter des requêtes
function query($sql, $params = []) {
    global $conn;
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log('[DB_QUERY_ERROR] ' . db_last_error_message());
        throw new DatabaseOperationException('Erreur interne de base de donnees.');
    }
    return $stmt;
}

function db_last_error_message() {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!is_array($errors) || count($errors) === 0) {
        return 'Unknown SQLSRV error';
    }

    $parts = [];
    foreach ($errors as $err) {
        $sqlState = isset($err['SQLSTATE']) ? (string)$err['SQLSTATE'] : 'N/A';
        $code = isset($err['code']) ? (string)$err['code'] : 'N/A';
        $message = isset($err['message']) ? trim((string)$err['message']) : 'No message';
        $parts[] = "SQLSTATE={$sqlState}; Code={$code}; Message={$message}";
    }

    return implode(' | ', $parts);
}

// Fonction helper pour fetcher les résultats
function fetchAll($stmt) {
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    return $results;
}

function isValidSqlIdentifier($identifier) {
    return is_string($identifier)
        && preg_match('/^[A-Za-z_]\w*(\.[A-Za-z_]\w*)?$/', $identifier);
}

function ensureCodeSequenceTable() {
    global $conn;
    $sql = "
        IF OBJECT_ID('dbo.CodeSequences', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.CodeSequences (
                entity NVARCHAR(50) NOT NULL PRIMARY KEY,
                last_value BIGINT NOT NULL
            );
        END
    ";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        throw new DatabaseOperationException('Unable to create/load CodeSequences table.');
    }
}

function getMaxNumericCodeValue($table, $column) {
    if (!isValidSqlIdentifier($table) || !isValidSqlIdentifier($column)) {
        throw new InvalidArgumentException('Invalid table or column identifier.');
    }

    $stmt = query("SELECT $column AS code_value FROM $table WHERE $column IS NOT NULL");
    $maxNumeric = 0;

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $raw = trim((string)($row['code_value'] ?? ''));
        if ($raw === '') {
            continue;
        }

        if (!preg_match('/(\d+)$/', $raw, $matches)) {
            preg_match_all('/\d+/', $raw, $groups);
            if (empty($groups[0])) {
                continue;
            }
            $digits = implode('', $groups[0]);
        } else {
            $digits = $matches[1];
        }

        $numericValue = (int)$digits;
        if ($numericValue > $maxNumeric) {
            $maxNumeric = $numericValue;
        }
    }

    return $maxNumeric;
}

function getNextSequentialCode($entity, $table, $column, $defaultStart = 1, $minLength = 1, $prefix = '') {
    global $conn;
    $entity = trim((string)$entity);
    if ($entity === '') {
        throw new InvalidArgumentException('Sequence entity cannot be empty.');
    }

    $defaultStart = (int)$defaultStart;
    $minLength = max(1, (int)$minLength);

    if (!sqlsrv_begin_transaction($conn)) {
        throw new DatabaseOperationException('Unable to start transaction for sequence generation.');
    }

    try {
        ensureCodeSequenceTable();

        $stmt = sqlsrv_query(
            $conn,
            "SELECT last_value FROM dbo.CodeSequences WITH (UPDLOCK, HOLDLOCK) WHERE entity = ?",
            [$entity]
        );

        if ($stmt === false) {
            throw new DatabaseOperationException('Unable to lock sequence row.');
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row) {
            $lastValue = (int)$row['last_value'];
        } else {
            $maxExisting = getMaxNumericCodeValue($table, $column);
            $lastValue = max($defaultStart - 1, $maxExisting);

            $insertStmt = sqlsrv_query(
                $conn,
                "INSERT INTO dbo.CodeSequences (entity, last_value) VALUES (?, ?)",
                [$entity, $lastValue]
            );
            if ($insertStmt === false) {
                throw new DatabaseOperationException('Unable to initialize sequence row.');
            }
        }

        $nextValue = $lastValue + 1;
        $updateStmt = sqlsrv_query(
            $conn,
            "UPDATE dbo.CodeSequences SET last_value = ? WHERE entity = ?",
            [$nextValue, $entity]
        );
        if ($updateStmt === false) {
            throw new DatabaseOperationException('Unable to persist next sequence value.');
        }

        if (!sqlsrv_commit($conn)) {
            throw new DatabaseOperationException('Unable to commit sequence transaction.');
        }

        return $prefix . str_pad((string)$nextValue, $minLength, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        sqlsrv_rollback($conn);
        throw $e;
    }
}

function previewNextSequentialCode($entity, $table, $column, $defaultStart = 1, $minLength = 1, $prefix = '') {
    global $conn;
    $entity = trim((string)$entity);
    if ($entity === '') {
        throw new InvalidArgumentException('Sequence entity cannot be empty.');
    }

    $defaultStart = (int)$defaultStart;
    $minLength = max(1, (int)$minLength);

    ensureCodeSequenceTable();

    $stmt = sqlsrv_query($conn, "SELECT last_value FROM dbo.CodeSequences WHERE entity = ?", [$entity]);
    if ($stmt === false) {
        throw new DatabaseOperationException('Unable to read sequence row.');
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($row) {
        $lastValue = (int)$row['last_value'];
    } else {
        $maxExisting = getMaxNumericCodeValue($table, $column);
        $lastValue = max($defaultStart - 1, $maxExisting);
    }

    $nextValue = $lastValue + 1;
    return $prefix . str_pad((string)$nextValue, $minLength, '0', STR_PAD_LEFT);
}

function ensure_app_settings_table() {
    global $conn;
    $sql = "
        IF OBJECT_ID('dbo.AppSettings', 'U') IS NULL
        BEGIN
            CREATE TABLE dbo.AppSettings (
                setting_key NVARCHAR(100) NOT NULL PRIMARY KEY,
                setting_value NVARCHAR(255) NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT GETDATE()
            );
        END
    ";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        throw new DatabaseOperationException('Unable to create/load AppSettings table.');
    }
}

function get_app_setting($key, $default = null) {
    global $conn;
    ensure_app_settings_table();

    $stmt = sqlsrv_query($conn, "SELECT setting_value FROM dbo.AppSettings WHERE setting_key = ?", [$key]);
    if ($stmt === false) {
        return $default;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row) {
        return $default;
    }

    return (string)$row['setting_value'];
}

function set_app_setting($key, $value) {
    global $conn;
    ensure_app_settings_table();

    $sql = "
        MERGE dbo.AppSettings AS target
        USING (SELECT ? AS setting_key, ? AS setting_value) AS source
        ON target.setting_key = source.setting_key
        WHEN MATCHED THEN
            UPDATE SET setting_value = source.setting_value, updated_at = GETDATE()
        WHEN NOT MATCHED THEN
            INSERT (setting_key, setting_value, updated_at)
            VALUES (source.setting_key, source.setting_value, GETDATE());
    ";

    $stmt = sqlsrv_query($conn, $sql, [$key, (string)$value]);
    return $stmt !== false;
}
