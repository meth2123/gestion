<?php
// Test simple pour vérifier l'accès au répertoire documentation
echo "<h1>Test d'accès - Documentation</h1>";
echo "<p>Si vous voyez ce message, le répertoire documentation est accessible.</p>";
echo "<p>Chemin actuel: " . __DIR__ . "</p>";
echo "<p>Fichier actuel: " . __FILE__ . "</p>";

// Tester l'inclusion de db_utils.php
$db_utils_path = __DIR__ . '/../service/db_utils.php';
echo "<p>Chemin vers db_utils.php: $db_utils_path</p>";

if (file_exists($db_utils_path)) {
    echo "<p style='color: green;'>✓ db_utils.php existe</p>";
    try {
        require_once $db_utils_path;
        echo "<p style='color: green;'>✓ db_utils.php chargé avec succès</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Erreur lors du chargement: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ db_utils.php n'existe pas</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Aller à la page principale de documentation</a></p>";
?>

