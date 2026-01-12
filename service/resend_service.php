<?php
/**
 * Service Resend pour l'envoi d'emails via API
 * Alternative moderne et fiable à SMTP pour les environnements cloud comme Render.com
 * 
 * Documentation: https://resend.com/docs
 */

// Fonction helper pour obtenir les variables d'environnement
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
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
    // Récupérer la clé API Resend
    $api_key = getEnvVar('RESEND_API_KEY');
    
    if (empty($api_key)) {
        return [
            'success' => false,
            'message' => 'RESEND_API_KEY non configurée dans les variables d\'environnement',
            'id' => null
        ];
    }
    
    // Configuration par défaut
    if (empty($from_email)) {
        $from_email = getEnvVar('RESEND_FROM_EMAIL', getEnvVar('SMTP_FROM_EMAIL', 'noreply@resend.dev'));
    }
    if (empty($from_name)) {
        $from_name = getEnvVar('RESEND_FROM_NAME', getEnvVar('SMTP_FROM_NAME', 'SchoolManager'));
    }
    
    // Générer le texte depuis HTML si non fourni
    if (empty($text_body)) {
        $text_body = strip_tags($html_body);
    }
    
    // Préparer les données pour l'API Resend
    $data = [
        'from' => $from_name . ' <' . $from_email . '>',
        'to' => [$to_email],
        'subject' => $subject,
        'html' => $html_body,
        'text' => $text_body
    ];
    
    // Ajouter le nom du destinataire si fourni
    if (!empty($to_name)) {
        $data['to'] = [$to_name . ' <' . $to_email . '>'];
    }
    
    // Envoyer la requête à l'API Resend
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Gérer les erreurs cURL
    if ($curl_error) {
        error_log("Resend API Error (cURL): " . $curl_error);
        return [
            'success' => false,
            'message' => 'Erreur de connexion à l\'API Resend: ' . $curl_error,
            'id' => null
        ];
    }
    
    // Parser la réponse
    $response_data = json_decode($response, true);
    
    if ($http_code >= 200 && $http_code < 300) {
        // Succès
        $email_id = isset($response_data['id']) ? $response_data['id'] : null;
        error_log("Resend Email sent successfully. ID: " . $email_id);
        return [
            'success' => true,
            'message' => 'Email envoyé avec succès via Resend',
            'id' => $email_id
        ];
    } else {
        // Erreur
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Erreur inconnue';
        error_log("Resend API Error (HTTP $http_code): " . $error_message . " | Response: " . $response);
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
    return !empty($api_key);
}

