<?php
echo "<h1>🔍 Debug de la Page de Connexion</h1>";

// Test 1: Vérifier les sessions
echo "<h2>1. Test Sessions</h2>";
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . "<br>";
echo "Session save path: " . session_save_path() . "<br>";

// Test 2: Simuler la page de connexion
echo "<h2>2. Test de la Fonction Login</h2>";
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/auth.php';

$email = 'test@gmail.com';
$password = '12345678';

echo "Tentative de connexion avec:<br>";
echo "- Email: $email<br>";
echo "- Mot de passe: $password<br>";

// Test direct de la base de données
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Utilisateur trouvé en base<br>";
        echo "- ID: " . $user['id'] . "<br>";
        echo "- Nom: " . $user['nom'] . "<br>";
        echo "- Email: " . $user['email'] . "<br>";
        echo "- Rôle: " . $user['role'] . "<br>";
        
        // Test de vérification du mot de passe
        if (password_verify($password, $user['password'])) {
            echo "✅ Mot de passe valide<br>";
            
            // Test de la fonction login
            if (login($email, $password)) {
                echo "✅ Fonction login() réussie<br>";
                echo "Session créée pour: " . $_SESSION['user']['prenom'] . " " . $_SESSION['user']['nom'] . "<br>";
                
                // Test de redirection
                echo "<h3>Test de redirection:</h3>";
                echo "✅ Prêt pour redirection vers dashboard.php<br>";
                echo "<a href='public/dashboard.php'>Cliquer ici pour aller au dashboard</a><br>";
            } else {
                echo "❌ Fonction login() échouée<br>";
            }
        } else {
            echo "❌ Mot de passe invalide<br>";
            echo "Hash en base: " . substr($user['password'], 0, 20) . "...<br>";
        }
    } else {
        echo "❌ Utilisateur non trouvé<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur base de données: " . $e->getMessage() . "<br>";
}

// Test 3: Vérifier les variables POST
echo "<h2>3. Test Variables POST</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "✅ Méthode POST détectée<br>";
    echo "Email reçu: " . ($_POST['email'] ?? 'AUCUN') . "<br>";
    echo "Mot de passe reçu: " . (isset($_POST['password']) ? 'PRÉSENT' : 'AUCUN') . "<br>";
} else {
    echo "ℹ️ Méthode GET (pas de soumission de formulaire)<br>";
}

// Test 4: Formulaire de test
echo "<h2>4. Formulaire de Test</h2>";
echo "<form method='POST' action=''>";
echo "<input type='email' name='email' value='test@gmail.com'><br>";
echo "<input type='password' name='password' value='12345678'><br>";
echo "<button type='submit'>Tester la connexion</button>";
echo "</form>";

echo "<hr>";
echo "<h2>🔗 Liens utiles:</h2>";
echo "<ul>";
echo "<li><a href='public/index.php'>Page de connexion réelle</a></li>";
echo "<li><a href='simple_test.php'>Test simple</a></li>";
echo "<li><a href='create_test_user.php'>Recréer l'utilisateur</a></li>";
echo "</ul>";
?> 