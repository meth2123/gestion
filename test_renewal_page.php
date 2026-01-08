<?php
/**
 * Test direct de la page de renouvellement
 */

echo "<h1>Test de la page de renouvellement</h1>";

// Simuler les paramètres GET
$_GET['email'] = 'dmbosse104@gmail.com';

echo "<h2>Test avec email : dmbosse104@gmail.com</h2>";

require_once 'service/mysqlcon.php';
require_once 'service/SubscriptionDetector.php';

$detector = new SubscriptionDetector($link);

// Test de détection
$email = urldecode($_GET['email']);
echo "<p>Email décodé : " . $email . "</p>";

$detection = $detector->detectSubscriptionStatus($email);
echo "<h3>Résultat de la détection :</h3>";
echo "<pre>";
print_r($detection);
echo "</pre>";

if ($detection['exists']) {
    $subscription = $detection['subscription'];
    echo "<h3>Abonnement trouvé :</h3>";
    echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
    echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
    echo "<p><strong>Statut:</strong> " . $subscription['payment_status'] . "</p>";
    echo "<p><strong>Peut renouveler:</strong> " . ($detection['can_renew'] ? 'OUI' : 'NON') . "</p>";
    
    if ($detection['can_renew']) {
        echo "<p><a href='module/subscription/renew.php?email=" . urlencode($email) . "' class='btn btn-success'>Tester la page de renouvellement</a></p>";
    }
} else {
    echo "<p>Aucun abonnement trouvé.</p>";
}
?>

