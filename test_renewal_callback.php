<?php
/**
 * Test du callback de renouvellement
 */

echo "<h1>🔄 Test du Callback de Renouvellement</h1>";

require_once 'service/mysqlcon.php';
require_once 'service/paydunya_service.php';

// Simuler un callback PayDunya pour un renouvellement
$mock_payload = json_encode([
    'token' => 'test_token_123',
    'status' => 'completed',
    'total_amount' => '15000',
    'receipt_url' => 'https://paydunya.com/receipt/test123',
    'custom_data' => [
        'subscription_id' => 7  // ID de l'abonnement MET2813
    ]
]);

echo "<h2>Payload simulé :</h2>";
echo "<pre>" . htmlspecialchars($mock_payload) . "</pre>";

try {
    $paydunya = new PayDunyaService($link);
    
    echo "<h2>Avant le callback :</h2>";
    
    // Vérifier le statut avant
    $stmt = $link->prepare("SELECT id, school_name, payment_status, expiry_date FROM subscriptions WHERE id = 7");
    $stmt->execute();
    $before = $stmt->get_result()->fetch_assoc();
    
    echo "<p><strong>Statut avant :</strong> " . $before['payment_status'] . "</p>";
    echo "<p><strong>Date d'expiration avant :</strong> " . $before['expiry_date'] . "</p>";
    
    echo "<h2>Exécution du callback :</h2>";
    
    // Simuler le callback
    $result = $paydunya->handleCallback($mock_payload);
    
    if ($result) {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
        echo "✅ Callback exécuté avec succès";
        echo "</div>";
        
        echo "<h2>Après le callback :</h2>";
        
        // Vérifier le statut après
        $stmt = $link->prepare("SELECT id, school_name, payment_status, expiry_date FROM subscriptions WHERE id = 7");
        $stmt->execute();
        $after = $stmt->get_result()->fetch_assoc();
        
        echo "<p><strong>Statut après :</strong> " . $after['payment_status'] . "</p>";
        echo "<p><strong>Date d'expiration après :</strong> " . $after['expiry_date'] . "</p>";
        
        if ($after['payment_status'] === 'completed') {
            echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
            echo "✅ Abonnement réactivé avec succès !";
            echo "</div>";
        } else {
            echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
            echo "❌ L'abonnement n'a pas été réactivé";
            echo "</div>";
        }
        
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
        echo "❌ Le callback a échoué";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ Erreur : " . $e->getMessage();
    echo "</div>";
}

echo "<h2>Test de la page de renouvellement :</h2>";
echo "<p><a href='module/subscription/renew.php?email=dmbosse104%40gmail.com' target='_blank'>";
echo "Tester la page de renouvellement</a></p>";
?>
