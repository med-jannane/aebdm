<?php
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$action = $_GET['action'] ?? '';
$intervention_id = isset($_GET['intervention_id']) ? $_GET['intervention_id'] : '';
$user_id = $_SESSION['user_id'];

if (empty($intervention_id)) {
    echo json_encode(['error' => 'ID intervention invalide']);
    exit;
}

if ($action === 'get') {
    // Assuming Users has: nom_complet, role (instead of nom/prenom) based on login.php
    $sql = "SELECT im.id, im.message, im.cree_le, u.nom_complet, u.role, im.expediteur_id
            FROM Intervention_Messages im
            JOIN Users u ON im.expediteur_id = u.id
            WHERE im.intervention_id = ?
            ORDER BY im.cree_le ASC";
    
    $stmt = sqlsrv_query($conn, $sql, [$intervention_id]);
    if ($stmt === false) {
        error_log('[API_CHAT_INTERVENTION_GET] ' . db_last_error_message());
        echo json_encode(['error' => 'Erreur SGBD']);
        exit;
    }
    
    $messages = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $messages[] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'date' => $row['cree_le'] ? $row['cree_le']->format('d/m/Y H:i') : '',
            'auteur' => $row['nom_complet'],
            'role' => $row['role'],
            'is_mine' => ($row['expediteur_id'] == $_SESSION['user_id'])
        ];
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

if ($action === 'post') {
    $data = json_decode(file_get_contents('php://input'), true);
    $message = trim($data['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['error' => 'Message vide']);
        exit;
    }
    
    $new_id = uniqid('MSG-');
    $sql = "INSERT INTO Intervention_Messages (id, intervention_id, expediteur_id, message, cree_le)
            VALUES (?, ?, ?, ?, GETDATE())";
    
    $stmt = sqlsrv_query($conn, $sql, [$new_id, $intervention_id, $user_id, $message]);
    
    if ($stmt) {
        require_once __DIR__ . '/../../utils/NotificationManager.php';
        $nm = new NotificationManager($conn);
        
        $inv_req = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT tech_id FROM Interventions WHERE id = ?", [$intervention_id]), SQLSRV_FETCH_ASSOC);
        $tech_id = $inv_req['tech_id'] ?? null;
        $sender_role = $_SESSION['role'] ?? '';
        
        if ($sender_role === 'tech') {
            // Notify dispatch
            $link = "/sav/src/modules/dispatch/interventions_list.php?open_chat=" . $intervention_id;
            $nm->create("Nouveau message du technicien (Intervention #$intervention_id)", 'dispatch', null, $link);
        } else {
            // Notify the assigned tech and open the active intervention chat directly.
            if ($tech_id) {
                $link = "/sav/src/modules/tech/dashboard.php?open_chat=" . $intervention_id;
                $nm->create("Nouveau message du Dispatch (Intervention #$intervention_id)", null, $tech_id, $link);
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Erreur lors de l\'envoi']);
    }
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
?>
