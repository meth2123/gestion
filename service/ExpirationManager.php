<?php
/**
 * Gestionnaire d'expiration automatique des abonnements
 */

require_once __DIR__ . '/mysqlcon.php';

class ExpirationManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Vérifie et met à jour les abonnements expirés
     */
    public function checkAndUpdateExpiredSubscriptions() {
        try {
            $this->db->begin_transaction();

            // Trouver tous les abonnements completed qui sont expirés
            $stmt = $this->db->prepare("
                SELECT id, school_name, admin_email, expiry_date
                FROM subscriptions 
                WHERE payment_status = 'completed' 
                AND DATE(expiry_date) < CURDATE()
                AND expiry_date != '0000-00-00 00:00:00'
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $expired_count = 0;
            $expired_subscriptions = [];
            
            while ($subscription = $result->fetch_assoc()) {
                // Mettre à jour le statut à 'expired'
                $update_stmt = $this->db->prepare("
                    UPDATE subscriptions 
                    SET payment_status = 'expired',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $update_stmt->bind_param("i", $subscription['id']);
                $update_stmt->execute();
                
                if ($update_stmt->affected_rows > 0) {
                    $expired_count++;
                    $expired_subscriptions[] = $subscription;
                    
                    // Créer une notification d'expiration
                    $this->createExpirationNotification($subscription['id'], $subscription['school_name']);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'expired_count' => $expired_count,
                'expired_subscriptions' => $expired_subscriptions,
                'message' => "{$expired_count} abonnement(s) marqué(s) comme expiré(s)"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des abonnements expirés: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crée une notification d'expiration
     */
    private function createExpirationNotification($subscription_id, $school_name) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO subscription_notifications 
                (subscription_id, type, message, sent_at) 
                VALUES (?, 'expiration', ?, NOW())
            ");
            
            $message = "Votre abonnement pour {$school_name} a expiré. Veuillez le renouveler pour continuer à utiliser nos services.";
            $stmt->bind_param("is", $subscription_id, $message);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Erreur lors de la création de la notification d'expiration: " . $e->getMessage());
        }
    }

    /**
     * Vérifie les abonnements qui vont expirer bientôt (5 jours)
     */
    public function checkUpcomingExpirations() {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, 
                       DATEDIFF(s.expiry_date, CURDATE()) as days_until_expiry
                FROM subscriptions s
                WHERE s.payment_status = 'completed'
                AND DATE(s.expiry_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)
                AND s.expiry_date != '0000-00-00 00:00:00'
                AND NOT EXISTS (
                    SELECT 1 FROM subscription_notifications n
                    WHERE n.subscription_id = s.id
                    AND n.type = 'expiry_warning'
                    AND n.sent_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
                )
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $upcoming_count = 0;
            $upcoming_subscriptions = [];
            
            while ($subscription = $result->fetch_assoc()) {
                // Créer une notification d'avertissement
                $this->createExpiryWarningNotification($subscription['id'], $subscription['school_name'], $subscription['days_until_expiry']);
                
                $upcoming_count++;
                $upcoming_subscriptions[] = $subscription;
            }
            
            return [
                'success' => true,
                'upcoming_count' => $upcoming_count,
                'upcoming_subscriptions' => $upcoming_subscriptions,
                'message' => "{$upcoming_count} abonnement(s) vont expirer bientôt"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification des expirations proches: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crée une notification d'avertissement d'expiration
     */
    private function createExpiryWarningNotification($subscription_id, $school_name, $days_until_expiry) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO subscription_notifications 
                (subscription_id, type, message, sent_at) 
                VALUES (?, 'expiry_warning', ?, NOW())
            ");
            
            $message = "Votre abonnement pour {$school_name} expire dans {$days_until_expiry} jour(s). Veuillez le renouveler pour éviter l'interruption de service.";
            $stmt->bind_param("is", $subscription_id, $message);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Erreur lors de la création de la notification d'avertissement: " . $e->getMessage());
        }
    }

    /**
     * Obtient les statistiques des abonnements
     */
    public function getSubscriptionStats() {
        try {
            $stats = [];
            
            // Abonnements actifs
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM subscriptions 
                WHERE payment_status = 'completed' 
                AND DATE(expiry_date) >= CURDATE()
                AND expiry_date != '0000-00-00 00:00:00'
            ");
            $stmt->execute();
            $stats['active'] = $stmt->get_result()->fetch_assoc()['count'];
            
            // Abonnements expirés
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM subscriptions 
                WHERE payment_status = 'expired'
            ");
            $stmt->execute();
            $stats['expired'] = $stmt->get_result()->fetch_assoc()['count'];
            
            // Abonnements en attente
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM subscriptions 
                WHERE payment_status = 'pending'
            ");
            $stmt->execute();
            $stats['pending'] = $stmt->get_result()->fetch_assoc()['count'];
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
}
?>

