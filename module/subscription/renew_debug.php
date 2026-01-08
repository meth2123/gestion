<?php
/**
 * Version debug de la page de renouvellement
 */

echo "<h1>🔍 Debug de la Page de Renouvellement</h1>";

// Afficher tous les paramètres
echo "<h2>Paramètres reçus :</h2>";
echo "<p><strong>GET:</strong></p>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<p><strong>POST:</strong></p>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<p><strong>SERVER REQUEST_METHOD:</strong> " . ($_SERVER['REQUEST_METHOD'] ?? 'Non défini') . "</p>";

// Test de la détection
require_once __DIR__ . '/../../service/mysqlcon.php';
require_once __DIR__ . '/../../service/SubscriptionDetector.php';

$detector = new SubscriptionDetector($link);

echo "<h2>Test de détection :</h2>";

if (isset($_GET['email'])) {
    $email = urldecode($_GET['email']);
    echo "<p><strong>Email décodé:</strong> " . $email . "</p>";
    
    $detection = $detector->detectSubscriptionStatus($email);
    
    echo "<h3>Résultat de la détection :</h3>";
    echo "<pre>";
    print_r($detection);
    echo "</pre>";
    
    if ($detection['exists']) {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
        echo "✅ Abonnement trouvé !";
        echo "</div>";
        
        $subscription = $detection['subscription'];
        echo "<h3>Informations de l'abonnement :</h3>";
        echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
        echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
        echo "<p><strong>Email:</strong> " . $subscription['admin_email'] . "</p>";
        echo "<p><strong>Statut:</strong> " . $subscription['payment_status'] . "</p>";
        echo "<p><strong>Peut renouveler:</strong> " . ($detection['can_renew'] ? 'OUI' : 'NON') . "</p>";
        
        // Test de la condition
        echo "<h3>Test de la condition :</h3>";
        $condition1 = isset($subscription);
        $condition2 = isset($detection);
        $condition3 = $detection['exists'];
        
        echo "<p><strong>subscription existe:</strong> " . ($condition1 ? 'OUI' : 'NON') . "</p>";
        echo "<p><strong>detection existe:</strong> " . ($condition2 ? 'OUI' : 'NON') . "</p>";
        echo "<p><strong>detection['exists']:</strong> " . ($condition3 ? 'OUI' : 'NON') . "</p>";
        
        $final_condition = $condition1 && $condition2 && $condition3;
        echo "<p><strong>Condition finale:</strong> " . ($final_condition ? 'OUI' : 'NON') . "</p>";
        
        if ($final_condition) {
            echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
            echo "✅ Condition remplie - L'abonnement devrait être affiché";
            echo "</div>";
        } else {
            echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
            echo "❌ Condition non remplie - C'est pourquoi vous voyez 'Aucun abonnement trouvé'";
            echo "</div>";
        }
        
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
        echo "❌ Aucun abonnement trouvé par SubscriptionDetector";
        echo "</div>";
    }
} else {
    echo "<p>Aucun email fourni dans GET</p>";
}

echo "<h2>Lien de test :</h2>";
echo "<p><a href='renew_debug.php?email=dmbosse104%40gmail.com' class='btn btn-primary'>";
echo "Tester avec debug</a></p>";
?>
