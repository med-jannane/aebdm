<?php
session_start();
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

// Redirige si déjà connecté
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Debug: afficher les informations reçues
    error_log("Tentative de connexion - Email: $email");
    
    if (login($email, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Email ou mot de passe incorrect.';
        error_log("Échec de connexion pour l'email: $email");
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - AEBDM</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e5e7eb 100%);
            min-height: 100vh;
        }
        .aebdm-logo-splash {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e5e7eb 100%);
            z-index: 1000;
            transition: opacity 0.7s cubic-bezier(.4,0,.2,1), visibility 0.7s;
        }
        .aebdm-logo-splash.hide {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .aebdm-logo-text {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            font-size: 6rem;
            color: #4a006f;
            font-weight: 900;
            letter-spacing: -0.03em;
            text-align: center;
            user-select: none;
            text-shadow: 0 6px 32px #4a006f22, 0 1px 0 #fff;
            transition: transform 0.7s cubic-bezier(.4,0,.2,1), opacity 0.7s;
            animation: fadeInUp 1s cubic-bezier(.4,0,.2,1);
        }
        .login-container-modern {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100vw;
            position: fixed;
            top: 0; left: 0;
            z-index: 10;
            background: none;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.7s cubic-bezier(.4,0,.2,1);
        }
        .login-container-modern.show {
            opacity: 1;
            pointer-events: auto;
        }
        .modern-login-form {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 8px 32px rgba(74,0,111,0.13), 0 1.5px 8px #4a006f11;
            padding: 2.7rem 2.2rem 2.2rem 2.2rem;
            min-width: 320px;
            max-width: 95vw;
            display: flex;
            flex-direction: column;
            gap: 1.3rem;
            animation: fadeInUp 0.8s cubic-bezier(.4,0,.2,1);
        }
        .modern-login-form h2 {
            color: #4a006f;
            font-size: 2.1rem;
            font-weight: 800;
            margin-bottom: 0.7rem;
            text-align: center;
        }
        .modern-login-form label {
            font-weight: 600;
            color: #4a006f;
            margin-bottom: 0.3rem;
        }
        .modern-login-form input[type="email"],
        .modern-login-form input[type="password"] {
            width: 100%;
            padding: 1rem 1.2rem;
            border-radius: 15px;
            border: 2px solid #e5e7eb;
            font-size: 1.08rem;
            background: #f8f9fa;
            margin-bottom: 0.2rem;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .modern-login-form input[type="email"]:focus,
        .modern-login-form input[type="password"]:focus {
            border-color: #4a006f;
            background: #fff;
            box-shadow: 0 2px 8px #4a006f22;
            outline: none;
        }
        .modern-login-form button {
            background: linear-gradient(90deg, #4a006f 0%, #7c3aed 100%);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 1rem 2.2rem;
            font-size: 1.1rem;
            font-weight: 700;
            box-shadow: 0 2px 8px #4a006f22;
            cursor: pointer;
            margin-top: 0.7rem;
            transition: background 0.2s, transform 0.2s;
        }
        .modern-login-form button:hover {
            background: linear-gradient(90deg, #7c3aed 0%, #4a006f 100%);
            transform: translateY(-2px) scale(1.04);
        }
        .modern-login-form .message {
            margin-bottom: 0.5rem;
        }
        @media (max-width: 600px) {
            .aebdm-logo-text { font-size: 3.5rem; }
            .modern-login-form { min-width: 90vw; padding: 1.2rem 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="aebdm-logo-splash" id="splash" style="display: <?= $error ? 'none' : 'flex' ?>;">
        <div class="aebdm-logo-text">AEBDM</div>
    </div>
    <div class="login-container-modern<?= $error ? ' show' : '' ?>" id="loginContainer">
        <form method="POST" class="modern-login-form">
            <h2>Connexion AEBDM</h2>
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <label>Email</label>
            <input type="email" name="email" placeholder="Email" required autocomplete="username">
            <label>Mot de passe</label>
            <input type="password" name="password" placeholder="Mot de passe" required autocomplete="current-password">
            <button type="submit">Se connecter</button>
        </form>
    </div>
    <script>
        const splash = document.getElementById('splash');
        const loginContainer = document.getElementById('loginContainer');
        let splashHidden = <?= $error ? 'true' : 'false' ?>;
        function showLogin() {
            if (splashHidden) return;
            splash.classList.add('hide');
            setTimeout(() => {
                loginContainer.classList.add('show');
            }, 600);
            splashHidden = true;
        }
        if (!splashHidden) {
            splash.addEventListener('click', showLogin);
            document.body.addEventListener('click', showLogin);
            document.body.addEventListener('keydown', function(e) {
                if ((e.key === 'Enter' || e.key === ' ' || e.key === 'Escape') && !splashHidden) {
                    showLogin();
                }
            });
        }
    </script>
</body>
</html> 