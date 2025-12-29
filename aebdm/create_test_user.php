<?php
require_once __DIR__ . '/src/config.php';

echo "<h2>🔧 Création de l'utilisateur test</h2>";

try {
    // Supprimer l'utilisateur test s'il existe
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
    $stmt->execute(['test@gmail.com']);
    echo "✅ Ancien utilisateur test supprimé<br>";
    
    // Créer le nouveau mot de passe hashé
    $password = '12345678';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<h3>Détails du hash:</h3>";
    echo "<ul>";
    echo "<li><strong>Mot de passe:</strong> $password</li>";
    echo "<li><strong>Hash généré:</strong> " . substr($hashed_password, 0, 30) . "...</li>";
    echo "<li><strong>Longueur du hash:</strong> " . strlen($hashed_password) . " caractères</li>";
    echo "<li><strong>Test de vérification:</strong> ";
    if (password_verify($password, $hashed_password)) {
        echo "✅ VALIDE</li>";
    } else {
        echo "❌ INVALIDE</li>";
    }
    echo "</ul>";
    
    // Insérer le nouvel utilisateur test
    $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, numero, password, region, ville, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'Test',
        'Directeur',
        'test@gmail.com',
        '0123456789',
        $hashed_password,
        'Île-de-France',
        'Paris',
        'directeur'
    ]);
    
    $user_id = $pdo->lastInsertId();
    echo "✅ Nouvel utilisateur test créé avec succès (ID: $user_id)<br>";
    
    // Vérifier que l'utilisateur a bien été créé
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($new_user) {
        echo "<h3>Vérification de l'utilisateur créé:</h3>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> " . $new_user['id'] . "</li>";
        echo "<li><strong>Nom:</strong> " . $new_user['nom'] . "</li>";
        echo "<li><strong>Prénom:</strong> " . $new_user['prenom'] . "</li>";
        echo "<li><strong>Email:</strong> " . $new_user['email'] . "</li>";
        echo "<li><strong>Rôle:</strong> " . $new_user['role'] . "</li>";
        echo "<li><strong>Password hashé:</strong> " . substr($new_user['password'], 0, 30) . "...</li>";
        echo "</ul>";
        
        // Test de connexion avec la fonction login
        require_once __DIR__ . '/src/auth.php';
        echo "<h3>Test de connexion:</h3>";
        if (login('test@gmail.com', '12345678')) {
            echo "✅ Connexion réussie avec la fonction login()<br>";
        } else {
            echo "❌ Échec de la connexion avec la fonction login()<br>";
        }
    }
    
    echo "<hr>";
    echo "<h3>📋 Informations de connexion:</h3>";
    echo "<ul>";
    echo "<li><strong>📧 Email:</strong> test@gmail.com</li>";
    echo "<li><strong>🔑 Mot de passe:</strong> 12345678</li>";
    echo "<li><strong>👤 Rôle:</strong> directeur</li>";
    echo "</ul>";
    
    echo "<h3>🔗 Liens utiles:</h3>";
    echo "<ul>";
    echo "<li><a href='debug_login.php'>🔍 Debug de la connexion</a></li>";
    echo "<li><a href='public/index.php'>🚪 Page de connexion</a></li>";
    echo "<li><a href='test_db.php'>📊 Test de la base de données</a></li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
    echo "<h3>🔍 Debug de l'erreur:</h3>";
    echo "<pre>" . print_r($e->getTrace(), true) . "</pre>";
}
?> 