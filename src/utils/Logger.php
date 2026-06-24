<?php
require_once __DIR__ . '/../../config/db.php';

class Logger {
    public static function log($action, $description = '', $user_id = null) {
        global $conn;
        
        // S'assurer que la table existe
        $sqlCheck = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='SystemLogs' and xtype='U')
                     CREATE TABLE SystemLogs (
                         id INT IDENTITY(1,1) PRIMARY KEY,
                         action NVARCHAR(255) NOT NULL,
                         description NVARCHAR(MAX),
                         user_id INT NULL,
                         created_at DATETIME DEFAULT GETDATE()
                     )";
        sqlsrv_query($conn, $sqlCheck);

        // Insérer le log
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }

        $sql = "INSERT INTO SystemLogs (action, description, user_id) VALUES (?, ?, ?)";
        sqlsrv_query($conn, $sql, [$action, $description, $user_id]);
    }

    public static function getLogs($limit = 100) {
        global $conn;
        $sql = "SELECT L.*, U.nom_complet as user_name, U.role as user_role 
                FROM SystemLogs L
                LEFT JOIN Users U ON L.user_id = U.id
                ORDER BY L.created_at DESC";
                // NOTE: Limit not supported the MySQL way. In SQL Server it's SELECT TOP $limit ...
        
        $sqlTop = "SELECT TOP $limit L.*, U.nom_complet as user_name, U.role as user_role 
                   FROM SystemLogs L
                   LEFT JOIN Users U ON L.user_id = U.id
                   ORDER BY L.created_at DESC";

        $stmt = sqlsrv_query($conn, $sqlTop);
        $logs = [];
        if ($stmt) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $logs[] = $row;
            }
        }
        return $logs;
    }
}
?>
