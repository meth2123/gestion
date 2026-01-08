<?php
/**
 * Vérifier le statut actuel de l'abonnement
 */

require_once 'service/mysqlcon.php';

echo "<h1>🔍 Statut de l'Abonnement MET2813</h1>";

$email = 'dmbosse104@gmail.com';

// Vérifier l'abonnement actuel
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
    LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $subscription = $result->fetch_assoc();
    
    echo "<h2>Informations actuelles :</h2>";
    echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
    echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
    echo "<p><strong>Email:</strong> " . $subscription['admin_email'] . "</p>";
    echo "<p><strong>Statut paiement:</strong> " . $subscription['payment_status'] . "</p>";
    echo "<p><strong>Date d'expiration:</strong> " . $subscription['expiry_date'] . "</p>";
    echo "<p><strong>Statut calculé:</strong> " . $subscription['status'] . "</p>";
    echo "<p><strong>Jours jusqu'à expiration:</strong> " . $subscription['days_until_expiry'] . "</p>";
    
    if ($subscription['status'] === 'expired') {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
        echo "❌ L'abonnement est toujours marqué comme expiré";
        echo "</div>";
        
        echo "<h3>Actions possibles :</h3>";
        echo "<p><a href='reactivate_subscription.php?id=" . $subscription['id'] . "' class='btn btn-primary'>Réactiver l'abonnement</a></p>";
    } else {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
        echo "✅ L'abonnement est actif";
        echo "</div>";
    }
} else {
    echo "<p>Aucun abonnement trouvé</p>";
}
?>