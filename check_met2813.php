<?php
require_once 'service/mysqlcon.php';
require_once 'service/SubscriptionDetector.php';

echo "<h1>Vérification du compte MET2813</h1>";

// Rechercher par ID admin
$admin_id = 'MET2813';
$stmt = $link->prepare('SELECT * FROM admin WHERE id = ?');
$stmt->bind_param('s', $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<h2>✅ Admin trouvé</h2>";
    echo "<p><strong>Nom:</strong> " . htmlspecialchars($admin['name']) . "</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($admin['email']) . "</p>";
    echo "<p><strong>ID:</strong> " . htmlspecialchars($admin['id']) . "</p>";
    
    // Rechercher l'abonnement
    $stmt2 = $link->prepare('SELECT * FROM subscriptions WHERE admin_email = ?');
    $stmt2->bind_param('s', $admin['email']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($result2->num_rows > 0) {
        $subscription = $result2->fetch_assoc();
        echo "<h2>📋 Abonnement trouvé</h2>";
        echo "<p><strong>École:</strong> " . htmlspecialchars($subscription['school_name']) . "</p>";
        echo "<p><strong>Statut:</strong> " . htmlspecialchars($subscription['payment_status']) . "</p>";
        echo "<p><strong>Expiration:</strong> " . htmlspecialchars($subscription['expiry_date']) . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($subscription['admin_email']) . "</p>";
        
        // Vérifier avec le détecteur
        $detector = new SubscriptionDetector($link);
        $detection = $detector->detectSubscriptionStatus($admin['email']);
        
        echo "<h2>🔍 Analyse du statut</h2>";
        echo "<p><strong>Peut renouveler:</strong> " . ($detection['can_renew'] ? 'OUI' : 'NON') . "</p>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($detection['message']) . "</p>";
        
        if ($detection['can_renew']) {
            echo "<h2>🔄 Actions disponibles</h2>";
            echo "<p><a href='module/subscription/renew.php?email=" . urlencode($admin['email']) . "' class='btn btn-warning'>Renouveler l'abonnement</a></p>";
            echo "<p><a href='module/subscription/dashboard.php' class='btn btn-info'>Tableau de bord</a></p>";
        }
        
    } else {
        echo "<h2>❌ Aucun abonnement trouvé</h2>";
        echo "<p>Aucun abonnement trouvé pour l'email: " . htmlspecialchars($admin['email']) . "</p>";
        echo "<p><a href='module/subscription/register.php' class='btn btn-success'>Créer un abonnement</a></p>";
    }
} else {
    echo "<h2>❌ Admin MET2813 non trouvé</h2>";
    echo "<p>Le compte administrateur avec l'ID 'MET2813' n'existe pas dans la base de données.</p>";
    
    // Chercher des comptes similaires
    $stmt3 = $link->prepare("SELECT * FROM admin WHERE id LIKE ?");
    $like_param = '%MET%';
    $stmt3->bind_param('s', $like_param);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    
    if ($result3->num_rows > 0) {
        echo "<h3>Comptes similaires trouvés:</h3>";
        while ($row = $result3->fetch_assoc()) {
            echo "<p>- " . htmlspecialchars($row['id']) . " (" . htmlspecialchars($row['name']) . ")</p>";
        }
    }
}

echo "<hr>";
echo "<p><a href='index.php'>Retour à la page d'accueil</a></p>";
?>
