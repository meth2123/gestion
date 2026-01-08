<?php
/**
 * Service de gestion des callbacks de paiement PayDunya
 */

require_once __DIR__ . '/mysqlcon.php';
require_once __DIR__ . '/SubscriptionService.php';

class PaymentCallback {
    private $db;
    private $subscriptionService;

    public function __construct($db) {
        $this->db = $db;
        $this->subscriptionService = new SubscriptionService($db);
    }

    /**
     * Traite un callback de paiement réussi
     */
    public function handleSuccessfulPayment($payment_reference, $transaction_id = null) {
        try {
            $this->db->begin_transaction();

            // Trouver l'abonnement associé à ce paiement
            $stmt = $this->db->prepare("
                SELECT s.*, sr.id as renewal_id
                FROM subscriptions s
                LEFT JOIN subscription_renewals sr ON sr.subscription_id = s.id
                WHERE sr.payment_reference = ? OR s.payment_reference = ?
                ORDER BY s.created_at DESC
                LIMIT 1
            ");
            
            $stmt->bind_param("ss", $payment_reference, $payment_reference);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Aucun abonnement trouvé pour cette référence de paiement");
            }
            
            $subscription = $result->fetch_assoc();
            
            // Mettre à jour le statut de l'abonnement
            $new_expiry_date = date('Y-m-d H:i:s', strtotime('+1 year'));
            
            $update_stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET payment_status = 'completed',
                    expiry_date = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $update_stmt->bind_param("si", $new_expiry_date, $subscription['id']);
            $update_stmt->execute();
            
            // Mettre à jour le renouvellement si il existe
            if ($subscription['renewal_id']) {
                $renewal_stmt = $this->db->prepare("
                    UPDATE subscription_renewals 
                    SET status = 'completed',
                        completed_at = NOW()
                    WHERE id = ?
                ");
                
                $renewal_stmt->bind_param("i", $subscription['renewal_id']);
                $renewal_stmt->execute();
            }
            
            // Créer une notification de succès
            $this->createSuccessNotification($subscription['id'], $payment_reference);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Abonnement réactivé avec succès',
                'subscription_id' => $subscription['id'],
                'new_expiry_date' => $new_expiry_date
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'Erreur lors de la réactivation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crée une notification de succès
     */
    private function createSuccessNotification($subscription_id, $payment_reference) {
        $stmt = $this->db->prepare("
            INSERT INTO subscription_notifications 
            (subscription_id, type, message, sent_at) 
            VALUES (?, 'payment_success', ?, NOW())
        ");
        
        $message = "Votre abonnement a été renouvelé avec succès. Référence: " . $payment_reference;
        $stmt->bind_param("is", $subscription_id, $message);
        $stmt->execute();
    }

    /**
     * Traite un callback de paiement échoué
     */
    public function handleFailedPayment($payment_reference, $error_message = null) {
        try {
            // Trouver l'abonnement
            $stmt = $this->db->prepare("
                SELECT s.*, sr.id as renewal_id
                FROM subscriptions s
                LEFT JOIN subscription_renewals sr ON sr.subscription_id = s.id
                WHERE sr.payment_reference = ? OR s.payment_reference = ?
                ORDER BY s.created_at DESC
                LIMIT 1
            ");
            
            $stmt->bind_param("ss", $payment_reference, $payment_reference);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $subscription = $result->fetch_assoc();
                
                // Mettre à jour le statut du renouvellement
                if ($subscription['renewal_id']) {
                    $renewal_stmt = $this->db->prepare("
                        UPDATE subscription_renewals 
                        SET status = 'failed',
                            error_message = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    
                    $renewal_stmt->bind_param("si", $error_message, $subscription['renewal_id']);
                    $renewal_stmt->execute();
                }
                
                // Créer une notification d'échec
                $this->createFailureNotification($subscription['id'], $error_message);
            }
            
            return [
                'success' => true,
                'message' => 'Statut de paiement mis à jour'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crée une notification d'échec
     */
    private function createFailureNotification($subscription_id, $error_message) {
        $stmt = $this->db->prepare("
            INSERT INTO subscription_notifications 
            (subscription_id, type, message, sent_at) 
            VALUES (?, 'payment_failure', ?, NOW())
        ");
        
        $message = "Le paiement a échoué. " . ($error_message ?: "Veuillez réessayer.");
        $stmt->bind_param("is", $subscription_id, $message);
        $stmt->execute();
    }
}
?>
