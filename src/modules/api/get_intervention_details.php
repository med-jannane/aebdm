<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['error' => 'ID intervention invalide']);
    exit;
}

$sql = "SELECT rapport, travaux_recommandes FROM Interventions WHERE id = ?";
$stmt = query($sql, [$id]);
$inter = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($inter) {
    $texte = "";
    if (!empty($inter['rapport'])) {
        $texte .= trim($inter['rapport']) . "\n";
    }
    if (!empty($inter['travaux_recommandes'])) {
        $texte .= "\nRecommandations:\n" . trim($inter['travaux_recommandes']);
    }
    
    echo json_encode(['success' => true, 'rapport' => trim($texte)]);
} else {
    echo json_encode(['error' => 'Intervention introuvable']);
}
?>
