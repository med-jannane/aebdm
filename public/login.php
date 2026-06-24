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
        error_log('[LOGIN_QUERY_ERROR] ' . db_last_error_message());
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
    <script src="./assets/js/script.js?v=<?php echo time(); ?>" defer></script>
    <style>
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
            background: #FFFFFF;
            color: #000000;
            min-height: 100vh;
            overflow: hidden;
        }


        .screen {
            position: fixed;
            inset: 0;
            display: grid;
            place-items: center;
            background: #FFFFFF;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }


        .splash-title {
            font-size: clamp(4rem, 12vw, 10rem);
            font-weight: 900;
            letter-spacing: 0.03em;
            color: #240046;
            line-height: 1;
            user-select: none;
            text-shadow: 0 14px 30px rgba(0, 0, 0, 0.08);
            animation: pulseLogo 2.4s ease-in-out infinite;
        }

        @keyframes pulseLogo {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.015); }
        }

        .screen.hidden {
            opacity: 0;
            transform: scale(1.02);
            pointer-events: none;
        }

        .login-stage {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transform: translateY(20px);
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        .login-stage.active {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .login-card {
            width: min(560px, 100%);
            background: #FFFFFF;
            border: 1px solid #E5E5E5;
            border-radius: 28px;
            box-shadow: 0 20px 45px rgba(16, 0, 43, 0.10);
            padding: 34px 32px;
        }


        .card-title {
            font-size: clamp(2rem, 3.4vw, 3.1rem);
            font-weight: 800;
            color: #240046;
            margin-bottom: 26px;
            line-height: 1.1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-group label {
            font-size: 0.95rem;
            font-weight: 700;
            color: #240046;
        }

        .form-group input {
            width: 100%;
            min-height: 56px;
            border: 2px solid #E5E5E5;
            border-radius: 20px;
            background: #FFFFFF;
            color: #000000;
            font-size: 1.05rem;
            padding: 14px 16px;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }


        .form-group input:focus {
            outline: none;
            border-color: #240046;
            box-shadow: 0 0 0 4px rgba(36, 0, 70, 0.1);
        }

        .btn-login {
            width: 100%;
            min-height: 56px;
            border: none;
            border-radius: 999px;
            background: linear-gradient(120deg, #240046 0%, #10002B 100%);
            color: #FFFFFF;
            font-size: 2rem;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(36, 0, 70, 0.24);
        }

        .alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.92rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-error {
            background: #FFF2F2;
            border: 1px solid #E5E5E5;
            color: #10002B;
        }

        .alert-success {
            background: #F8F8F8;
            border: 1px solid #E5E5E5;
            color: #240046;
        }

        @media (max-width: 640px) {
            body {
                overflow: auto;
            }

            .splash-title {
                font-size: clamp(3rem, 18vw, 5rem);
            }

            .login-card {
                border-radius: 20px;
                padding: 24px 20px;
            }

            .card-title {
                font-size: 2.15rem;
                margin-bottom: 20px;
            }

            .form-group input {
                min-height: 52px;
                font-size: 1rem;
            }

            .btn-login {
                min-height: 52px;
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>
    <div id="introScreen" class="screen" aria-hidden="true">
        <h1 class="splash-title">AEBDM</h1>
    </div>

    <main class="login-stage" id="loginStage">
        <section class="login-card">
            <h2 class="card-title">Connexion AEBDM</h2>

            <form method="POST" id="loginForm" autocomplete="off">
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
                Se connecter
            </button>
            </form>
        </section>
    </main>

    <script>
        const introScreen = document.getElementById('introScreen');
        const loginStage = document.getElementById('loginStage');
        const usernameInput = document.getElementById('username');
        let revealed = false;

        function revealForm() {
            if (revealed) return;
            revealed = true;
            introScreen.classList.add('hidden');
            loginStage.classList.add('active');
            setTimeout(() => {
                introScreen.style.display = 'none';
                if (usernameInput) usernameInput.focus();
            }, 520);
        }

        <?php if ($error || $logoutMessage): ?>
            revealForm();
        <?php else: ?>
            document.addEventListener('click', revealForm, { once: true });
            document.addEventListener('keydown', revealForm, { once: true });
            document.addEventListener('touchstart', revealForm, { once: true, passive: true });
        <?php endif; ?>
    </script>
</body>
</html>
