<?php
/**
 * Vérifier tous les abonnements pour cet email
 */

require_once 'service/mysqlcon.php';

echo "<h1>🔍 Tous les Abonnements pour dmbosse104@gmail.com</h1>";

$email = 'dmbosse104@gmail.com';

// Vérifier tous les abonnements
$stmt = $link->prepare("
    SELECT s.*, 
           CASE 
               WHEN s.payment_status = 'completed' AND s.expiry_date > NOW() THEN 'active'
               WHEN s.payment_status = 'completed' AND s.expiry_date <= NOW() THEN 'expired'
               WHEN s.payment_status = 'expired' THEN 'expired'
               WHEN s.payment_status = 'pending' THEN 'pending'
               WHEN s.payment_status = 'failed' THEN 'failed'
               ELSE 'unknown'
           END as status,
           DATEDIFF(s.expiry_date, NOW()) as days_until_expiry
    FROM subscriptions s
    WHERE s.admin_email = ?
    ORDER BY s.created_at DESC
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Tous les abonnements trouvés :</h2>";

if ($result->num_rows > 0) {
    while ($subscription = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h3>Abonnement ID: " . $subscription['id'] . "</h3>";
        echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
        echo "<p><strong>Email:</strong> " . $subscription['admin_email'] . "</p>";
        echo "<p><strong>Statut paiement:</strong> " . $subscription['payment_status'] . "</p>";
        echo "<p><strong>Date d'expiration:</strong> " . $subscription['expiry_date'] . "</p>";
        echo "<p><strong>Statut calculé:</strong> " . $subscription['status'] . "</p>";
        echo "<p><strong>Date de création:</strong> " . $subscription['created_at'] . "</p>";
        echo "<p><strong>Date de mise à jour:</strong> " . $subscription['updated_at'] . "</p>";
        echo "</div>";
    }
} else {
    echo "<p>Aucun abonnement trouvé</p>";
}
?>

