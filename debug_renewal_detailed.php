<?php
/**
 * Debug détaillé de la page de renouvellement
 */

echo "<h1>🔍 Debug Détaillé de la Page de Renouvellement</h1>";

// Simuler exactement ce que fait la page de renouvellement
$_GET['email'] = 'dmbosse104@gmail.com';

echo "<h2>1. Simulation de la page de renouvellement</h2>";

require_once 'service/mysqlcon.php';
require_once 'service/SubscriptionDetector.php';

$detector = new SubscriptionDetector($link);

echo "<h3>Paramètres reçus :</h3>";
echo "<p><strong>Email GET:</strong> " . ($_GET['email'] ?? 'Non défini') . "</p>";

// Détection comme dans renew.php
$detection = null;
$subscription = null;

if (isset($_GET['email'])) {
    $email = urldecode($_GET['email']);
    echo "<p><strong>Email décodé:</strong> " . $email . "</p>";
    
    $detection = $detector->detectSubscriptionStatus($email);
    echo "<h3>Résultat de la détection :</h3>";
    echo "<pre>";
    print_r($detection);
    echo "</pre>";
    
    if ($detection['exists']) {
        $subscription = $detection['subscription'];
        echo "<h3>Abonnement assigné :</h3>";
        echo "<p><strong>ID:</strong> " . $subscription['id'] . "</p>";
        echo "<p><strong>École:</strong> " . $subscription['school_name'] . "</p>";
        echo "<p><strong>Statut:</strong> " . $subscription['payment_status'] . "</p>";
    }
}

echo "<h3>Variables finales :</h3>";
echo "<p><strong>subscription existe:</strong> " . (isset($subscription) ? 'OUI' : 'NON') . "</p>";
echo "<p><strong>detection existe:</strong> " . (isset($detection) ? 'OUI' : 'NON') . "</p>";

if ($subscription && isset($detection) && $detection) {
    echo "<p style='color: green;'><strong>✅ Condition remplie - Abonnement trouvé</strong></p>";
    if (!$detection['can_renew']) {
        echo "<p style='color: orange;'><strong>⚠️ Ne peut pas être renouvelé</strong></p>";
    } else {
        echo "<p style='color: green;'><strong>✅ Peut être renouvelé</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Condition non remplie - Aucun abonnement trouvé</strong></p>";
    echo "<p><strong>Raison possible :</strong></p>";
    if (!isset($subscription)) echo "<p>- subscription n'est pas défini</p>";
    if (!isset($detection)) echo "<p>- detection n'est pas défini</p>";
    if (isset($detection) && !$detection) echo "<p>- detection est false</p>";
}

echo "<h2>2. Test direct de la requête SQL</h2>";

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
    ORDER BY 
        CASE s.payment_status 
            WHEN 'completed' THEN 1
            WHEN 'expired' THEN 2
            WHEN 'pending' THEN 3
            WHEN 'failed' THEN 4
            ELSE 5
        END,
        s.created_at DESC
    LIMIT 1
");

$email = 'dmbosse104@gmail.com';
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

echo "<h3>Résultat de la requête SQL directe :</h3>";
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p><strong>✅ Abonnement trouvé par requête directe</strong></p>";
    echo "<p><strong>ID:</strong> " . $row['id'] . "</p>";
    echo "<p><strong>École:</strong> " . $row['school_name'] . "</p>";
    echo "<p><strong>Statut:</strong> " . $row['payment_status'] . "</p>";
    echo "<p><strong>Status calculé:</strong> " . $row['status'] . "</p>";
} else {
    echo "<p><strong>❌ Aucun abonnement trouvé par requête directe</strong></p>";
}

echo "<h2>3. Test de la page de renouvellement</h2>";
echo "<p><a href='module/subscription/renew.php?email=dmbosse104%40gmail.com' target='_blank' class='btn btn-primary'>";
echo "Tester la page de renouvellement</a></p>";
?>
