<?php
/**
 * Réactiver un abonnement
 */

require_once 'service/mysqlcon.php';

$subscription_id = $_GET['id'] ?? null;

if (!$subscription_id) {
    die("ID d'abonnement requis");
}

echo "<h1>🔄 Réactivation de l'Abonnement</h1>";

try {
    // Mettre à jour le statut de l'abonnement
    $stmt = $link->prepare("
        UPDATE subscriptions 
        SET payment_status = 'completed',
            expiry_date = DATE_ADD(NOW(), INTERVAL 1 YEAR),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param("i", $subscription_id);
    $result = $stmt->execute();
    
    if ($result) {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
        echo "✅ Abonnement réactivé avec succès !";
        echo "</div>";
        
        // Vérifier le nouveau statut
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
            WHERE s.id = ?
        ");
        
        $stmt->bind_param("i", $subscription_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $subscription = $result->fetch_assoc();
        
        echo "<h2>Nouveau statut :</h2>";
        echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
        echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
        echo "<p><strong>Email:</strong> " . $subscription['admin_email'] . "</p>";
        echo "<p><strong>Statut paiement:</strong> " . $subscription['payment_status'] . "</p>";
        echo "<p><strong>Date d'expiration:</strong> " . $subscription['expiry_date'] . "</p>";
        echo "<p><strong>Statut calculé:</strong> " . $subscription['status'] . "</p>";
        echo "<p><strong>Jours jusqu'à expiration:</strong> " . $subscription['days_until_expiry'] . "</p>";
        
        echo "<h3>Test de la page de renouvellement :</h3>";
        echo "<p><a href='module/subscription/renew.php?email=" . urlencode($subscription['admin_email']) . "' target='_blank'>";
        echo "Tester la page de renouvellement</a></p>";
        
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
        echo "❌ Erreur lors de la réactivation";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ Erreur: " . $e->getMessage();
    echo "</div>";
}
?>
