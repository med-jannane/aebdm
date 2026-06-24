<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/NotificationManager.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$userId = $_SESSION['user_id'];

$notificationManager = new NotificationManager($conn);
$result = $notificationManager->getRecentForUser($role, $userId, 30);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$notifications = [];
$unreadCount = 0;

while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $isRead = isset($row['is_read']) ? (int)$row['is_read'] : 0;
    if ($isRead === 0) {
        $unreadCount++;
    }

    $notifications[] = [
        'id' => $row['id'],
        'message' => (string)($row['message'] ?? ''),
        'link' => (string)($row['link'] ?? ''),
        'is_read' => $isRead,
        'created_at' => isset($row['created_at']) && $row['created_at'] instanceof DateTime
            ? $row['created_at']->format('Y-m-d H:i:s')
            : ''
    ];
}

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);
