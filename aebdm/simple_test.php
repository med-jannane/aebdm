<?php
echo "<h1>🔍 Test Simple de Connexion</h1>";

// Test 1: Vérifier PHP
echo "<h2>1. Test PHP</h2>";
echo "✅ PHP fonctionne<br>";
echo "Version PHP: " . phpversion() . "<br>";

// Test 2: Vérifier les sessions
echo "<h2>2. Test Sessions</h2>";
session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Sessions PHP actives<br>";
} else {
    echo "❌ Sessions PHP non actives<br>";
}

// Test 3: Connexion à la base de données
echo "<h2>3. Test Base de données</h2>";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=AEBDM;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion à la base de données réussie<br>";
} catch (PDOException $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "<br>";
    exit;
}

// Test 4: Vérifier la table users
echo "<h2>4. Test Table Users</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch();
    echo "✅ Table users existe avec " . $result['total'] . " utilisateurs<br>";
} catch (PDOException $e) {
    echo "❌ Erreur table users: " . $e->getMessage() . "<br>";
    exit;
}

// Test 5: Vérifier l'utilisateur test
echo "<h2>5. Test Utilisateur Test</h2>";
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute(['test@gmail.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Utilisateur test trouvé<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Rôle: " . $user['role'] . "<br>";
        echo "Password hashé: " . substr($user['password'], 0, 20) . "...<br>";
        
        // Test de vérification du mot de passe
        $test_password = '12345678';
        echo "<h3>Test de vérification du mot de passe:</h3>";
        
        if (password_verify($test_password, $user['password'])) {
            echo "✅ Mot de passe '12345678' VALIDE<br>";
        } else {
            echo "❌ Mot de passe '12345678' INVALIDE<br>";
            
            // Créer un nouveau hash pour tester
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "Nouveau hash généré: " . substr($new_hash, 0, 20) . "...<br>";
            
            if (password_verify($test_password, $new_hash)) {
                echo "✅ Nouveau hash fonctionne<br>";
                
                // Mettre à jour l'utilisateur avec le nouveau hash
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$new_hash, 'test@gmail.com']);
                echo "✅ Utilisateur mis à jour avec le nouveau hash<br>";
            }
        }
    } else {
        echo "❌ Utilisateur test non trouvé<br>";
        
        // Créer l'utilisateur test
        echo "<h3>Création de l'utilisateur test:</h3>";
        $password = '12345678';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Test', 'Directeur', 'test@gmail.com', $hash, 'directeur']);
        
        echo "✅ Utilisateur test créé<br>";
        echo "Email: test@gmail.com<br>";
        echo "Mot de passe: 12345678<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur utilisateur: " . $e->getMessage() . "<br>";
}

// Test 6: Simuler la fonction login
echo "<h2>6. Test Fonction Login</h2>";
try {
    $email = 'test@gmail.com';
    $password = '12345678';
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        echo "✅ Connexion réussie!<br>";
        echo "Session créée pour: " . $user['prenom'] . " " . $user['nom'] . "<br>";
        echo "Rôle: " . $user['role'] . "<br>";
    } else {
        echo "❌ Échec de la connexion<br>";
        if (!$user) {
            echo "- Utilisateur non trouvé<br>";
        } else {
            echo "- Mot de passe incorrect<br>";
        }
    }
} catch (PDOException $e) {
    echo "❌ Erreur login: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>🔗 Liens de test:</h2>";
echo "<ul>";
echo "<li><a href='public/index.php'>Page de connexion</a></li>";
echo "<li><a href='public/dashboard.php'>Dashboard (si connecté)</a></li>";
echo "</ul>";
?> 