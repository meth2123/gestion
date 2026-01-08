<?php
/**
 * Test du système d'expiration automatique
 */

echo "<h1>🔄 Test du Système d'Expiration Automatique</h1>";

require_once 'service/mysqlcon.php';
require_once 'service/ExpirationManager.php';

$expirationManager = new ExpirationManager($link);

echo "<h2>1. Vérification des abonnements expirés</h2>";

$expired_result = $expirationManager->checkAndUpdateExpiredSubscriptions();

if ($expired_result['success']) {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ " . $expired_result['message'];
    echo "</div>";
    
    if ($expired_result['expired_count'] > 0) {
        echo "<h3>Abonnements expirés :</h3>";
        foreach ($expired_result['expired_subscriptions'] as $subscription) {
            echo "<p>- ID {$subscription['id']} ({$subscription['school_name']}) expiré le {$subscription['expiry_date']}</p>";
        }
    }
} else {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ " . $expired_result['message'];
    echo "</div>";
}

echo "<h2>2. Vérification des abonnements qui vont expirer</h2>";

$upcoming_result = $expirationManager->checkUpcomingExpirations();

if ($upcoming_result['success']) {
    echo "<div style='color: blue; background: #e6f3ff; padding: 10px; border-radius: 5px;'>";
    echo "ℹ️ " . $upcoming_result['message'];
    echo "</div>";
    
    if ($upcoming_result['upcoming_count'] > 0) {
        echo "<h3>Abonnements qui vont expirer :</h3>";
        foreach ($upcoming_result['upcoming_subscriptions'] as $subscription) {
            echo "<p>- ID {$subscription['id']} ({$subscription['school_name']}) expire dans {$subscription['days_until_expiry']} jour(s)</p>";
        }
    }
} else {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ " . $upcoming_result['message'];
    echo "</div>";
}

echo "<h2>3. Statistiques des abonnements</h2>";

$stats_result = $expirationManager->getSubscriptionStats();

if ($stats_result['success']) {
    $stats = $stats_result['stats'];
    echo "<div style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
    echo "<h3>📊 Statistiques :</h3>";
    echo "<p><strong>Actifs :</strong> {$stats['active']}</p>";
    echo "<p><strong>Expirés :</strong> {$stats['expired']}</p>";
    echo "<p><strong>En attente :</strong> {$stats['pending']}</p>";
    echo "</div>";
} else {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ " . $stats_result['message'];
    echo "</div>";
}

echo "<h2>4. Test du script cron</h2>";
echo "<p><a href='cron/expiration_checker.php' target='_blank' class='btn btn-primary'>";
echo "Exécuter le script cron</a></p>";

echo "<h2>5. Vérification du statut MET2813</h2>";

// Vérifier le statut de l'abonnement MET2813
$stmt = $link->prepare("
    SELECT s.*, 
           CASE 
               WHEN s.payment_status = 'completed' AND s.expiry_date > NOW() THEN 'active'
               WHEN s.payment_status = 'completed' AND s.expiry_date <= NOW() THEN 'expired'
               WHEN s.payment_status = 'expired' THEN 'expired'
               WHEN s.payment_status = 'pending' THEN 'pending'
               WHEN s.payment_status = 'failed' THEN 'failed'
               ELSE 'unknown'
           END as status
    FROM subscriptions s
    WHERE s.admin_email = 'dmbosse104@gmail.com'
    AND s.expiry_date != '0000-00-00 00:00:00'
    ORDER BY s.expiry_date DESC
    LIMIT 1
");

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $subscription = $result->fetch_assoc();
    
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h3>Abonnement MET2813 :</h3>";
    echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
    echo "<p><strong>Statut paiement:</strong> " . $subscription['payment_status'] . "</p>";
    echo "<p><strong>Date d'expiration:</strong> " . $subscription['expiry_date'] . "</p>";
    echo "<p><strong>Statut calculé:</strong> " . $subscription['status'] . "</p>";
    
    if ($subscription['status'] === 'active') {
        echo "<div style='color: green; background: #e6ffe6; padding: 5px; border-radius: 3px;'>";
        echo "✅ Abonnement ACTIF";
        echo "</div>";
    } elseif ($subscription['status'] === 'expired') {
        echo "<div style='color: red; background: #ffe6e6; padding: 5px; border-radius: 3px;'>";
        echo "❌ Abonnement EXPIRÉ";
        echo "</div>";
    }
    echo "</div>";
}
?>

