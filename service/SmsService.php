<?php
/**
 * Service d'envoi de messages WhatsApp
 * Ce service permet d'envoyer des messages WhatsApp aux parents pour les rappels de paiement
 */
class SmsService {
    private $api_key;
    private $phone_number_id;
    private $base_url;
    private $school_name;
    private $version;
    
    /**
     * Constructeur du service WhatsApp
     * 
     * @param string $api_key Clé API du service WhatsApp Business (à configurer)
     * @param string $phone_number_id ID du numéro de téléphone WhatsApp Business (à configurer)
     * @param string $school_name Nom de l'école pour les messages
     */
    public function __construct($api_key = null, $phone_number_id = null, $school_name = "SchoolManager") {
        $this->api_key = $api_key ?: getenv('WHATSAPP_API_KEY');
        $this->phone_number_id = $phone_number_id ?: getenv('WHATSAPP_PHONE_NUMBER_ID');
        $this->base_url = "https://graph.facebook.com/v17.0"; // URL de l'API WhatsApp Business
        $this->version = "v17.0"; // Version de l'API
        $this->school_name = $school_name;
    }
    
    /**
     * Envoie un message WhatsApp de rappel de paiement à un parent
     * 
     * @param string $phone Numéro de téléphone du parent (format international, ex: 22507123456)
     * @param string $parent_name Nom du parent
     * @param string $student_name Nom de l'élève
     * @param string $month Mois du paiement en retard
     * @param string $year Année du paiement en retard
     * @param float $amount Montant dû (optionnel)
     * @return array Résultat de l'envoi du message WhatsApp
     */
    public function sendPaymentReminder($phone, $parent_name, $student_name, $month, $year, $amount = null) {
        // Formater le numéro de téléphone (supprimer les espaces, tirets, etc.)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // S'assurer que le numéro commence par le code du pays
        if (substr($phone, 0, 3) !== '221') { // Code pays pour le Sénégal
            $phone = '221' . $phone;
        }
        
        // Construire le message
        $message = "Cher(e) parent de {$student_name}, ";
        $message .= "Nous vous rappelons que le paiement des frais de scolarité pour {$month} {$year} n'a pas été effectué. ";
        
        if ($amount) {
            $message .= "Le montant dû est de " . number_format($amount, 2) . " FCFA. ";
        }
        
        $message .= "Veuillez régulariser la situation dès que possible. ";
        $message .= "Cordialement, {$this->school_name}.";
        
        // En mode développement/test, simuler l'envoi et retourner un succès
        if (empty($this->api_key) || empty($this->phone_number_id)) {
            return [
                'success' => true,
                'message' => 'Message WhatsApp simulé en mode développement',
                'to' => $phone,
                'content' => $message,
                'development_mode' => true
            ];
        }
        
        // En production, envoyer réellement le message via l'API WhatsApp Business
        try {
            // Construire l'URL de l'API
            $url = "{$this->base_url}/{$this->phone_number_id}/messages";
            
            // Préparer les données pour l'API WhatsApp
            $data = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message
                ]
            ];
            
            // Initialiser cURL
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json'
            ]);
            
            // Exécuter la requête
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            // Traiter la réponse
            if ($error) {
                return [
                    'success' => false,
                    'message' => 'Erreur cURL: ' . $error,
                    'to' => $phone
                ];
            }
            
            $result = json_decode($response, true);
            
            // Vérifier si l'envoi a réussi
            $success = isset($result['messages']) && !empty($result['messages']) && isset($result['messages'][0]['id']);
            
            return [
                'success' => $success,
                'message' => $success ? 'Message WhatsApp envoyé avec succès' : 'Erreur lors de l\'envoi du message WhatsApp',
                'to' => $phone,
                'content' => $message,
                'api_response' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'to' => $phone
            ];
        }
    }
    
    /**
     * Enregistre l'historique d'envoi de messages WhatsApp dans la base de données
     * 
     * @param string $phone Numéro de téléphone
     * @param string $message Message envoyé
     * @param string $status Statut de l'envoi
     * @param string $student_id ID de l'élève concerné
     * @param string $admin_id ID de l'administrateur qui a envoyé le message
     * @return bool Succès de l'enregistrement
     */
    public function logSms($phone, $message, $status, $student_id, $admin_id) {
        global $conn;
        
        // Vérifier si la table whatsapp_log existe, sinon la créer
        $check_table = "SHOW TABLES LIKE 'whatsapp_log'";
        $result = $conn->query($check_table);
        
        if ($result->num_rows == 0) {
            // Créer la table si elle n'existe pas
            $create_table = "CREATE TABLE whatsapp_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(50) NOT NULL,
                student_id VARCHAR(20) NOT NULL,
                sent_by VARCHAR(20) NOT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            if (!$conn->query($create_table)) {
                return false;
            }
        }
        
        // Insérer l'enregistrement
        $stmt = $conn->prepare("INSERT INTO whatsapp_log (phone, message, status, student_id, sent_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $phone, $message, $status, $student_id, $admin_id);
        
        return $stmt->execute();
    }
}
?>
