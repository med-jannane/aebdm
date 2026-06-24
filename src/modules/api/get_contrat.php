<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'client_id manquant.']);
    exit;
}

$client_id = $_GET['client_id'];

// Get latest contract for client (robuste import: ID_CLIENT ou Code_Client)
$sql = "SELECT TOP 1 *
    FROM CONTRAT
    WHERE LTRIM(RTRIM(ISNULL(ID_CLIENT, ''))) = LTRIM(RTRIM(?))
       OR LTRIM(RTRIM(ISNULL(Code_Client, ''))) = LTRIM(RTRIM(?))
    ORDER BY Date_Fin DESC";
$stmt = sqlsrv_query($conn, $sql, [$client_id, $client_id]);

if ($stmt && $contrat = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Determine status based on dates
    $now = new DateTime();
    $status = 'actif';
    
    // Check Date_Debut
    $dateDebutObj = null;
    if (!empty($contrat['Date_Debut'])) {
        $dateDebutObj = ($contrat['Date_Debut'] instanceof DateTime) ? $contrat['Date_Debut'] : new DateTime((string)$contrat['Date_Debut']);
        if ($now < $dateDebutObj) {
            $status = 'Non commencé';
            $isActive = false;
        }
    }
    // Check Date_Fin
    $dateFinObj = null;
    if (!empty($contrat['Date_Fin'])) {
        $dateFinObj = ($contrat['Date_Fin'] instanceof DateTime) ? $contrat['Date_Fin'] : new DateTime((string)$contrat['Date_Fin']);
        if ($now > $dateFinObj) {
            $status = 'termine';
        }
    }
    
    // Check user role for 'montant' visibility
    $userRole = $_SESSION['role'] ?? '';
    $canViewMontant = in_array(strtolower($userRole), ['commercial', 'directeur', 'admin']);

    // Prepare data
    $contratData = [];
    foreach($contrat as $key => $value) {
        if (strtolower($key) === 'montant' && !$canViewMontant) {
            $contratData[$key] = '*** (Masqué)';
            continue;
        }
        
        if ($value instanceof DateTime) {
            $contratData[$key] = $value->format('d/m/Y');
        } else {
            $contratData[$key] = $value;
        }
    }

    echo json_encode([
        'success' => true, 
        'contrat' => array_merge($contratData, [
            'status' => $status,
            'statut_badge' => ($status === 'actif' ? 'success' : ($status === 'avenir' ? 'warning' : 'danger'))
        ])
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Aucun contrat trouvé pour ce client.']);
}
?>
