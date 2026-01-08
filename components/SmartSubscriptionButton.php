<?php
require_once __DIR__ . '/../service/SubscriptionDetector.php';

class SmartSubscriptionButton {
    private $detector;
    
    public function __construct($db) {
        $this->detector = new SubscriptionDetector($db);
    }
    
    /**
     * Génère le bouton d'abonnement intelligent
     */
    public function render($email = null, $school_name = null, $size = 'normal') {
        $button = $this->detector->generateSmartSubscriptionButton($email, $school_name);
        
        $sizeClass = $size === 'large' ? 'btn-lg' : '';
        $icon = $button['icon'];
        $text = $button['text'];
        $url = $button['url'];
        $class = $button['class'];
        $message = $button['message'];
        
        return "
        <div class='smart-subscription-container' data-toggle='tooltip' title='{$message}'>
            <a href='{$url}' class='btn {$class} {$sizeClass}'>
                <i class='{$icon} me-2'></i>{$text}
            </a>
        </div>";
    }
    
    /**
     * Génère le bouton pour un utilisateur connecté
     */
    public function renderForLoggedUser($size = 'normal') {
        $detection = $this->detector->detectForLoggedUser();
        
        if (!$detection['exists']) {
            // Utilisateur non connecté ou sans abonnement
            return $this->render(null, null, $size);
        }
        
        $subscription = $detection['subscription'];
        return $this->render($subscription['admin_email'], $subscription['school_name'], $size);
    }
    
    /**
     * Génère un menu déroulant avec options d'abonnement
     */
    public function renderDropdown($email = null, $school_name = null) {
        $detection = $this->detector->detectSubscriptionStatus($email, $school_name);
        
        if (!$detection['exists']) {
            // Nouvel utilisateur
            return "
            <div class='dropdown'>
                <button class='btn btn-success dropdown-toggle' type='button' data-bs-toggle='dropdown'>
                    <i class='fas fa-crown me-2'></i>S'abonner
                </button>
                <ul class='dropdown-menu'>
                    <li><a class='dropdown-item' href='/gestion/module/subscription/register.php'>
                        <i class='fas fa-plus-circle me-2'></i>Nouvel abonnement
                    </a></li>
                    <li><a class='dropdown-item' href='/gestion/login.php'>
                        <i class='fas fa-sign-in-alt me-2'></i>Se connecter
                    </a></li>
                </ul>
            </div>";
        }
        
        $subscription = $detection['subscription'];
        $status = $detection['status'];
        $canRenew = $detection['can_renew'];
        
        $statusIcon = [
            'active' => 'fas fa-check-circle text-success',
            'expired' => 'fas fa-exclamation-triangle text-warning',
            'pending' => 'fas fa-clock text-info',
            'failed' => 'fas fa-times-circle text-danger'
        ][$status] ?? 'fas fa-question-circle text-muted';
        
        $statusText = [
            'active' => 'Actif',
            'expired' => 'Expiré',
            'pending' => 'En attente',
            'failed' => 'Échoué'
        ][$status] ?? 'Inconnu';
        
        $dropdownItems = "
        <li><h6 class='dropdown-header'>
            <i class='{$statusIcon}'></i> {$statusText}
        </h6></li>
        <li><span class='dropdown-item-text small text-muted'>
            {$detection['message']}
        </span></li>
        <li><hr class='dropdown-divider'></li>";
        
        if ($canRenew) {
            $dropdownItems .= "
            <li><a class='dropdown-item' href='{$detection['renewal_url']}'>
                <i class='fas fa-sync-alt me-2'></i>Renouveler
            </a></li>";
        }
        
        $dropdownItems .= "
        <li><a class='dropdown-item' href='/gestion/module/subscription/dashboard.php'>
            <i class='fas fa-chart-line me-2'></i>Tableau de bord
        </a></li>
        <li><a class='dropdown-item' href='/gestion/module/subscription/register.php'>
            <i class='fas fa-plus-circle me-2'></i>Nouvel abonnement
        </a></li>";
        
        return "
        <div class='dropdown'>
            <button class='btn btn-outline-primary dropdown-toggle' type='button' data-bs-toggle='dropdown'>
                <i class='fas fa-user-check me-2'></i>Mon Abonnement
            </button>
            <ul class='dropdown-menu'>
                {$dropdownItems}
            </ul>
        </div>";
    }
}
?>
