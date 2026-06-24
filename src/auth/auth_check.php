<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $httpsEnabled = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $httpsEnabled,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Fonction pour vérifier si l'utilisateur est connecté
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /sav/public/login.php");
        exit;
    }
}

// Fonction pour vérifier si l'utilisateur a le bon rôle
// Accepte une chaîne ou un tableau de rôles autorisés
function check_role($allowed_roles) {
    check_auth(); // Vérifie d'abord la connexion
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    // Si 'directeur' n'est pas explicite, on l'ajoute souvent par défaut, mais restons strict ici.
    if (!in_array($_SESSION['role'], $allowed_roles) && $_SESSION['role'] !== 'directeur') {
        // Redirection vers une page d'erreur ou le dashboard par défaut
        // Pour l'instant, on die()
        die("Accès refusé. Rôle requis : " . implode(", ", $allowed_roles));
    }
}

// Rediriger l'utilisateur vers son dashboard en fonction de son rôle
function redirect_by_role($role) {
    // Les chemins sont relatifs au script d'exécution (souvent public/index.php ou public/login.php)
    // Nous supposons que le point d'entrée est dans 'public/'
    switch ($role) {
        case 'commercial':
            header("Location: ../src/modules/commercial/dashboard.php");
            break;
        case 'accueil':
            header("Location: ../src/modules/accueil/dashboard.php");
            break;
        case 'tac':
            header("Location: ../src/modules/tac/dashboard.php");
            break;
        case 'dispatch':
            header("Location: ../src/modules/dispatch/dashboard.php");
            break;
        case 'tech':
            header("Location: ../src/modules/tech/dashboard.php");
            break;
        case 'directeur':
            header("Location: ../src/modules/admin/dashboard.php"); // Si existe
            break;
        case 'charge_de_compte':
            header("Location: ../src/modules/charge_compte/dashboard.php");
            break;
        default:
            header("Location: index.php"); // Fallback
    }
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate_request() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && is_string($sessionToken) && $token !== '' && hash_equals($sessionToken, $token);
}
?>
