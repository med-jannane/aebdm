<?php
// Script de diagnostic pour trouver l'erreur 500 sur import_csv.php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<h2>Test Diagnostic Import CSV</h2>";

echo "<p>1. Test db.php...</p>";
try {
    require_once __DIR__ . '/../../../config/db.php';
    echo "<p style='color:green'>✅ db.php OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ db.php ERREUR: " . $e->getMessage() . "</p>";
}

echo "<p>2. Test auth_check.php...</p>";
try {
    require_once __DIR__ . '/../../auth/auth_check.php';
    echo "<p style='color:green'>✅ auth_check.php OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ auth_check.php ERREUR: " . $e->getMessage() . "</p>";
}

echo "<p>3. Test Session...</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session role: " . ($_SESSION['role'] ?? 'NON DEFINI') . "</p>";

echo "<p>4. Test CsvImporter.php...</p>";
try {
    require_once __DIR__ . '/../../utils/CsvImporter.php';
    echo "<p style='color:green'>✅ CsvImporter.php OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ CsvImporter.php ERREUR: " . $e->getMessage() . "</p>";
}

echo "<p>5. Test SimpleXLSX.php exist...</p>";
$xlsxPath = __DIR__ . '/../../libs/SimpleXLSX.php';
if (file_exists($xlsxPath)) {
    echo "<p style='color:green'>✅ SimpleXLSX.php existe</p>";
} else {
    echo "<p style='color:red'>❌ SimpleXLSX.php INTROUVABLE à: $xlsxPath</p>";
}

echo "<p>6. Test head.php...</p>";
echo "<p>head.php path: " . realpath(__DIR__ . '/../../includes/head.php') . "</p>";

echo "<hr><p><strong>Si vous voyez ce message, pas d'erreur fatale dans les includes.</strong></p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Loaded extensions: " . implode(', ', get_loaded_extensions()) . "</p>";
?>
