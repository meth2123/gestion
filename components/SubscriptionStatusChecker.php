<?php
require_once __DIR__ . '/../service/SubscriptionDetector.php';

class SubscriptionStatusChecker {
    private $detector;
    
    public function __construct($db) {
        $this->detector = new SubscriptionDetector($db);
    }
    
    /**
     * Affiche un formulaire de vérification d'abonnement pour les utilisateurs non connectés
     */
    public function renderStatusChecker() {
        return '
        <div class="subscription-status-checker bg-light p-4 rounded mb-4">
            <h4><i class="fas fa-search"></i> Vérifier mon abonnement</h4>
            <p class="text-muted">Entrez votre email ou nom d\'école pour vérifier votre statut d\'abonnement</p>
            
            <form method="GET" action="check_subscription_status.php" class="row g-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="votre@email.com">
                </div>
                <div class="col-md-6">
                    <label for="school" class="form-label">Nom de l\'école</label>
                    <input type="text" class="form-control" id="school" name="school" placeholder="Nom de votre école">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Vérifier mon statut
                    </button>
                </div>
            </form>
        </div>';
    }
    
    /**
     * Affiche un message d'alerte pour les abonnements expirés
     */
    public function renderExpiredAlert($email = null, $school = null) {
        if (!$email && !$school) return '';
        
        $detection = $this->detector->detectSubscriptionStatus($email, $school);
        
        if (!$detection['exists']) {
            return '
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Aucun abonnement trouvé</h5>
                <p>Nous n\'avons trouvé aucun abonnement avec ces informations.</p>
                <a href="module/subscription/register.php" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> Créer un nouvel abonnement
                </a>
            </div>';
        }
        
        $subscription = $detection['subscription'];
        $status = $detection['status'];
        
        if ($status === 'expired') {
            return '
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Abonnement expiré</h5>
                <p><strong>École:</strong> ' . htmlspecialchars($subscription['school_name']) . '</p>
                <p><strong>Expiré le:</strong> ' . date('d/m/Y', strtotime($subscription['expiry_date'])) . '</p>
                <p>Votre abonnement a expiré. Renouvelez-le pour continuer à utiliser nos services.</p>
                <div class="mt-3">
                    <a href="module/subscription/renew.php?email=' . urlencode($subscription['admin_email']) . '" class="btn btn-warning me-2">
                        <i class="fas fa-sync-alt"></i> Renouveler mon abonnement
                    </a>
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </a>
                </div>
            </div>';
        }
        
        if ($status === 'active') {
            return '
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle"></i> Abonnement actif</h5>
                <p><strong>École:</strong> ' . htmlspecialchars($subscription['school_name']) . '</p>
                <p><strong>Expire le:</strong> ' . date('d/m/Y', strtotime($subscription['expiry_date'])) . '</p>
                <p>Votre abonnement est actif. Vous pouvez vous connecter pour accéder à votre espace.</p>
                <a href="login.php" class="btn btn-success">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>';
        }
        
        if ($status === 'pending') {
            return '
            <div class="alert alert-info">
                <h5><i class="fas fa-clock"></i> Paiement en attente</h5>
                <p><strong>École:</strong> ' . htmlspecialchars($subscription['school_name']) . '</p>
                <p>Votre paiement est en attente de confirmation. Veuillez finaliser votre abonnement.</p>
                <a href="module/subscription/renew.php?email=' . urlencode($subscription['admin_email']) . '" class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Finaliser le paiement
                </a>
            </div>';
        }
        
        return '
        <div class="alert alert-secondary">
            <h5><i class="fas fa-question-circle"></i> Statut inconnu</h5>
            <p>Statut d\'abonnement: ' . htmlspecialchars($status) . '</p>
            <a href="module/subscription/register.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Créer un abonnement
            </a>
        </div>';
    }
}
?>

