<?php
echo "<h1>🔧 Correction des Rôles</h1>";

// Liste des fichiers à corriger
$files = [
    'public/dashboard.php',
    'public/users.php',
    'public/clients.php',
    'public/contrats.php',
    'public/interventions.php',
    'public/visites.php',
    'public/produits.php',
    'public/add_user.php',
    'public/add_client.php',
    'public/add_contrat.php',
    'public/add_intervention.php',
    'public/add_visite.php',
    'public/add_produit.php',
    'public/edit_user.php',
    'public/edit_client.php',
    'public/edit_contrat.php',
    'public/edit_intervention.php',
    'public/edit_visite.php',
    'public/edit_produit.php',
    'public/delete_user.php',
    'public/delete_client.php',
    'public/delete_contrat.php',
    'public/delete_intervention.php',
    'public/delete_visite.php',
    'public/delete_produit.php'
];

$total_fixed = 0;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $original_content = $content;
        
        // Remplacer toutes les occurrences
        $content = str_replace('charge_de_compte', 'charge_compte', $content);
        
        if ($content !== $original_content) {
            file_put_contents($file, $content);
            echo "✅ Corrigé: $file<br>";
            $total_fixed++;
        } else {
            echo "ℹ️ Aucun changement: $file<br>";
        }
    } else {
        echo "❌ Fichier non trouvé: $file<br>";
    }
}

echo "<hr>";
echo "<h2>📊 Résumé</h2>";
echo "Total de fichiers corrigés: $total_fixed<br>";

echo "<h2>🔗 Liens utiles:</h2>";
echo "<ul>";
echo "<li><a href='public/index.php'>Page de connexion</a></li>";
echo "<li><a href='public/dashboard.php'>Dashboard</a></li>";
echo "<li><a href='debug_connection.php'>Debug connexion</a></li>";
echo "</ul>";
?> 