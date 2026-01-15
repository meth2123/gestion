<?php
class BrevoService {
    private $api_key;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->api_key = getenv('BREVO_API_KEY');
        $this->from_email = getenv('BREVO_EMAIL') ?: 'methndiaye43@gmail.com';
        $this->from_name = getenv('BREVO_NAME') ?: 'SchoolManager';
        
        if (empty($this->api_key)) {
            throw new Exception("Clé API Brevo manquante. Veuillez configurer BREVO_API_KEY.");
        }
    }
    
    public function sendEmail($to_email, $to_name, $subject, $html_content, $text_content = null) {
        try {
            // CORRECTION : Si le nom est vide, utiliser l'email comme nom
            if (empty($to_name) || trim($to_name) === '') {
                $to_name = $to_email;
            }
            
            $url = 'https://api.brevo.com/v3/smtp/email';
            
            $data = [
                'sender' => [
                    'name' => $this->from_name,
                    'email' => $this->from_email
                ],
                'to' => [
                    [
                        'name' => $to_name,
                        'email' => $to_email
                    ]
                ],
                'subject' => $subject,
                'htmlContent' => $html_content
            ];
            
            // Ajouter le contenu texte si fourni
            if ($text_content) {
                $data['textContent'] = $text_content;
            }
            
            // Log pour debug
            error_log("📧 Envoi email Brevo à: $to_email (nom: $to_name)");
            error_log("📧 Données JSON: " . json_encode($data));
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'api-key: ' . $this->api_key
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("Erreur cURL Brevo: " . $curl_error);
            }
            
            $result = json_decode($response, true);
            
            if ($http_code >= 200 && $http_code < 300) {
                error_log("✅ Email Brevo envoyé avec succès à $to_email (Message ID: " . ($result['messageId'] ?? 'N/A') . ")");
                return [
                    'success' => true,
                    'message' => 'Email envoyé avec succès',
                    'message_id' => $result['messageId'] ?? null
                ];
            } else {
                $error_message = $result['message'] ?? 'Erreur inconnue';
                error_log("❌ Erreur API Brevo ($http_code): $error_message");
                error_log("❌ Réponse complète: " . $response);
                throw new Exception("Erreur API Brevo ($http_code): $error_message");
            }
            
        } catch (Exception $e) {
            error_log("❌ Erreur lors de l'envoi d'email Brevo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function testConnection() {
        try {
            $url = 'https://api.brevo.com/v3/account';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'api-key: ' . $this->api_key
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("Erreur de connexion: " . $curl_error);
            }
            
            if ($http_code === 200) {
                $account = json_decode($response, true);
                return [
                    'success' => true,
                    'message' => 'Connexion Brevo réussie',
                    'account_info' => [
                        'email' => $account['email'] ?? 'N/A',
                        'credits' => $account['plan']['credits'] ?? 'N/A'
                    ]
                ];
            } else {
                throw new Exception("Erreur d'authentification (HTTP $http_code)");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>