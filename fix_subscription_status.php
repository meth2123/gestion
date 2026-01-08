<?php
/**
 * Script pour corriger le statut des abonnements qui ont été marqués comme expirés par erreur
 * Ce script remet le statut à 'completed' pour les abonnements dont la date d'expiration est dans le futur
 */

require_once __DIR__ . '/service/mysqlcon.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correction du statut des abonnements</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container py-5'>
        <div class='card shadow'>
            <div class='card-header bg-primary text-white'>
                <h3 class='mb-0'>Correction du statut des abonnements</h3>
            </div>
            <div class='card-body'>";

try {
    $link->begin_transaction();
    
    // Trouver tous les abonnements marqués comme expirés mais dont la date d'expiration est dans le futur
    $stmt = $link->prepare("
        SELECT id, school_name, admin_email, expiry_date, payment_status
        FROM subscriptions 
        WHERE payment_status = 'expired' 
        AND DATE(expiry_date) >= CURDATE()
        AND expiry_date != '0000-00-00 00:00:00'
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fixed_count = 0;
    $fixed_subscriptions = [];
    
    echo "<div class='alert alert-info'>
            <h5>Abonnements à corriger :</h5>
            <ul>";
    
    while ($subscription = $result->fetch_assoc()) {
        echo "<li><strong>École:</strong> " . htmlspecialchars($subscription['school_name']) . 
             " - <strong>Email:</strong> " . htmlspecialchars($subscription['admin_email']) . 
             " - <strong>Expire le:</strong> " . date('d/m/Y', strtotime($subscription['expiry_date'])) . "</li>";
        
        // Mettre à jour le statut à 'completed'
        $update_stmt = $link->prepare("
            UPDATE subscriptions 
            SET payment_status = 'completed',
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_stmt->bind_param("i", $subscription['id']);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows > 0) {
            $fixed_count++;
            $fixed_subscriptions[] = $subscription;
        }
    }
    
    echo "</ul></div>";
    
    $link->commit();
    
    if ($fixed_count > 0) {
        echo "<div class='alert alert-success'>
                <h5><i class='fas fa-check-circle'></i> Succès !</h5>
                <p><strong>{$fixed_count}</strong> abonnement(s) ont été corrigés avec succès.</p>
                <p>Ces abonnements sont maintenant marqués comme <strong>actifs</strong>.</p>
              </div>";
    } else {
        echo "<div class='alert alert-warning'>
                <h5><i class='fas fa-info-circle'></i> Aucune correction nécessaire</h5>
                <p>Tous les abonnements ont déjà le bon statut.</p>
              </div>";
    }
    
    echo "<div class='mt-3'>
            <a href='secure_subscription_check.php' class='btn btn-primary'>
                <i class='fas fa-search'></i> Vérifier mon abonnement
            </a>
            <a href='index.php' class='btn btn-secondary'>
                <i class='fas fa-home'></i> Retour à l'accueil
            </a>
          </div>";
    
} catch (Exception $e) {
    $link->rollback();
    echo "<div class='alert alert-danger'>
            <h5><i class='fas fa-exclamation-triangle'></i> Erreur</h5>
            <p>Une erreur s'est produite lors de la correction : " . htmlspecialchars($e->getMessage()) . "</p>
          </div>";
}

echo "      </div>
        </div>
    </div>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</body>
</html>";
?>
