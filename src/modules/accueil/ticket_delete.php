<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';
check_role(['accueil', 'admin']);

if (csrf_validate_request()) {
    $ticketId = trim((string)($_POST['id'] ?? ''));
    if ($ticketId !== '') {
        sqlsrv_query($conn, "DELETE FROM TICKET WHERE ID_TICKET = ?", [$ticketId]);
    }
}
header("Location: tickets.php");
exit;
