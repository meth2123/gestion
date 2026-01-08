<?php
/**
 * Test simple de la page de renouvellement
 */

echo "<h1>🔍 Test Simple de Renew.php</h1>";

// Simuler l'URL exacte
$_GET['email'] = 'dmbosse104@gmail.com';

echo "<h2>Paramètres :</h2>";
echo "<p><strong>GET email:</strong> " . ($_GET['email'] ?? 'Non défini') . "</p>";

// Inclure les services
require_once 'service/mysqlcon.php';
require_once 'service/SubscriptionDetector.php';

$detector = new SubscriptionDetector($link);

echo "<h2>Test de détection :</h2>";

if (isset($_GET['email'])) {
    $email = urldecode($_GET['email']);
    echo "<p><strong>Email décodé:</strong> " . $email . "</p>";
    
    $detection = $detector->detectSubscriptionStatus($email);
    
    echo "<h3>Résultat :</h3>";
    echo "<p><strong>exists:</strong> " . ($detection['exists'] ? 'TRUE' : 'FALSE') . "</p>";
    
    if ($detection['exists']) {
        $subscription = $detection['subscription'];
        echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
        echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
        echo "<p><strong>Email:</strong> " . $subscription['admin_email'] . "</p>";
        echo "<p><strong>Statut:</strong> " . $subscription['payment_status'] . "</p>";
        echo "<p><strong>Montant:</strong> " . $subscription['amount'] . " FCFA</p>";
        
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
        echo "✅ Abonnement trouvé - Devrait fonctionner dans renew.php";
        echo "</div>";
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
        echo "❌ Aucun abonnement trouvé";
        echo "</div>";
    }
}

echo "<h2>Test de la page renew.php :</h2>";

// Capturer la sortie
ob_start();
include 'module/subscription/renew.php';
$output = ob_get_clean();

// Vérifier le résultat
if (strpos($output, 'Aucun abonnement trouvé') !== false) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ ERREUR: renew.php dit 'Aucun abonnement trouvé'";
    echo "</div>";
} else {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ SUCCÈS: renew.php trouve l'abonnement";
    echo "</div>";
}

echo "<h2>Lien de test :</h2>";
echo "<p><a href='module/subscription/renew.php?email=dmbosse104%40gmail.com' target='_blank'>";
echo "Tester renew.php directement</a></p>";

echo "<p><a href='module/subscription/renew.php?email=dmbosse104%40gmail.com&debug=1' target='_blank'>";
echo "Tester renew.php avec debug</a></p>";
?>
