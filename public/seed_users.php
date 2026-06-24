<?php
// Script pour créer les utilisateurs de test avec les bons hashs de mot de passe
require_once '../config/db.php';

$users = [
    ['commercial', '123', 'commercial', 'Jean Commercial'],
    ['accueil', '123', 'accueil', 'Sophie Accueil'],
    ['tac', '123', 'tac', 'Thomas TAC'],
    ['dispatch', '123', 'dispatch', 'David Dispatch'],
    ['tech', '123', 'tech', 'Thierry Tech'],
    ['admin', '123', 'admin', 'Super Admin']
];

echo "<h2>Création des utilisateurs de test...</h2>";

foreach ($users as $u) {
    $username = $u[0];
    $password = password_hash($u[1], PASSWORD_DEFAULT);
    $role = $u[2];
    $nom = $u[3];

    // Vérifier si existe déjà
    $check = query("SELECT id FROM Users WHERE username = ?", [$username]);
    if (sqlsrv_has_rows($check)) {
        echo "L'utilisateur <strong>$username</strong> existe déjà.<br>";
        // Update password just in case
        $sql = "UPDATE Users SET password = ?, role = ?, nom_complet = ? WHERE username = ?";
        query($sql, [$password, $role, $nom, $username]);
        echo " -> Mis à jour (Mdp: 123)<br>";
    } else {
        $sql = "INSERT INTO Users (username, password, role, nom_complet) VALUES (?, ?, ?, ?)";
        query($sql, [$username, $password, $role, $nom]);
        echo "Utilisateur <strong>$username</strong> créé (Mdp: 123)<br>";
    }
}
echo "<hr><h3>Fait ! Vous pouvez vous connecter.</h3>";
