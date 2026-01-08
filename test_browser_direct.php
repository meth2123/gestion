<?php
/**
 * Test direct pour le navigateur
 */

echo "<h1>🔍 Test Direct pour le Navigateur</h1>";

// Simuler exactement l'URL du navigateur
$_GET['email'] = 'dmbosse104@gmail.com';

echo "<h2>URL testée :</h2>";
echo "<p>http://localhost:8080/gestion/module/subscription/renew.php?email=dmbosse104%40gmail.com</p>";

echo "<h2>Paramètres reçus :</h2>";
echo "<p><strong>GET email:</strong> " . ($_GET['email'] ?? 'Non défini') . "</p>";

// Test de la détection
require_once 'service/mysqlcon.php';
require_once 'service/SubscriptionDetector.php';

$detector = new SubscriptionDetector($link);

if (isset($_GET['email'])) {
    $email = urldecode($_GET['email']);
    echo "<p><strong>Email décodé:</strong> " . $email . "</p>";
    
    $detection = $detector->detectSubscriptionStatus($email);
    
    echo "<h2>Résultat de la détection :</h2>";
    echo "<pre>";
    print_r($detection);
    echo "</pre>";
    
    if ($detection['exists']) {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
        echo "✅ Abonnement trouvé par SubscriptionDetector";
        echo "</div>";
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
        echo "❌ Aucun abonnement trouvé par SubscriptionDetector";
        echo "</div>";
    }
}

echo "<h2>Test de la page de renouvellement :</h2>";

// Capturer la sortie
ob_start();
include 'module/subscription/renew.php';
$output = ob_get_clean();

// Analyser
if (strpos($output, 'Aucun abonnement trouvé') !== false) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ ERREUR: La page de renouvellement dit 'Aucun abonnement trouvé'";
    echo "</div>";
} else {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ SUCCÈS: La page de renouvellement trouve l'abonnement";
    echo "</div>";
}

echo "<h2>Lien de test :</h2>";
echo "<p><a href='module/subscription/renew.php?email=dmbosse104%40gmail.com' target='_blank' class='btn btn-primary'>";
echo "Tester dans le navigateur</a></p>";

echo "<h2>Debug de la sortie (premiers 1000 caractères) :</h2>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
echo htmlspecialchars(substr($output, 0, 1000));
echo "</pre>";
?>
