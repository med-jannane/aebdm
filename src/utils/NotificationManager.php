<?php
class NotificationManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function normalizeRole($role) {
        if ($role === null) {
            return null;
        }
        $role = strtolower(trim((string)$role));
        return $role === '' ? null : $role;
    }

    public function create($message, $targetRole = null, $targetUserId = null, $link = null) {
        $targetRole = $this->normalizeRole($targetRole);
        $sql = "INSERT INTO dbo.Notifications (message, role_target, user_id, link) VALUES (?, ?, ?, ?)";
        $params = [$message, $targetRole, $targetUserId, $link];
        return sqlsrv_query($this->conn, $sql, $params);
    }

    public function getUnread($role, $userId) {
        $role = $this->normalizeRole($role);
        // Get generic role notifications OR specific user notifications
        $sql = "SELECT * FROM dbo.Notifications 
                WHERE is_read = 0 
                AND (
                    (LOWER(LTRIM(RTRIM(ISNULL(role_target, '')))) = ? AND user_id IS NULL) 
                    OR 
                    (user_id = ?)
                )
                ORDER BY created_at DESC";
        $params = [$role, $userId];
        return sqlsrv_query($this->conn, $sql, $params);
    }

    public function getRecentForUser($role, $userId, $limit = 30) {
        $role = $this->normalizeRole($role);
        $safeLimit = (int)$limit;
        if ($safeLimit <= 0) {
            $safeLimit = 30;
        }
        if ($safeLimit > 200) {
            $safeLimit = 200;
        }

        $sql = "SELECT TOP ($safeLimit) id, message, link, is_read, created_at
            FROM dbo.Notifications
                WHERE (
                    (LOWER(LTRIM(RTRIM(ISNULL(role_target, '')))) = ? AND user_id IS NULL)
                    OR
                    (user_id = ?)
                )
                ORDER BY created_at DESC";
        return sqlsrv_query($this->conn, $sql, [$role, $userId]);
    }

    public function markAllAsReadForUser($role, $userId) {
        $role = $this->normalizeRole($role);
        $sql = "UPDATE dbo.Notifications
                SET is_read = 1
                WHERE is_read = 0
                AND (
                    (LOWER(LTRIM(RTRIM(ISNULL(role_target, '')))) = ? AND user_id IS NULL)
                    OR
                    (user_id = ?)
                )";
        return sqlsrv_query($this->conn, $sql, [$role, $userId]);
    }
    
    public function markAsReadForUser($id, $role, $userId) {
        $role = $this->normalizeRole($role);
        $sql = "UPDATE dbo.Notifications
                SET is_read = 1
                WHERE id = ?
                AND (
                    (LOWER(LTRIM(RTRIM(ISNULL(role_target, '')))) = ? AND user_id IS NULL)
                    OR
                    (user_id = ?)
                )";
        return sqlsrv_query($this->conn, $sql, [$id, $role, $userId]);
    }
}
