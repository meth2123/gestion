<?php
require_once 'service/mysqlcon.php';

echo "<h1>Debug du statut de l'abonnement</h1>";

$stmt = $link->prepare('SELECT * FROM subscriptions WHERE admin_email = ?');
$email = 'dmbosse104@gmail.com';
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $sub = $result->fetch_assoc();
    echo "<h2>Données de la base de données :</h2>";
    echo "<p><strong>ID:</strong> " . $sub['id'] . "</p>";
    echo "<p><strong>Statut DB:</strong> " . $sub['payment_status'] . "</p>";
    echo "<p><strong>Expiry:</strong> " . $sub['expiry_date'] . "</p>";
    echo "<p><strong>Expired:</strong> " . (strtotime($sub['expiry_date']) < time() ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Email:</strong> " . $sub['admin_email'] . "</p>";
    echo "<p><strong>School:</strong> " . $sub['school_name'] . "</p>";
    
    // Test de la logique du SubscriptionDetector
    echo "<h2>Test SubscriptionDetector :</h2>";
    require_once 'service/SubscriptionDetector.php';
    $detector = new SubscriptionDetector($link);
    $detection = $detector->detectSubscriptionStatus($email);
    
    echo "<pre>";
    print_r($detection);
    echo "</pre>";
    
} else {
    echo "<p>Aucun abonnement trouvé pour cet email.</p>";
}
?>

