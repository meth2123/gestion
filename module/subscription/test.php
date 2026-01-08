<?php
// Test simple pour vérifier l'accès au répertoire subscription
echo "<h1>Test d'accès - Abonnement</h1>";
echo "<p>Si vous voyez ce message, le répertoire subscription est accessible.</p>";
echo "<p>Chemin actuel: " . __DIR__ . "</p>";
echo "<p>Fichier actuel: " . __FILE__ . "</p>";

// Tester l'inclusion des fichiers requis
$files_to_test = [
    'paydunya_service.php' => __DIR__ . '/../../service/paydunya_service.php',
    'paydunya_env.php' => __DIR__ . '/../../service/paydunya_env.php',
    'db_utils.php' => __DIR__ . '/../../service/db_utils.php'
];

foreach ($files_to_test as $name => $path) {
    echo "<p>Test de $name:</p>";
    echo "<p>Chemin: $path</p>";
    
    if (file_exists($path)) {
        echo "<p style='color: green;'>✓ $name existe</p>";
        try {
            require_once $path;
            echo "<p style='color: green;'>✓ $name chargé avec succès</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Erreur lors du chargement de $name: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ $name n'existe pas</p>";
    }
    echo "<br>";
}

echo "<hr>";
echo "<p><a href='register.php'>Aller à la page d'abonnement</a></p>";
?>

