<?php
require_once __DIR__ . '/../service/SubscriptionDetector.php';

class SecureSubscriptionChecker {
    private $detector;
    
    public function __construct($db) {
        $this->detector = new SubscriptionDetector($db);
    }
    
    /**
     * Génère un code de vérification temporaire
     */
    public function generateVerificationCode($email, $school_name = null) {
        $data = $email . ($school_name ? $school_name : '');
        $code = substr(md5($data . time()), 0, 8);
        
        // Stocker le code en session (expire dans 10 minutes)
        $_SESSION['verification_code'] = $code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['verification_school'] = $school_name;
        $_SESSION['verification_time'] = time();
        
        return $code;
    }
    
    /**
     * Vérifie le code de vérification
     */
    public function verifyCode($code) {
        if (!isset($_SESSION['verification_code']) || 
            !isset($_SESSION['verification_time']) ||
            $_SESSION['verification_code'] !== $code) {
            return false;
        }
        
        // Vérifier l'expiration (10 minutes)
        if (time() - $_SESSION['verification_time'] > 600) {
            $this->clearVerification();
            return false;
        }
        
        return true;
    }
    
    /**
     * Nettoie les données de vérification
     */
    public function clearVerification() {
        unset($_SESSION['verification_code']);
        unset($_SESSION['verification_email']);
        unset($_SESSION['verification_school']);
        unset($_SESSION['verification_time']);
    }
    
    /**
     * Affiche un formulaire de vérification sécurisé
     */
    public function renderSecureChecker() {
        return '
        <div class="secure-subscription-checker bg-light p-4 rounded mb-4">
            <h4><i class="fas fa-shield-alt"></i> Vérification sécurisée de votre abonnement</h4>
            <p class="text-muted">Pour des raisons de sécurité, nous devons vérifier votre identité avant d\'afficher les informations de votre abonnement.</p>
            
            <form method="POST" action="secure_subscription_check.php" class="row g-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email de votre compte</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="votre@email.com" required>
                </div>
                <div class="col-md-6">
                    <label for="school" class="form-label">Nom de votre école (optionnel)</label>
                    <input type="text" class="form-control" id="school" name="school" placeholder="Nom de votre école">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="consent" name="consent" required>
                        <label class="form-check-label" for="consent">
                            J\'accepte que mes informations soient utilisées uniquement pour vérifier mon abonnement
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-shield-alt"></i> Vérifier mon identité
                    </button>
                </div>
            </form>
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> 
                    Vos informations sont protégées et ne seront utilisées que pour vérifier votre abonnement.
                </small>
            </div>
        </div>';
    }
    
    /**
     * Affiche les résultats de vérification sécurisés
     */
    public function renderSecureResults($email = null, $school = null) {
        if (!$email && !$school) return '';
        
        $detection = $this->detector->detectSubscriptionStatus($email, $school);
        
        if (!$detection['exists']) {
            return '
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> Aucun abonnement trouvé</h5>
                <p>Nous n\'avons trouvé aucun abonnement avec ces informations.</p>
                <p><strong>Vérifiez que :</strong></p>
                <ul>
                    <li>L\'email est correct</li>
                    <li>Le nom de l\'école est exact</li>
                </ul>
                <a href="secure_subscription_check.php" class="btn btn-success">
                    <i class="fas fa-search"></i> Nouvelle recherche
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

