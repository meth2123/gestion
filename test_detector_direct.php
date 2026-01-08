<?php
/**
 * Test direct du SubscriptionDetector
 */

require_once 'service/mysqlcon.php';
require_once 'service/SubscriptionDetector.php';

echo "<h1>🔍 Test Direct du SubscriptionDetector</h1>";

$detector = new SubscriptionDetector($link);
$email = 'dmbosse104@gmail.com';

echo "<h2>Test avec email: $email</h2>";

$detection = $detector->detectSubscriptionStatus($email);

echo "<h3>Résultat de la détection :</h3>";
echo "<pre>";
print_r($detection);
echo "</pre>";

if ($detection['exists']) {
    $subscription = $detection['subscription'];
    echo "<h3>Abonnement détecté :</h3>";
    echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
    echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
    echo "<p><strong>Statut:</strong> " . $subscription['payment_status'] . "</p>";
    echo "<p><strong>Date d'expiration:</strong> " . $subscription['expiry_date'] . "</p>";
    echo "<p><strong>Peut renouveler:</strong> " . ($detection['can_renew'] ? 'OUI' : 'NON') . "</p>";
    
    if ($detection['can_renew']) {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
        echo "✅ L'abonnement peut être renouvelé";
        echo "</div>";
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
        echo "❌ L'abonnement ne peut pas être renouvelé";
        echo "</div>";
    }
} else {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ Aucun abonnement trouvé";
    echo "</div>";
}
?>

