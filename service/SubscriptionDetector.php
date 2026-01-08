<?php
require_once __DIR__ . '/mysqlcon.php';
require_once __DIR__ . '/auto_fix_expired.php';

class SubscriptionDetector {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        // Corriger automatiquement les abonnements expirés avant toute détection
        autoFixExpiredSubscriptions($this->db);
    }
    
    /**
     * Détecte le statut d'abonnement d'un utilisateur
     * @param string $email Email de l'utilisateur
     * @param string $school_name Nom de l'école (optionnel)
     * @return array Informations sur l'abonnement
     */
    public function detectSubscriptionStatus($email = null, $school_name = null) {
        try {
            // Si on a un email, chercher par email
            if ($email) {
            $stmt = $this->db->prepare("
                SELECT s.*, 
                       CASE 
                           WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) >= CURDATE() THEN 'active'
                           WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) < CURDATE() THEN 'expired'
                           WHEN s.payment_status = 'expired' THEN 'expired'
                           WHEN s.payment_status = 'pending' THEN 'pending'
                           WHEN s.payment_status = 'failed' THEN 'failed'
                           ELSE 'unknown'
                       END as status,
                       DATEDIFF(s.expiry_date, CURDATE()) as days_until_expiry
                FROM subscriptions s
                WHERE s.admin_email = ? AND s.expiry_date != '0000-00-00 00:00:00'
                ORDER BY 
                    CASE 
                        WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) < CURDATE() THEN 1
                        WHEN s.payment_status = 'expired' THEN 2
                        WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) >= CURDATE() THEN 3
                        WHEN s.payment_status = 'pending' THEN 4
                        WHEN s.payment_status = 'failed' THEN 5
                        ELSE 6
                    END,
                    s.expiry_date DESC, s.created_at DESC
                LIMIT 1
            ");
                $stmt->bind_param("s", $email);
            }
            // Sinon, chercher par nom d'école
            elseif ($school_name) {
                $stmt = $this->db->prepare("
                SELECT s.*, 
                       CASE 
                           WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) >= CURDATE() THEN 'active'
                           WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) < CURDATE() THEN 'expired'
                           WHEN s.payment_status = 'expired' THEN 'expired'
                           WHEN s.payment_status = 'pending' THEN 'pending'
                           WHEN s.payment_status = 'failed' THEN 'failed'
                           ELSE 'unknown'
                       END as status,
                       DATEDIFF(s.expiry_date, CURDATE()) as days_until_expiry
                FROM subscriptions s
                WHERE s.school_name = ?
                ORDER BY 
                    CASE 
                        WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) < CURDATE() THEN 1
                        WHEN s.payment_status = 'expired' THEN 2
                        WHEN s.payment_status = 'completed' AND DATE(s.expiry_date) >= CURDATE() THEN 3
                        WHEN s.payment_status = 'pending' THEN 4
                        WHEN s.payment_status = 'failed' THEN 5
                        ELSE 6
                    END,
                    s.expiry_date DESC, s.created_at DESC
                LIMIT 1
            ");
                $stmt->bind_param("s", $school_name);
            }
            else {
                return [
                    'exists' => false,
                    'status' => 'not_found',
                    'message' => 'Aucun critère de recherche fourni'
                ];
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return [
                    'exists' => false,
                    'status' => 'not_found',
                    'message' => 'Aucun abonnement trouvé'
                ];
            }
            
            $subscription = $result->fetch_assoc();
            
            return [
                'exists' => true,
                'subscription' => $subscription,
                'status' => $subscription['status'],
                'days_until_expiry' => $subscription['days_until_expiry'],
                'can_renew' => $this->canRenew($subscription),
                'renewal_url' => $this->generateRenewalUrl($subscription),
                'message' => $this->getStatusMessage($subscription)
            ];
            
        } catch (Exception $e) {
            error_log("Erreur lors de la détection d'abonnement : " . $e->getMessage());
            return [
                'exists' => false,
                'status' => 'error',
                'message' => 'Erreur lors de la vérification de l\'abonnement'
            ];
        }
    }
    
    /**
     * Vérifie si un abonnement peut être renouvelé
     */
    private function canRenew($subscription) {
        $status = $subscription['status'];
        $days_until_expiry = $subscription['days_until_expiry'];
        
        // Peut renouveler si :
        // - Abonnement expiré
        // - Abonnement actif mais expire dans moins de 7 jours
        // - Paiement en attente ou échoué
        return in_array($status, ['expired', 'failed', 'pending']) || 
               ($status === 'active' && $days_until_expiry <= 7);
    }
    
    /**
     * Génère l'URL de renouvellement
     */
    private function generateRenewalUrl($subscription) {
        $school_name = urlencode($subscription['school_name']);
        return "/gestion/module/subscription/renew.php?school={$school_name}";
    }
    
    /**
     * Retourne un message approprié selon le statut
     */
    private function getStatusMessage($subscription) {
        $status = $subscription['status'];
        $days = $subscription['days_until_expiry'];
        
        switch ($status) {
            case 'active':
                if ($days > 7) {
                    return "Votre abonnement est actif jusqu'au " . date('d/m/Y', strtotime($subscription['expiry_date']));
                } else {
                    return "Votre abonnement expire bientôt (" . date('d/m/Y', strtotime($subscription['expiry_date'])) . "). Renouvelez-le maintenant !";
                }
            case 'expired':
                return "Votre abonnement a expiré le " . date('d/m/Y', strtotime($subscription['expiry_date'])) . ". Renouvelez-le pour continuer.";
            case 'pending':
                return "Votre paiement est en attente. Finalisez votre abonnement.";
            case 'failed':
                return "Votre dernier paiement a échoué. Réessayez maintenant.";
            default:
                return "Statut d'abonnement inconnu.";
        }
    }
    
    /**
     * Détecte le statut pour un utilisateur connecté
     */
    public function detectForLoggedUser() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
            return [
                'exists' => false,
                'status' => 'not_logged',
                'message' => 'Utilisateur non connecté'
            ];
        }
        
        $user_id = $_SESSION['user_id'];
        $user_type = $_SESSION['user_type'];
        
        try {
            // Récupérer l'email de l'utilisateur selon son type
            $email = null;
            
            if ($user_type === 'admin') {
                $stmt = $this->db->prepare("SELECT email FROM admin WHERE id = ?");
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    $email = $admin['email'];
                }
            }
            
            if ($email) {
                return $this->detectSubscriptionStatus($email);
            } else {
                return [
                    'exists' => false,
                    'status' => 'no_email',
                    'message' => 'Email non trouvé pour cet utilisateur'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Erreur lors de la détection pour utilisateur connecté : " . $e->getMessage());
            return [
                'exists' => false,
                'status' => 'error',
                'message' => 'Erreur lors de la vérification'
            ];
        }
    }
    
    /**
     * Génère le HTML du bouton d'abonnement intelligent
     */
    public function generateSmartSubscriptionButton($email = null, $school_name = null) {
        $detection = $this->detectSubscriptionStatus($email, $school_name);
        
        if (!$detection['exists']) {
            // Nouvel utilisateur - bouton d'inscription
            return [
                'text' => 'S\'abonner',
                'url' => '/gestion/module/subscription/register.php',
                'class' => 'btn-success',
                'icon' => 'fas fa-crown',
                'message' => 'Commencez votre abonnement'
            ];
        }
        
        $subscription = $detection['subscription'];
        $status = $detection['status'];
        
        if ($detection['can_renew']) {
            // Peut renouveler
            return [
                'text' => 'Renouveler',
                'url' => $detection['renewal_url'],
                'class' => 'btn-warning',
                'icon' => 'fas fa-sync-alt',
                'message' => $detection['message']
            ];
        } else {
            // Abonnement actif
            return [
                'text' => 'Mon Abonnement',
                'url' => '/gestion/module/subscription/dashboard.php',
                'class' => 'btn-info',
                'icon' => 'fas fa-user-check',
                'message' => $detection['message']
            ];
        }
    }
}
?>
