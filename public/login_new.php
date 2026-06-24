<?php
require_once '../config/db.php';
require_once '../src/auth/auth_check.php';

if (isset($_SESSION['user_id'])) {
    redirect_by_role($_SESSION['role']);
}

$error = '';
$logoutMessage = isset($_GET['logged_out']) ? 'Vous êtes déconnecté en toute sécurité.' : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = 'SELECT id, nom, mot_de_passe, role, nom_complet FROM Users WHERE nom = ?';
    $stmt = sqlsrv_query($conn, $sql, [$username]);

    if ($stmt === false) {
        error_log('[LOGIN_NEW_QUERY_ERROR] ' . db_last_error_message());
        $error = 'Erreur interne, veuillez reessayer.';
    } elseif (sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if (password_verify($password, $row['mot_de_passe'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['nom'];
            $role_db = ($row['role'] === 'admin') ? 'directeur' : $row['role'];
            $_SESSION['role'] = $role_db;
            $_SESSION['nom_complet'] = $row['nom_complet'];
            redirect_by_role($_SESSION['role']);
        } else {
            $error = 'Mot de passe incorrect.';
        }
    } else {
        $error = 'Identifiant introuvable.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Connexion - AEBDM SAV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ╔══════════════════════════════════════════════════════╗
           ║                    RESET & BASE                       ║
           ╚══════════════════════════════════════════════════════╝ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #140152 0%, #240046 50%, #140152 100%);
            background-attachment: fixed;
            color: #333333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║              LOGIN CONTAINER - RESPONSIVE             ║
           ╚══════════════════════════════════════════════════════╝ */
        .login-container {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideInUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║                    HERO SECTION                       ║
           ╚══════════════════════════════════════════════════════╝ */
        .login-hero {
            background: linear-gradient(135deg, #240046 0%, #140152 100%);
            padding: 48px 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .login-hero::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            right: -50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: float 20s linear infinite;
            pointer-events: none;
        }

        @keyframes float {
            0% { transform: translate(0, 0); }
            100% { transform: translate(30px, 30px); }
        }

        .login-logo {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #ffffff;
            position: relative;
            z-index: 1;
        }

        .login-title {
            font-size: clamp(2rem, 6vw, 3.2rem);
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
            line-height: 1.2;
            position: relative;
            z-index: 1;
        }

        .login-subtitle {
            font-size: clamp(0.9rem, 2vw, 1rem);
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║                    FORM SECTION                       ║
           ╚══════════════════════════════════════════════════════╝ */
        .login-form {
            padding: 36px 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #666666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            padding: 14px 16px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: #f9f9f9;
            color: #333333;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #240046;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(36, 0, 70, 0.1);
        }

        .form-group input::placeholder {
            color: #999999;
        }

        .form-remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }

        .form-remember input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #240046;
        }

        .form-remember label {
            cursor: pointer;
            font-weight: 500;
            text-transform: none;
            letter-spacing: normal;
            font-size: 0.85rem;
        }

        .btn-login {
            padding: 14px 24px;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #240046 0%, #140152 100%);
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-height: 48px;
            -webkit-appearance: none;
            appearance: none;
        }

        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(36, 0, 70, 0.3);
        }

        .btn-login:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║                   ALERTS / MESSAGES                   ║
           ╚══════════════════════════════════════════════════════╝ */
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-error i {
            color: #dc2626;
            flex-shrink: 0;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }

        .alert-success i {
            color: #15803d;
            flex-shrink: 0;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║                  FOOTER SECTION                       ║
           ╚══════════════════════════════════════════════════════╝ */
        .login-footer {
            padding: 16px 24px;
            text-align: center;
            font-size: 0.8rem;
            color: #999999;
            border-top: 1px solid #f0f0f0;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║                 MOBILE RESPONSIVE                     ║
           ╚══════════════════════════════════════════════════════╝ */
        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .login-container {
                max-width: 100%;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }

            .login-hero {
                padding: 32px 20px;
                gap: 12px;
            }

            .login-title {
                font-size: 2.2rem;
            }

            .login-logo {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .login-form {
                padding: 24px 20px;
                gap: 16px;
            }

            .form-group input {
                padding: 12px 14px;
                font-size: 16px;
                border-radius: 10px;
            }

            .btn-login {
                padding: 12px 20px;
                font-size: 0.95rem;
                min-height: 44px;
            }

            .alert {
                font-size: 0.85rem;
                padding: 12px 14px;
            }
        }

        @media (min-width: 481px) and (max-width: 767px) {
            .login-container {
                max-width: 380px;
            }

            .login-hero {
                padding: 40px 24px;
            }

            .login-form {
                padding: 32px 24px;
            }
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║              ORIENTATION - LANDSCAPE                  ║
           ╚══════════════════════════════════════════════════════╝ */
        @media (max-width: 767px) and (orientation: landscape) {
            body {
                min-height: auto;
                padding: 8px;
            }

            .login-container {
                max-height: 95vh;
                overflow-y: auto;
            }

            .login-hero {
                padding: 20px 24px;
                gap: 12px;
            }

            .login-form {
                padding: 20px 24px;
                gap: 14px;
            }
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║                    ACCESSIBILITY                      ║
           ╚══════════════════════════════════════════════════════╝ */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #0a0a0a;
            }

            .login-container {
                background: #1a1a1a;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
            }

            .login-form {
                background: #1a1a1a;
            }

            .form-group input {
                background: #262626;
                border-color: #404040;
                color: #ffffff;
            }

            .form-group input:focus {
                background: #2a2a2a;
                box-shadow: 0 0 0 3px rgba(36, 0, 70, 0.2);
            }

            .form-group label {
                color: #cccccc;
            }

            .login-footer {
                border-top-color: #262626;
                color: #666666;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Hero Section -->
        <div class="login-hero">
            <div class="login-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">AEBDM</h1>
            <p class="login-subtitle">Gestion SAV</p>
        </div>

        <!-- Form Section -->
        <form method="POST" class="login-form" autocomplete="off">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($logoutMessage): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($logoutMessage) ?></span>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Identifiant</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Votre nom d'utilisateur"
                    required
                    autocomplete="username"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Votre mot de passe"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Connexion
            </button>
        </form>

        <!-- Footer -->
        <div class="login-footer">
            © <?= date('Y') ?> AEBDM - Tous droits réservés
        </div>
    </div>
</body>
</html>
