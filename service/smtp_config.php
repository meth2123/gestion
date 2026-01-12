<?php
/**
 * Configuration SMTP centralisée avec support Resend
 * Utilisez ce fichier pour obtenir la configuration SMTP dans tout le projet
 * Supporte les variables d'environnement pour Render.com et autres plateformes
 * 
 * PRIORITÉ : Si RESEND_API_KEY est configurée, Resend sera utilisé automatiquement
 * Sinon, SMTP sera utilisé (Gmail, SendGrid, etc.)
 */

// Fonction helper pour obtenir les variables d'environnement (compatible avec getenv et $_ENV)
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        return $value !== false ? $value : $default;
    }
}

// Configuration SMTP : utiliser les variables d'environnement si disponibles, sinon valeurs par défaut
$smtp_config = [
    'host' => getEnvVar('SMTP_HOST', 'smtp.gmail.com'),
    'port' => (int)getEnvVar('SMTP_PORT', 587),
    'username' => getEnvVar('SMTP_USERNAME', 'methndiaye43@gmail.com'),
    'password' => getEnvVar('SMTP_PASSWORD', 'elaf cmwo iahy gghs'), // Mot de passe d'application Gmail
    'from_email' => getEnvVar('SMTP_FROM_EMAIL', 'methndiaye43@gmail.com'),
    'from_name' => getEnvVar('SMTP_FROM_NAME', 'SchoolManager'),
    'encryption' => getEnvVar('SMTP_ENCRYPTION', 'tls') // STARTTLS par défaut
];

// Fonction pour obtenir la configuration SMTP
function get_smtp_config() {
    global $smtp_config;
    return $smtp_config;
}

// Fonction pour nettoyer le mot de passe (enlever les espaces)
function get_clean_smtp_password() {
    global $smtp_config;
    return str_replace(' ', '', $smtp_config['password']);
}

/**
 * Configure les options SMTP pour PHPMailer, optimisées pour Render.com
 * 
 * IMPORTANT : Si vous rencontrez des erreurs "Connection timed out" sur Render.com :
 * 1. Render.com peut bloquer les connexions SMTP sortantes (ports 587/465)
 * 2. Gmail peut bloquer les connexions depuis certains serveurs cloud
 * 3. Solutions recommandées :
 *    - Utiliser un service SMTP tiers (SendGrid, Mailgun, Amazon SES)
 *    - Vérifier que les ports SMTP ne sont pas bloqués dans les paramètres réseau de Render
 *    - Activer "Accès moins sécurisé" dans Gmail (non recommandé pour la sécurité)
 *    - Utiliser un service de relais SMTP
 * 
 * @param PHPMailer $mail Instance de PHPMailer à configurer
 */
function configure_smtp_for_render($mail) {
    // Options SMTP améliorées pour Render.com
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT
        ]
    ];
    
    // Timeouts augmentés pour Render.com (connexions peuvent être lentes)
    $mail->Timeout = 60; // Timeout général de 60 secondes pour Render.com
    $mail->SMTPKeepAlive = false;
    $mail->SMTPAutoTLS = true;
    $mail->SMTPDebug = 0; // Désactiver le debug en production (mettre à 2 pour debug)
    
    // Timeout de connexion spécifique (si supporté par PHPMailer)
    if (property_exists($mail, 'SMTPConnectTimeout')) {
        $mail->SMTPConnectTimeout = 30;
    }
}

/**
 * Envoie un email en utilisant Resend (si configuré) ou SMTP (fallback)
 * Cette fonction unifie l'envoi d'emails dans toute l'application
 * 
 * @param string $to_email Email du destinataire
 * @param string $to_name Nom du destinataire
 * @param string $subject Sujet de l'email
 * @param string $html_body Corps HTML de l'email
 * @param string $text_body Corps texte de l'email (optionnel)
 * @return array ['success' => bool, 'message' => string]
 */
function send_email_unified($to_email, $to_name, $subject, $html_body, $text_body = null) {
    // Charger le service Resend
    require_once(__DIR__ . '/resend_service.php');
    
    // Vérifier si Resend est configuré (priorité)
    if (is_resend_configured()) {
        error_log("✅ Resend API configured - Using Resend API for email to: $to_email");
        $result = send_email_via_resend($to_email, $to_name, $subject, $html_body, $text_body);
        
        if ($result['success']) {
            return ['success' => true, 'message' => $result['message']];
        } else {
            // Si Resend échoue, essayer SMTP en fallback
            error_log("❌ Resend failed, falling back to SMTP: " . $result['message']);
        }
    } else {
        error_log("⚠️ RESEND_API_KEY not configured - Using SMTP (PHPMailer) for email to: $to_email");
    }
    
    // Fallback vers SMTP (PHPMailer)
    error_log("Using SMTP (PHPMailer) for email to: $to_email");
    
    try {
        // Vérifier si PHPMailer est disponible
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            return [
                'success' => false,
                'message' => 'PHPMailer non installé. Faites composer install dans le dossier racine.'
            ];
        }
        
        require_once(__DIR__ . '/../vendor/autoload.php');
        
        $smtp_config = get_smtp_config();
        $smtp_password = get_clean_smtp_password();
        
        // Utiliser les noms complets des classes (pas besoin de use dans une fonction)
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = $smtp_config['encryption'] === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Configurer les options SMTP optimisées pour Render.com
        configure_smtp_for_render($mail);
        
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body ?: strip_tags($html_body);
        
        $mail->send();
        
        return ['success' => true, 'message' => 'Email envoyé avec succès via SMTP'];
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur SMTP: ' . $e->getMessage()
        ];
    }
}

