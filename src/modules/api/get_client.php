<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID manquant']);
    exit;
}

$id = $_GET['id'];
$sql = "SELECT * FROM SAV_Clients WHERE ID_Client = ?";
$stmt = sqlsrv_query($conn, $sql, [$id]);

if ($stmt && $client = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    echo json_encode(['success' => true, 'client' => $client]);
} else {
    echo json_encode(['success' => false, 'error' => 'Client introuvable']);
}
?>
