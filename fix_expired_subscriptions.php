<?php
/**
 * Script pour corriger les abonnements expirés qui n'ont pas été mis à jour automatiquement
 * Ce script met à jour le statut de 'completed' à 'expired' pour les abonnements dont la date d'expiration est passée
 */

require_once __DIR__ . '/service/mysqlcon.php';
require_once __DIR__ . '/service/ExpirationManager.php';

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est admin (optionnel, peut être retiré pour un script public)
$is_admin = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'admin';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction des abonnements expirés</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .subscription-item {
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
            border-radius: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h3 class="mb-0">
                    <i class="fas fa-exclamation-triangle"></i> Correction des abonnements expirés
                </h3>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Utiliser ExpirationManager pour la cohérence
                    $expirationManager = new ExpirationManager($link);
                    
                    // Vérifier d'abord les abonnements qui doivent être expirés
                    // Utiliser NOW() pour comparer avec l'heure exacte
                    $check_stmt = $link->prepare("
                        SELECT id, school_name, admin_email, expiry_date, payment_status, created_at
                        FROM subscriptions 
                        WHERE payment_status = 'completed' 
                        AND expiry_date < NOW()
                        AND expiry_date != '0000-00-00 00:00:00'
                        AND expiry_date IS NOT NULL
                        ORDER BY expiry_date ASC
                    ");
                    
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    $expired_subscriptions = [];
                    while ($row = $result->fetch_assoc()) {
                        $expired_subscriptions[] = $row;
                    }
                    
                    if (count($expired_subscriptions) > 0) {
                        echo "<div class='alert alert-warning'>
                                <h5><i class='fas fa-info-circle'></i> Abonnements à corriger</h5>
                                <p><strong>" . count($expired_subscriptions) . "</strong> abonnement(s) trouvé(s) qui doivent être marqués comme expirés.</p>
                              </div>";
                        
                        echo "<div class='table-responsive'>
                                <table class='table table-striped'>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>École</th>
                                            <th>Email</th>
                                            <th>Date d'expiration</th>
                                            <th>Statut actuel</th>
                                            <th>Jours depuis expiration</th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                        
                        foreach ($expired_subscriptions as $sub) {
                            $days_expired = floor((time() - strtotime($sub['expiry_date'])) / (60 * 60 * 24));
                            echo "<tr>
                                    <td>{$sub['id']}</td>
                                    <td>" . htmlspecialchars($sub['school_name']) . "</td>
                                    <td>" . htmlspecialchars($sub['admin_email']) . "</td>
                                    <td>" . date('d/m/Y H:i', strtotime($sub['expiry_date'])) . "</td>
                                    <td><span class='badge bg-warning'>{$sub['payment_status']}</span></td>
                                    <td><span class='badge bg-danger'>{$days_expired} jour(s)</span></td>
                                  </tr>";
                        }
                        
                        echo "</tbody></table></div>";
                        
                        // Si on a un paramètre action=fix, on corrige
                        if (isset($_GET['action']) && $_GET['action'] === 'fix') {
                            $link->begin_transaction();
                            
                            try {
                                $fixed_count = 0;
                                
                                foreach ($expired_subscriptions as $sub) {
                                    $update_stmt = $link->prepare("
                                        UPDATE subscriptions 
                                        SET payment_status = 'expired',
                                            updated_at = NOW()
                                        WHERE id = ? AND payment_status = 'completed'
                                    ");
                                    
                                    $update_stmt->bind_param("i", $sub['id']);
                                    $update_stmt->execute();
                                    
                                    if ($update_stmt->affected_rows > 0) {
                                        $fixed_count++;
                                        
                                        // Créer une notification d'expiration
                                        try {
                                            $notif_stmt = $link->prepare("
                                                INSERT INTO subscription_notifications 
                                                (subscription_id, type, message, sent_at) 
                                                VALUES (?, 'expiration', ?, NOW())
                                            ");
                                            
                                            $message = "Votre abonnement pour {$sub['school_name']} a expiré le " . date('d/m/Y', strtotime($sub['expiry_date'])) . ". Veuillez le renouveler pour continuer à utiliser nos services.";
                                            $notif_stmt->bind_param("is", $sub['id'], $message);
                                            $notif_stmt->execute();
                                        } catch (Exception $e) {
                                            error_log("Erreur lors de la création de la notification: " . $e->getMessage());
                                        }
                                    }
                                }
                                
                                $link->commit();
                                
                                echo "<div class='alert alert-success mt-3'>
                                        <h5><i class='fas fa-check-circle'></i> Correction réussie !</h5>
                                        <p><strong>{$fixed_count}</strong> abonnement(s) ont été mis à jour avec succès.</p>
                                        <p>Le statut a été changé de <strong>completed</strong> à <strong>expired</strong>.</p>
                                      </div>";
                                
                                // Recharger la page après 2 secondes pour voir les résultats
                                echo "<script>
                                        setTimeout(function() {
                                            window.location.href = 'fix_expired_subscriptions.php';
                                        }, 2000);
                                      </script>";
                                
                            } catch (Exception $e) {
                                $link->rollback();
                                throw $e;
                            }
                        } else {
                            // Afficher le bouton pour corriger
                            echo "<div class='mt-3'>
                                    <a href='?action=fix' class='btn btn-danger btn-lg'>
                                        <i class='fas fa-wrench'></i> Corriger ces abonnements
                                    </a>
                                    <a href='module/admin/check_subscriptions.php' class='btn btn-secondary'>
                                        <i class='fas fa-list'></i> Voir tous les abonnements
                                    </a>
                                  </div>";
                        }
                        
                    } else {
                        echo "<div class='alert alert-success'>
                                <h5><i class='fas fa-check-circle'></i> Aucune correction nécessaire</h5>
                                <p>Tous les abonnements ont déjà le bon statut.</p>
                                <p>Aucun abonnement avec le statut 'completed' n'a une date d'expiration passée.</p>
                              </div>";
                    }
                    
                    // Afficher les statistiques
                    $stats_result = $expirationManager->getSubscriptionStats();
                    if ($stats_result['success']) {
                        $stats = $stats_result['stats'];
                        echo "<div class='card mt-4'>
                                <div class='card-header bg-info text-white'>
                                    <h5 class='mb-0'><i class='fas fa-chart-bar'></i> Statistiques des abonnements</h5>
                                </div>
                                <div class='card-body'>
                                    <div class='row'>
                                        <div class='col-md-4'>
                                            <div class='text-center'>
                                                <h3 class='text-success'>{$stats['active']}</h3>
                                                <p class='text-muted'>Actifs</p>
                                            </div>
                                        </div>
                                        <div class='col-md-4'>
                                            <div class='text-center'>
                                                <h3 class='text-danger'>{$stats['expired']}</h3>
                                                <p class='text-muted'>Expirés</p>
                                            </div>
                                        </div>
                                        <div class='col-md-4'>
                                            <div class='text-center'>
                                                <h3 class='text-warning'>{$stats['pending']}</h3>
                                                <p class='text-muted'>En attente</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                              </div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>
                            <h5><i class='fas fa-exclamation-triangle'></i> Erreur</h5>
                            <p>Une erreur s'est produite : " . htmlspecialchars($e->getMessage()) . "</p>
                            <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
                          </div>";
                }
                ?>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Retour à l'accueil
                    </a>
                    <?php if ($is_admin): ?>
                        <a href="module/admin/check_subscriptions.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> Gestion des abonnements
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Information</h5>
            </div>
            <div class="card-body">
                <p>Ce script vérifie et corrige les abonnements dont la date d'expiration est passée mais qui sont encore marqués comme "completed".</p>
                <p><strong>Note :</strong> Pour une gestion automatique, configurez un cron job pour exécuter <code>cron/expiration_checker.php</code> quotidiennement.</p>
            </div>
        </div>
    </div>
</body>
</html>

