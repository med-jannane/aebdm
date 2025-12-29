<?php
require_once __DIR__ . '/src/config.php';

echo "<h2>Test de connexion à la base de données</h2>";

try {
    // Test de connexion
    $pdo->query('SELECT 1');
    echo "✅ Connexion à la base de données réussie<br>";
    
    // Vérifier si la table users existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table 'users' existe<br>";
        
        // Vérifier la structure de la table
        $stmt = $pdo->query("DESCRIBE users");
        echo "<h3>Structure de la table users:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
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
        
        // Vérifier l'utilisateur test
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(['test@gmail.com']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<h3>Utilisateur test trouvé:</h3>";
            echo "<ul>";
            echo "<li>ID: " . $user['id'] . "</li>";
            echo "<li>Nom: " . $user['nom'] . "</li>";
            echo "<li>Prénom: " . $user['prenom'] . "</li>";
            echo "<li>Email: " . $user['email'] . "</li>";
            echo "<li>Rôle: " . $user['role'] . "</li>";
            echo "<li>Mot de passe hashé: " . (isset($user['password']) ? substr($user['password'], 0, 20) . "..." : "NULL") . "</li>";
            echo "</ul>";
            
            // Test de vérification du mot de passe
            if (isset($user['password']) && !empty($user['password'])) {
                $test_password = '12345678';
                if (password_verify($test_password, $user['password'])) {
                    echo "✅ Mot de passe '12345678' valide pour test@gmail.com<br>";
                } else {
                    echo "❌ Mot de passe '12345678' invalide pour test@gmail.com<br>";
                }
            } else {
                echo "❌ Aucun mot de passe trouvé pour l'utilisateur test<br>";
            }
        } else {
            echo "❌ Utilisateur test@gmail.com non trouvé<br>";
        }
        
    } else {
        echo "❌ Table 'users' n'existe pas<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "<br>";
}
?> 