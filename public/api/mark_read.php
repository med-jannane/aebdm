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

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$user_id = $_SESSION['user_id'];
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));

$notificationManager = new NotificationManager($conn);

if ($id === 'all') {
    $stmt = $notificationManager->markAllAsReadForUser($role, $user_id);
    if ($stmt !== false) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} elseif (!empty($id)) {
    $stmt = $notificationManager->markAsReadForUser($id, $role, $user_id);
    if ($stmt !== false) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID']);
}
