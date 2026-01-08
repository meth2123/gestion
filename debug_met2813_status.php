<?php
require_once 'service/mysqlcon.php';

echo "<h1>Debug du statut MET2813</h1>";

$stmt = $link->prepare('SELECT * FROM subscriptions WHERE admin_email = ?');
$email = 'dmbosse104@gmail.com';
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $sub = $result->fetch_assoc();
    echo "<h2>Informations de l'abonnement :</h2>";
    echo "<p><strong>Statut actuel:</strong> " . $sub['payment_status'] . "</p>";
    echo "<p><strong>Date expiration:</strong> " . $sub['expiry_date'] . "</p>";
    echo "<p><strong>Date actuelle:</strong> " . date('Y-m-d H:i:s') . "</p>";
    echo "<p><strong>Expiré:</strong> " . (strtotime($sub['expiry_date']) < time() ? 'OUI' : 'NON') . "</p>";
    
    // Vérifier la logique de détection
    echo "<h2>Test de la logique de détection :</h2>";
    
    $status = 'unknown';
    $days_until_expiry = 0;
    
    if ($sub['payment_status'] === 'completed' && strtotime($sub['expiry_date']) > time()) {
        $status = 'active';
        $days_until_expiry = floor((strtotime($sub['expiry_date']) - time()) / (60 * 60 * 24));
    } elseif ($sub['payment_status'] === 'completed' && strtotime($sub['expiry_date']) <= time()) {
        $status = 'expired';
        $days_until_expiry = floor((time() - strtotime($sub['expiry_date'])) / (60 * 60 * 24));
    } elseif ($sub['payment_status'] === 'expired') {
        $status = 'expired';
        $days_until_expiry = floor((time() - strtotime($sub['expiry_date'])) / (60 * 60 * 24));
    } elseif ($sub['payment_status'] === 'pending') {
        $status = 'pending';
    } elseif ($sub['payment_status'] === 'failed') {
        $status = 'failed';
    }
    
    echo "<p><strong>Statut calculé:</strong> " . $status . "</p>";
    echo "<p><strong>Jours jusqu'à expiration:</strong> " . $days_until_expiry . "</p>";
    
    // Test de la méthode canRenew
    $can_renew = in_array($status, ['expired', 'failed', 'pending']) || 
                 ($status === 'active' && $days_until_expiry <= 7);
    
    echo "<p><strong>Peut renouveler:</strong> " . ($can_renew ? 'OUI' : 'NON') . "</p>";
    
} else {
    echo "<p>Aucun abonnement trouvé pour cet email.</p>";
}
?>
