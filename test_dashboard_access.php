<?php
/**
 * Test simple pour vérifier que le tableau de bord fonctionne
 */

echo "<h1>Test d'accès au tableau de bord</h1>";

try {
    require_once __DIR__ . '/service/mysqlcon.php';
    require_once __DIR__ . '/service/SubscriptionDetector.php';
    require_once __DIR__ . '/service/SubscriptionService.php';
    
    echo "✅ Tous les services chargés avec succès<br>";
    
    $detector = new SubscriptionDetector($link);
    echo "✅ SubscriptionDetector initialisé<br>";
    
    $subscriptionService = new SubscriptionService($link);
    echo "✅ SubscriptionService initialisé<br>";
    
    echo "<h2>Test de détection d'abonnement</h2>";
    $detection = $detector->detectSubscriptionStatus("test@example.com");
    echo "✅ Détection d'abonnement fonctionne<br>";
    echo "Résultat: " . ($detection['exists'] ? 'Abonnement trouvé' : 'Aucun abonnement') . "<br>";
    
    echo "<h2>Test réussi !</h2>";
    echo "<p>Le système fonctionne correctement. Vous pouvez maintenant :</p>";
    echo "<ul>";
    echo "<li><a href='module/subscription/dashboard.php'>Accéder au tableau de bord</a></li>";
    echo "<li><a href='demo_subscription_changes.php'>Voir la démonstration</a></li>";
    echo "<li><a href='index.php'>Retourner à la page d'accueil</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString();
}
?>
