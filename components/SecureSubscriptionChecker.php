<?php
require_once __DIR__ . '/../service/SubscriptionDetector.php';
require_once __DIR__ . '/../service/smtp_config.php';

// Charger l'autoloader de Composer pour PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once(__DIR__ . '/../vendor/autoload.php');
}

// Importer PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SecureSubscriptionChecker {
    private $detector;
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
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
     * Affiche un formulaire de vérification sécurisé simplifié
     */
    public function renderSecureChecker() {
        return '
        <div class="secure-subscription-checker bg-light p-4 rounded mb-4">
            <h4><i class="fas fa-envelope"></i> Vérifier mon abonnement</h4>
            <p class="text-muted">Entrez votre adresse email et nous vous enverrons un lien de vérification sécurisé.</p>
            
            <form method="POST" action="check_subscription.php" class="row g-3" id="verificationForm">
                <div class="col-12">
                    <label for="email" class="form-label">Email de votre compte</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="votre@email.com" required>
                    <small class="form-text text-muted">Nous vous enverrons un lien de vérification par email</small>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Envoyer le lien de vérification
                    </button>
                </div>
            </form>
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-shield-alt"></i> 
                    Votre email est protégé et ne sera utilisé que pour vous envoyer le lien de vérification.
                </small>
            </div>
        </div>';
    }
    
    /**
     * Envoie un email avec les informations de l'abonnement directement
     */
    public function sendVerificationEmail($email) {
        try {
            // Vérifier si l'email existe dans les abonnements
            $detection = $this->detector->detectSubscriptionStatus($email, null);
            if (!$detection['exists']) {
                return [
                    'success' => false,
                    'message' => 'Aucun abonnement trouvé avec cet email. Vérifiez votre adresse email.'
                ];
            }
            
            // Préparer les données de l'abonnement pour l'email
            $subscription_data = [
                'subscription' => $detection['subscription'],
                'status' => $detection['status'],
                'message' => $detection['message'],
                'can_renew' => $detection['can_renew'] ?? false,
                'days_until_expiry' => $detection['days_until_expiry'] ?? 0
            ];
            
            // Envoyer l'email avec les informations de l'abonnement directement
            return $this->sendVerificationEmailMessage($email, $subscription_data);
            
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi de l'email de vérification : " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer plus tard.'
            ];
        }
    }
    
    /**
     * Envoie l'email avec les informations de l'abonnement directement
     */
    private function sendVerificationEmailMessage($email, $subscription_data) {
        try {
            if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
                return [
                    'success' => false,
                    'message' => 'PHPMailer n\'est pas installé. Impossible d\'envoyer l\'email.'
                ];
            }
            
            // Utiliser la configuration SMTP centralisée
            $smtp_config = get_smtp_config();
            
            // Préparer le contenu de l'email avant la configuration SMTP
            $subscription = $subscription_data['subscription'];
            $status = $subscription_data['status'];
            $days_until_expiry = $subscription_data['days_until_expiry'];
            $can_renew = $subscription_data['can_renew'];
            
            // Déterminer le statut et les couleurs
            $status_text = [
                'active' => 'Actif',
                'expired' => 'Expiré',
                'pending' => 'En attente',
                'failed' => 'Échoué'
            ][$status] ?? 'Inconnu';
            
            $status_color = [
                'active' => '#28a745',
                'expired' => '#ffc107',
                'pending' => '#17a2b8',
                'failed' => '#dc3545'
            ][$status] ?? '#6c757d';
            
            $status_icon = [
                'active' => '✓',
                'expired' => '⚠',
                'pending' => '⏳',
                'failed' => '✗'
            ][$status] ?? '?';
            
            // URL de renouvellement si nécessaire
            $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $base_url .= '://' . $_SERVER['HTTP_HOST'];
            $base_url .= dirname($_SERVER['PHP_SELF']);
            $renewal_url = $base_url . '/module/subscription/renew.php?email=' . urlencode($email);
            $login_url = $base_url . '/login.php';
            
            // Préparer le contenu de l'email
            $email_body = $this->prepareEmailBody($subscription, $status_text, $status_color, $status_icon, $days_until_expiry, $can_renew, $renewal_url, $login_url, $status);
            $email_alt_body = $this->prepareEmailAltBody($subscription, $status_text, $days_until_expiry, $can_renew, $renewal_url, $login_url, $status);
            
            // Essayer d'abord avec le mot de passe nettoyé (sans espaces) - méthode recommandée pour Gmail
            $password_clean = get_clean_smtp_password();
            $password_original = $smtp_config['password'];
            
            // Essayer avec le mot de passe nettoyé d'abord
            $mail = $this->createPHPMailerInstance($smtp_config, $email, $password_clean);
            $mail->Subject = 'Informations de votre abonnement SchoolManager';
            $mail->Body = $email_body;
            $mail->AltBody = $email_alt_body;
            
            try {
                $mail->send();
                return [
                    'success' => true,
                    'message' => 'Les informations de votre abonnement ont été envoyées à votre adresse email. Veuillez vérifier votre boîte de réception.'
                ];
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                error_log("Tentative 1 (mot de passe nettoyé) échouée : " . $error_message);
                
                // Si l'erreur est liée à l'authentification, essayer avec le mot de passe original (avec espaces)
                if (strpos($error_message, 'Could not authenticate') !== false || 
                    strpos($error_message, 'SMTP Error: Could not authenticate') !== false ||
                    strpos($error_message, 'authentication') !== false) {
                    
                    error_log("Tentative avec mot de passe original (avec espaces)...");
                    try {
                        $mail = $this->createPHPMailerInstance($smtp_config, $email, $password_original);
                        $mail->Subject = 'Informations de votre abonnement SchoolManager';
                        $mail->Body = $email_body;
                        $mail->AltBody = $email_alt_body;
                        $mail->send();
                        
                        error_log("Succès avec mot de passe original (avec espaces)");
                        return [
                            'success' => true,
                            'message' => 'Les informations de votre abonnement ont été envoyées à votre adresse email. Veuillez vérifier votre boîte de réception.'
                        ];
                    } catch (Exception $e2) {
                        $error_message = $e2->getMessage();
                        error_log("Tentative 2 (mot de passe original) échouée : " . $error_message);
                        error_log("SMTP ErrorInfo : " . (isset($mail) ? $mail->ErrorInfo : 'N/A'));
                    }
                } else {
                    // Si ce n'est pas une erreur d'authentification, propager l'erreur
                    throw $e;
                }
            }
            
            // Si on arrive ici, les deux tentatives ont échoué
            error_log("Les deux tentatives d'authentification SMTP ont échoué");
            error_log("SMTP ErrorInfo final : " . (isset($mail) ? $mail->ErrorInfo : 'N/A'));
            
            return [
                'success' => false,
                'message' => 'Erreur d\'authentification SMTP. Veuillez vérifier que le mot de passe d\'application Gmail est correct et valide. Si le problème persiste, contactez l\'administrateur.'
            ];
            
        } catch (Exception $e) {
            // Gérer les autres erreurs non liées à l'authentification
            $error_message = $e->getMessage();
            error_log("Erreur PHPMailer (non-authentification) : " . $error_message);
            error_log("SMTP ErrorInfo : " . (isset($mail) ? $mail->ErrorInfo : 'N/A'));
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email : ' . $error_message
            ];
        }
    }
    
    /**
     * Crée une instance PHPMailer configurée
     */
    private function createPHPMailerInstance($smtp_config, $email, $password) {
        $mail = new PHPMailer(true);
        
        // Configuration SMTP avec timeouts et options améliorées
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Options SMTP améliorées pour les connexions lentes ou depuis des serveurs distants
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Timeouts augmentés pour les connexions lentes (comme depuis Render vers Gmail)
        $mail->Timeout = 30; // Timeout général de 30 secondes
        $mail->SMTPKeepAlive = false; // Ne pas garder la connexion ouverte
        
        // Options de connexion
        $mail->SMTPAutoTLS = true; // Activer TLS automatiquement
        
        // Destinataires
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addAddress($email);
        $mail->isHTML(true);
        
        return $mail;
    }
    
    /**
     * Prépare le corps HTML de l'email
     */
    private function prepareEmailBody($subscription, $status_text, $status_color, $status_icon, $days_until_expiry, $can_renew, $renewal_url, $login_url, $status) {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background: #f9fafb; }
                .info-box { background: white; border-left: 4px solid {$status_color}; padding: 15px; margin: 15px 0; border-radius: 4px; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
                .info-label { font-weight: bold; color: #6b7280; }
                .info-value { color: #111827; }
                .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; color: white; background: {$status_color}; font-weight: bold; }
                .button { display: inline-block; padding: 12px 24px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .button-warning { background: #ffc107; color: #000; }
                .button-success { background: #28a745; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Informations de votre abonnement</h1>
                </div>
                <div class='content'>
                    <p>Bonjour,</p>
                    <p>Vous avez demandé à vérifier votre abonnement SchoolManager. Voici les informations de votre abonnement :</p>
                    
                    <div class='info-box'>
                        <div style='text-align: center; margin-bottom: 15px;'>
                            <span class='status-badge'>{$status_icon} {$status_text}</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='info-label'>École :</span>
                            <span class='info-value'>" . htmlspecialchars($subscription['school_name']) . "</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='info-label'>Email :</span>
                            <span class='info-value'>" . htmlspecialchars($subscription['admin_email']) . "</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='info-label'>Date d'expiration :</span>
                            <span class='info-value'>" . date('d/m/Y', strtotime($subscription['expiry_date'])) . "</span>
                        </div>
                        
                        <div class='info-row'>
                            <span class='info-label'>Jours restants :</span>
                            <span class='info-value'>" . ($days_until_expiry < 0 ? abs($days_until_expiry) . ' jour(s) en retard' : $days_until_expiry . ' jour(s)') . "</span>
                        </div>
                        
                        " . ($subscription['admin_phone'] ? "
                        <div class='info-row'>
                            <span class='info-label'>Téléphone :</span>
                            <span class='info-value'>" . htmlspecialchars($subscription['admin_phone']) . "</span>
                        </div>
                        " : "") . "
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        " . ($can_renew ? "
                        <a href='{$renewal_url}' class='button button-warning'>Renouveler mon abonnement</a>
                        " : "") . "
                        " . ($status === 'active' ? "
                        <a href='{$login_url}' class='button button-success'>Se connecter</a>
                        " : "") . "
                    </div>
                    
                    <p style='margin-top: 20px; color: #6b7280; font-size: 0.9em;'>
                        Si vous n'avez pas demandé cette vérification, vous pouvez ignorer cet email en toute sécurité.
                    </p>
                </div>
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                    <p>&copy; " . date('Y') . " SchoolManager. Tous droits réservés.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Prépare la version texte de l'email
     */
    private function prepareEmailAltBody($subscription, $status_text, $days_until_expiry, $can_renew, $renewal_url, $login_url, $status) {
        return "Informations de votre abonnement SchoolManager\n\n" .
            "Statut: {$status_text}\n" .
            "École: " . htmlspecialchars($subscription['school_name']) . "\n" .
            "Email: " . htmlspecialchars($subscription['admin_email']) . "\n" .
            "Date d'expiration: " . date('d/m/Y', strtotime($subscription['expiry_date'])) . "\n" .
            "Jours restants: " . ($days_until_expiry < 0 ? abs($days_until_expiry) . ' jour(s) en retard' : $days_until_expiry . ' jour(s)') . "\n\n" .
            ($can_renew ? "Pour renouveler votre abonnement, visitez: {$renewal_url}\n" : "") .
            ($status === 'active' ? "Pour vous connecter, visitez: {$login_url}\n" : "");
    }
    
    /**
     * Vérifie un token de vérification
     */
    public function verifyToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT email, expires_at, used
                FROM subscription_verification_tokens
                WHERE token = ? AND used = FALSE
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return [
                    'valid' => false,
                    'message' => 'Token invalide ou déjà utilisé.'
                ];
            }
            
            $row = $result->fetch_assoc();
            
            // Vérifier l'expiration
            if (strtotime($row['expires_at']) < time()) {
                return [
                    'valid' => false,
                    'message' => 'Ce lien de vérification a expiré. Veuillez en demander un nouveau.'
                ];
            }
            
            // Marquer le token comme utilisé
            $update_stmt = $this->db->prepare("
                UPDATE subscription_verification_tokens
                SET used = TRUE
                WHERE token = ?
            ");
            $update_stmt->bind_param("s", $token);
            $update_stmt->execute();
            
            return [
                'valid' => true,
                'email' => $row['email']
            ];
            
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification du token : " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Erreur lors de la vérification. Veuillez réessayer.'
            ];
        }
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

