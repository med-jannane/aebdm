<?php
require_once '../config/db.php';
require_once '../src/auth/auth_check.php';

if (isset($_SESSION['user_id'])) {
    redirect_by_role($_SESSION['role']);
}

$error = '';
$logoutMessage = isset($_GET['logged_out']) ? 'Vous etes deconnecte en toute securite.' : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = 'SELECT id, nom, mot_de_passe, role, nom_complet FROM Users WHERE nom = ?';
    $stmt = sqlsrv_query($conn, $sql, [$username]);

    if ($stmt === false) {
        error_log('[LOGIN_BACKUP_QUERY_ERROR] ' . db_last_error_message());
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - AEBDM SAV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body.login-page {
            margin: 0;
            padding: 22px;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f1f5f9;
        }

        .auth-shell {
            width: min(1120px, 100%);
            min-height: 640px;
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, .12);
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.14);
            display: grid;
            grid-template-columns: 1.08fr .92fr;
            background: #ffffff;
        }

        .auth-hero {
            position: relative;
            padding: 36px;
            color: #eef4ff;
            background: #1e293b;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-family: 'Sora', sans-serif;
            font-size: 1.14rem;
            font-weight: 700;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.28);
            box-shadow: none;
        }

        .hero-title {
            font-family: 'Sora', sans-serif;
            font-size: clamp(1.85rem, 3.5vw, 2.75rem);
            line-height: 1.14;
            margin: 6px 0 12px;
        }

        .hero-sub {
            max-width: 560px;
            color: rgba(238, 244, 255, .92);
            font-size: 1.01rem;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .hero-kpi {
            padding: 12px;
            border-radius: 14px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.2);
        }

        .hero-kpi strong {
            display: block;
            font-size: 1.34rem;
            line-height: 1.2;
            margin-bottom: 2px;
        }

        .hero-kpi span {
            font-size: .8rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            opacity: .9;
        }

        .auth-panel {
            background: #ffffff;
            padding: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            width: min(430px, 100%);
        }

        .auth-card h1 {
            font-size: 1.7rem;
            margin: 0 0 4px;
            color: #0f172a;
        }

        .auth-card p {
            color: #475569;
            margin-bottom: 18px;
        }

        .auth-form {
            display: grid;
            gap: 12px;
        }

        .auth-input {
            position: relative;
        }

        .auth-input i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }

        .auth-input input {
            min-height: 52px;
            padding-left: 40px;
            font-size: 1.03rem;
            border-radius: 14px;
        }

        .auth-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            color: #475569;
            font-size: .9rem;
            margin-top: 2px;
        }

        .auth-meta .secure {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #0f766e;
            font-weight: 700;
        }

        .auth-footer {
            margin-top: 18px;
            font-size: .84rem;
            color: #64748b;
            text-align: center;
        }

        @media (max-width: 980px) {
            .auth-shell {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .auth-hero {
                min-height: 280px;
            }
        }

        @media (max-width: 640px) {
            body.login-page {
                padding: 10px;
            }

            .auth-panel,
            .auth-hero {
                padding: 18px;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }

            .auth-card h1 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body class="login-page">
    <main class="auth-shell" aria-label="Connexion AEBDM SAV">
        <section class="auth-hero">
            <div>
                <div class="brand">
                    <span class="brand-mark"><i class="fa-solid fa-layer-group"></i></span>
                    <span>AEBDM SAV Platform</span>
                </div>
                <h2 class="hero-title">Pilotage support, tickets et operations dans un seul espace.</h2>
                <p class="hero-sub">Interface enterprise moderne, tres lisible et optimisee pour PC et mobile. Concue pour accelerer les equipes Accueil, TAC, Dispatch, Technique et Commercial.</p>
            </div>

            <div class="hero-grid" aria-hidden="true">
                <div class="hero-kpi">
                    <strong>24/7</strong>
                    <span>Disponibilite</span>
                </div>
                <div class="hero-kpi">
                    <strong>Realtime</strong>
                    <span>Notifications</span>
                </div>
                <div class="hero-kpi">
                    <strong>Secure</strong>
                    <span>Acces roles</span>
                </div>
                <div class="hero-kpi">
                    <strong>Responsive</strong>
                    <span>Desktop + Mobile</span>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <h1>Connexion securisee</h1>
                <p>Connectez-vous avec votre identifiant entreprise pour acceder au tableau de bord.</p>

                <?php if (!empty($logoutMessage)): ?>
                    <output class="alert alert-success" for="username password">
                        <i class="fa-solid fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($logoutMessage); ?></span>
                    </output>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error mt-2" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="auth-form mt-3" autocomplete="on">
                    <div>
                        <label for="username">Identifiant</label>
                        <div class="auth-input">
                            <i class="fa-regular fa-user"></i>
                            <input id="username" type="text" name="username" placeholder="Ex: accueil.aebdm" required autocomplete="username">
                        </div>
                    </div>

                    <div>
                        <label for="password">Mot de passe</label>
                        <div class="auth-input">
                            <i class="fa-solid fa-lock"></i>
                            <input id="password" type="password" name="password" placeholder="Votre mot de passe" required autocomplete="current-password">
                        </div>
                    </div>

                    <div class="auth-meta">
                        <span>Support AEBDM</span>
                        <span class="secure"><i class="fa-solid fa-shield-halved"></i> Session chiffree</span>
                    </div>

                    <button type="submit" class="btn btn-lg mt-1">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Se connecter
                    </button>
                </form>

                <div class="auth-footer">
                    AEBDM SAV - Plateforme interne entreprise
                </div>
            </div>
        </section>
    </main>
</body>
</html>
