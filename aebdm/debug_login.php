<?php
require_once __DIR__ . '/src/config.php';

echo "<h2>🔍 Debug de la connexion</h2>";

// Test 1: Connexion à la base de données
try {
    $pdo->query('SELECT 1');
    echo "✅ Connexion à la base de données OK<br>";
} catch (PDOException $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Vérifier la table users
$stmt = $pdo->query("SHOW TABLES LIKE 'users'");
if ($stmt->rowCount() == 0) {
    echo "❌ Table 'users' n'existe pas<br>";
    exit;
}
echo "✅ Table 'users' existe<br>";

// Test 3: Vérifier la structure de la table
echo "<h3>Structure de la table users:</h3>";
$stmt = $pdo->query("DESCRIBE users");
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr style='background: #f0f0f0;'><th>Champ</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 4: Vérifier l'utilisateur test
$email = 'test@gmail.com';
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "❌ Utilisateur '$email' non trouvé<br>";
    echo "<h3>Utilisateurs existants:</h3>";
    $stmt = $pdo->query("SELECT id, nom, prenom, email, role FROM users");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Rôle</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['nom'] . "</td>";
        echo "<td>" . $row['prenom'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "✅ Utilisateur '$email' trouvé<br>";
    echo "<h3>Détails de l'utilisateur:</h3>";
    echo "<ul>";
    echo "<li><strong>ID:</strong> " . $user['id'] . "</li>";
    echo "<li><strong>Nom:</strong> " . $user['nom'] . "</li>";
    echo "<li><strong>Prénom:</strong> " . $user['prenom'] . "</li>";
    echo "<li><strong>Email:</strong> " . $user['email'] . "</li>";
    echo "<li><strong>Rôle:</strong> " . $user['role'] . "</li>";
    
    // Vérifier le champ password
    if (isset($user['password'])) {
        echo "<li><strong>Password hashé:</strong> " . substr($user['password'], 0, 20) . "...</li>";
        echo "<li><strong>Longueur du hash:</strong> " . strlen($user['password']) . " caractères</li>";
        
        // Test de vérification du mot de passe
        $test_password = '12345678';
        echo "<li><strong>Test avec mot de passe '12345678':</strong> ";
        if (password_verify($test_password, $user['password'])) {
            echo "✅ VALIDE</li>";
        } else {
            echo "❌ INVALIDE</li>";
            
            // Test avec différents algorithmes
            echo "<li><strong>Tests alternatifs:</strong></li>";
            echo "<ul>";
            
            // Test avec hash simple
            $simple_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "<li>Nouveau hash pour '12345678': " . substr($simple_hash, 0, 20) . "...</li>";
            
            // Test avec hash spécifique
            $specific_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            echo "<li>Test avec hash spécifique: ";
            if (password_verify($test_password, $specific_hash)) {
                echo "✅ VALIDE</li>";
            } else {
                echo "❌ INVALIDE</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<li><strong>Password:</strong> ❌ CHAMP MANQUANT</li>";
    }
    echo "</ul>";
}

// Test 5: Simuler la fonction login
echo "<h3>Test de la fonction login:</h3>";
if ($user) {
    $test_password = '12345678';
    
    // Test direct
    echo "<strong>Test direct password_verify:</strong> ";
    if (password_verify($test_password, $user['password'])) {
        echo "✅ SUCCÈS<br>";
    } else {
        echo "❌ ÉCHEC<br>";
    }
    
    // Test avec la fonction login
    require_once __DIR__ . '/src/auth.php';
    echo "<strong>Test avec fonction login:</strong> ";
    if (login($email, $test_password)) {
        echo "✅ SUCCÈS<br>";
    } else {
        echo "❌ ÉCHEC<br>";
    }
}

echo "<hr>";
echo "<h3>🔧 Actions recommandées:</h3>";
echo "<ol>";
echo "<li><a href='create_test_user.php'>Recréer l'utilisateur test</a></li>";
echo "<li><a href='public/index.php'>Tester la connexion</a></li>";
echo "</ol>";
?> 