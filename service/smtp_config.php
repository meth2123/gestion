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
// Charge aussi un fichier .env si disponible (pour développement local)
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        // D'abord vérifier getenv() et $_ENV (pour production)
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        
        // Si pas trouvé, essayer de charger depuis un fichier .env (pour développement local)
        if ($value === false) {
            static $env_loaded = false;
            static $env_vars = [];
            
            if (!$env_loaded) {
                $env_file = __DIR__ . '/../.env';
                if (file_exists($env_file)) {
                    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        // Ignorer les commentaires
                        if (strpos(trim($line), '#') === 0) {
                            continue;
                        }
                        // Parser les lignes KEY=VALUE
                        if (strpos($line, '=') !== false) {
                            list($env_key, $env_value) = explode('=', $line, 2);
                            $env_key = trim($env_key);
                            $env_value = trim($env_value);
                            // Enlever les guillemets si présents
                            $env_value = trim($env_value, '"\'');
                            $env_vars[$env_key] = $env_value;
                        }
                    }
                }
                $env_loaded = true;
            }
            
            if (isset($env_vars[$key])) {
                $value = $env_vars[$key];
            }
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
/**
 * DÉPRÉCIÉ : SMTP a été complètement supprimé
 * Cette fonction est conservée uniquement pour compatibilité mais ne fait rien
 * 
 * @param mixed $mail (ignoré)
 * @deprecated Utilisez Resend uniquement via send_email_unified()
 */
function configure_smtp_for_render($mail) {
    // SMTP supprimé - cette fonction ne fait rien
    error_log("⚠️ configure_smtp_for_render() appelée mais SMTP est supprimé. Utilisez Resend uniquement.");
}

/**
 * Envoie un email en utilisant UNIQUEMENT Resend
 * SMTP a été complètement supprimé - Resend est maintenant obligatoire
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
    
    // Vérifier si Resend est configuré (OBLIGATOIRE)
    if (!is_resend_configured()) {
        $env_file = __DIR__ . '/../.env';
        $env_exists = file_exists($env_file);
        $message = 'RESEND_API_KEY non configurée. ';
        if (!$env_exists) {
            $message .= 'Créez un fichier .env à la racine du projet avec RESEND_API_KEY=votre_clé_api. ';
        } else {
            $message .= 'Vérifiez que RESEND_API_KEY est bien définie dans le fichier .env. ';
        }
        $message .= 'Consultez .env.example pour un exemple.';
        error_log("❌ ERREUR CRITIQUE: " . $message);
        return [
            'success' => false,
            'message' => $message
        ];
    }
    
    error_log("✅ Using Resend API for email to: $to_email");
    $result = send_email_via_resend($to_email, $to_name, $subject, $html_body, $text_body);
    
    if ($result['success']) {
        return ['success' => true, 'message' => $result['message']];
    } else {
        error_log("❌ Resend API Error: " . $result['message']);
        return [
            'success' => false,
            'message' => 'Erreur Resend API: ' . $result['message']
        ];
    }
}

