<?php
require_once __DIR__ . '/config.php';

function login($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: vérifier si l'utilisateur existe
        if (!$user) {
            return false;
        }
        
        // Debug: vérifier si le mot de passe existe
        if (!isset($user['password']) || empty($user['password'])) {
            return false;
        }
        
        // Vérifier le mot de passe
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        // Log l'erreur pour debug
        error_log("Erreur de connexion: " . $e->getMessage());
        return false;
    }
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function logout() {
    session_destroy();
} 