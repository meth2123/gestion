<?php
/**
 * Service Resend pour l'envoi d'emails via API
 * Alternative moderne et fiable à SMTP pour les environnements cloud comme Render.com
 * 
 * Documentation: https://resend.com/docs
 */

// Fonction helper pour obtenir les variables d'environnement
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
 * Envoie un email via l'API Resend
 * 
 * @param string $to_email Email du destinataire
 * @param string $to_name Nom du destinataire (optionnel)
 * @param string $subject Sujet de l'email
 * @param string $html_body Corps HTML de l'email
 * @param string $text_body Corps texte de l'email (optionnel, généré depuis HTML si non fourni)
 * @param string $from_email Email expéditeur (optionnel, utilise la config par défaut)
 * @param string $from_name Nom expéditeur (optionnel, utilise la config par défaut)
 * @return array ['success' => bool, 'message' => string, 'id' => string|null]
 */
function send_email_via_resend($to_email, $to_name, $subject, $html_body, $text_body = null, $from_email = null, $from_name = null) {
    // Récupérer la clé API Resend avec plusieurs méthodes pour compatibilité Render.com
    $api_key = getEnvVar('RESEND_API_KEY');
    
    // Vérifications supplémentaires pour Render.com
    if (empty($api_key)) {
        // Essayer directement getenv() et $_ENV pour déboguer
        $api_key_env = getenv('RESEND_API_KEY');
        $api_key_super = isset($_ENV['RESEND_API_KEY']) ? $_ENV['RESEND_API_KEY'] : null;
        
        error_log("🔍 DEBUG Resend: getenv() = " . ($api_key_env !== false ? "trouvé (" . strlen($api_key_env) . " chars)" : "non trouvé"));
        error_log("🔍 DEBUG Resend: \$_ENV = " . ($api_key_super !== null ? "trouvé (" . strlen($api_key_super) . " chars)" : "non trouvé"));
        
        // Utiliser la valeur trouvée si disponible
        if ($api_key_env !== false && !empty(trim($api_key_env))) {
            $api_key = trim($api_key_env);
            error_log("✅ Utilisation de getenv('RESEND_API_KEY')");
        } elseif ($api_key_super !== null && !empty(trim($api_key_super))) {
            $api_key = trim($api_key_super);
            error_log("✅ Utilisation de \$_ENV['RESEND_API_KEY']");
        }
    } else {
        $api_key = trim($api_key);
        error_log("✅ RESEND_API_KEY trouvée via getEnvVar() (" . strlen($api_key) . " caractères)");
    }
    
    if (empty($api_key)) {
        $env_file = __DIR__ . '/../.env';
        $env_exists = file_exists($env_file);
        $is_render = getenv('RENDER') === 'true' || getenv('IS_RENDER') === 'true';
        
        $message = 'RESEND_API_KEY non configurée. ';
        if ($is_render) {
            $message .= 'Sur Render.com, vérifiez que RESEND_API_KEY est bien définie dans les variables d\'environnement du dashboard. ';
        } elseif (!$env_exists) {
            $message .= 'Créez un fichier .env à la racine du projet avec RESEND_API_KEY=votre_clé_api. ';
        } else {
            $message .= 'Vérifiez que RESEND_API_KEY est bien définie dans le fichier .env. ';
        }
        $message .= 'Consultez ENV_SETUP.md pour plus d\'informations.';
        error_log("❌ " . $message);
        return [
            'success' => false,
            'message' => $message,
            'id' => null
        ];
    }
    
    // Configuration par défaut
    if (empty($from_email)) {
        $from_email = getEnvVar('RESEND_FROM_EMAIL', 'noreply@resend.dev');
    }
    if (empty($from_name)) {
        $from_name = getEnvVar('RESEND_FROM_NAME', 'SchoolManager');
    }
    
    // Nettoyer les valeurs
    $from_email = trim($from_email);
    $from_name = trim($from_name);
    
    // Générer le texte depuis HTML si non fourni
    if (empty($text_body)) {
        $text_body = strip_tags($html_body);
    }
    
    // Préparer les données pour l'API Resend
    // Format "from" : Resend accepte soit "email@domain.com" soit "Name <email@domain.com>"
    $from_string = $from_email;
    if (!empty($from_name)) {
        $from_string = $from_name . ' <' . $from_email . '>';
    }
    
    $data = [
        'from' => $from_string,
        'to' => [$to_email],
        'subject' => $subject,
        'html' => $html_body,
        'text' => $text_body
    ];
    
    // Ajouter le nom du destinataire si fourni
    if (!empty($to_name)) {
        $data['to'] = [$to_name . ' <' . $to_email . '>'];
    }
    
    // Log pour déboguer (sans exposer la clé API complète)
    error_log("📧 Envoi Resend: from=$from_string, to=$to_email, subject=" . substr($subject, 0, 50));
    
    // Envoyer la requête à l'API Resend
    $ch = curl_init('https://api.resend.com/emails');
    $json_data = json_encode($data);
    
    // Vérifier que le JSON est valide
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ Erreur JSON: " . json_last_error_msg());
        return [
            'success' => false,
            'message' => 'Erreur lors de la préparation des données: ' . json_last_error_msg(),
            'id' => null
        ];
    }
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    // Gérer les erreurs cURL
    if ($curl_error || $curl_errno) {
        error_log("❌ Resend API Error (cURL $curl_errno): " . $curl_error);
        error_log("📋 Response: " . substr($response, 0, 500));
        return [
            'success' => false,
            'message' => 'Erreur de connexion à l\'API Resend: ' . $curl_error . ' (Code: ' . $curl_errno . ')',
            'id' => null
        ];
    }
    
    // Parser la réponse
    $response_data = json_decode($response, true);
    $json_error = json_last_error();
    
    if ($json_error !== JSON_ERROR_NONE && !empty($response)) {
        error_log("⚠️ Erreur de parsing JSON de la réponse Resend: " . json_last_error_msg());
        error_log("📋 Raw response: " . substr($response, 0, 500));
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        // Succès
        $email_id = isset($response_data['id']) ? $response_data['id'] : null;
        error_log("✅ Resend Email sent successfully. ID: " . $email_id . " | HTTP: $http_code");
        return [
            'success' => true,
            'message' => 'Email envoyé avec succès via Resend',
            'id' => $email_id
        ];
    } else {
        // Erreur - améliorer les messages d'erreur
        $error_message = 'Erreur inconnue';
        if (isset($response_data['message'])) {
            $error_message = $response_data['message'];
        } elseif (isset($response_data['error'])) {
            $error_message = is_array($response_data['error']) ? json_encode($response_data['error']) : $response_data['error'];
        } elseif (!empty($response)) {
            // Si la réponse n'est pas du JSON valide, afficher le début de la réponse
            $error_message = 'Réponse API: ' . substr($response, 0, 200);
        }
        
        // Messages d'erreur spécifiques selon le code HTTP
        if ($http_code === 401 || $http_code === 403) {
            $error_message = 'Clé API Resend invalide ou expirée. Vérifiez votre RESEND_API_KEY sur Render.com.';
        } elseif ($http_code === 422) {
            $error_message = 'Données invalides: ' . $error_message;
            // Log des données envoyées pour déboguer (sans exposer la clé API)
            error_log("📋 Data sent (sans clé API): " . json_encode(array_merge($data, ['from' => '***'])));
        } elseif ($http_code === 429) {
            $error_message = 'Limite de taux dépassée. Réessayez plus tard.';
        } elseif ($http_code === 0) {
            $error_message = 'Aucune réponse du serveur Resend. Vérifiez votre connexion internet.';
        }
        
        error_log("❌ Resend API Error (HTTP $http_code): " . $error_message);
        error_log("📋 Full Response: " . substr($response, 0, 1000));
        return [
            'success' => false,
            'message' => 'Erreur Resend API: ' . $error_message,
            'id' => null
        ];
    }
}

/**
 * Vérifie si Resend est configuré et disponible
 * 
 * @return bool
 */
function is_resend_configured() {
    $api_key = getEnvVar('RESEND_API_KEY');
    
    // Vérifications supplémentaires pour Render.com
    if (empty($api_key)) {
        $api_key_env = getenv('RESEND_API_KEY');
        $api_key_super = isset($_ENV['RESEND_API_KEY']) ? $_ENV['RESEND_API_KEY'] : null;
        
        if ($api_key_env !== false && !empty(trim($api_key_env))) {
            return true;
        }
        if ($api_key_super !== null && !empty(trim($api_key_super))) {
            return true;
        }
    }
    
    return !empty(trim($api_key));
}

