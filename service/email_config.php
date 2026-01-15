<?php
/**
<<<<<<< C:\wamp64\www\gestion\service\email_config.php
 * Configuration d'email centralisée - Resend uniquement
 * Utilisez ce fichier pour envoyer des emails dans tout le projet
 * Supporte les variables d'environnement pour Render.com et autres plateformes
 * 
 * IMPORTANT : Resend est maintenant le seul service d'email supporté
 * SMTP a été complètement supprimé
=======
 * Configuration d'email centralisée - Brevo (Sendinblue)
 * Utilisez ce fichier pour envoyer des emails dans tout le projet
 * Supporte les variables d'environnement pour Render.com et autres plateformes
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-df691a30\service\email_config.php
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

/**
<<<<<<< C:\wamp64\www\gestion\service\email_config.php
 * Envoie un email en utilisant UNIQUEMENT Resend
=======
 * Vérifie si Brevo est configuré
 */
function is_brevo_configured() {
    $api_key = getEnvVar('BREVO_API_KEY');
    return !empty($api_key);
}

/**
 * Envoie un email en utilisant Brevo (Sendinblue)
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-df691a30\service\email_config.php
 * 
 * @param string $to_email Email du destinataire
 * @param string $to_name Nom du destinataire
 * @param string $subject Sujet de l'email
 * @param string $html_body Corps HTML de l'email
 * @param string $text_body Corps texte de l'email (optionnel)
 * @return array ['success' => bool, 'message' => string]
 */
function send_email_unified($to_email, $to_name, $subject, $html_body, $text_body = null) {
<<<<<<< C:\wamp64\www\gestion\service\email_config.php
    // Charger le service Resend
    require_once(__DIR__ . '/resend_service.php');
    
    // Vérifier si Resend est configuré (OBLIGATOIRE)
    if (!is_resend_configured()) {
=======
    // Charger le service Brevo
    require_once(__DIR__ . '/brevo_service.php');
    
    // Vérifier si Brevo est configuré (OBLIGATOIRE)
    if (!is_brevo_configured()) {
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-df691a30\service\email_config.php
        $env_file = __DIR__ . '/../.env';
        $env_exists = file_exists($env_file);
        $is_render = getenv('RENDER') === 'true' || getenv('IS_RENDER') === 'true';
        
<<<<<<< C:\wamp64\www\gestion\service\email_config.php
        $message = 'RESEND_API_KEY non configurée. ';
        if ($is_render) {
            $message .= 'Sur Render.com, vérifiez que RESEND_API_KEY est bien définie dans les variables d\'environnement du dashboard. ';
        } elseif (!$env_exists) {
            $message .= 'Créez un fichier .env à la racine du projet avec RESEND_API_KEY=votre_clé_api. ';
        } else {
            $message .= 'Vérifiez que RESEND_API_KEY est bien définie dans le fichier .env. ';
        }
        $message .= 'Consultez ENV_SETUP.md pour plus d\'informations.';
=======
        $message = 'BREVO_API_KEY non configurée. ';
        if ($is_render) {
            $message .= 'Sur Render.com, vérifiez que BREVO_API_KEY est bien définie dans les variables d\'environnement du dashboard. ';
        } elseif (!$env_exists) {
            $message .= 'Créez un fichier .env à la racine du projet avec BREVO_API_KEY=votre_clé_api. ';
        } else {
            $message .= 'Vérifiez que BREVO_API_KEY est bien définie dans le fichier .env. ';
        }
        $message .= 'Récupérez votre clé API depuis https://app.brevo.com/settings/keys/api';
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-df691a30\service\email_config.php
        error_log("❌ ERREUR CRITIQUE: " . $message);
        return [
            'success' => false,
            'message' => $message
        ];
    }
    
<<<<<<< C:\wamp64\www\gestion\service\email_config.php
    error_log("✅ Using Resend API for email to: $to_email");
    $result = send_email_via_resend($to_email, $to_name, $subject, $html_body, $text_body);
    
    if ($result['success']) {
        return ['success' => true, 'message' => $result['message']];
    } else {
        error_log("❌ Resend API Error: " . $result['message']);
        return [
            'success' => false,
            'message' => 'Erreur Resend API: ' . $result['message']
=======
    try {
        $brevo = new BrevoService();
        error_log("✅ Using Brevo API for email to: $to_email");
        $result = $brevo->sendEmail($to_email, $to_name, $subject, $html_body, $text_body);
        
        if ($result['success']) {
            return ['success' => true, 'message' => $result['message']];
        } else {
            error_log("❌ Brevo API Error: " . $result['message']);
            return [
                'success' => false,
                'message' => 'Erreur Brevo API: ' . $result['message']
            ];
        }
        
    } catch (Exception $e) {
        error_log("❌ Brevo Service Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur Brevo Service: ' . $e->getMessage()
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-df691a30\service\email_config.php
        ];
    }
}

