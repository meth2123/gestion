<?php
/**
 * Service de gestion des notifications push OneSignal
 */
class PushNotificationService {
    private $appId;
    private $apiKey;
    private $apiUrl = 'https://onesignal.com/api/v1/notifications';
    
    public function __construct() {
        $this->appId = getenv('ONESIGNAL_APP_ID') ?: 'b8c9e82f-be11-439a-a5fc-fd1b39558736';
        $this->apiKey = getenv('ONESIGNAL_API_KEY') ?: '';
        
        if (empty($this->apiKey)) {
            error_log('OneSignal API Key non configuré');
        }
    }
    
    /**
     * Envoyer une notification push
     */
    public function sendNotification($title, $message, $segments = [], $data = [], $url = null) {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'API Key OneSignal non configurée'];
        }
        
        $fields = [
            'app_id' => $this->appId,
            'headings' => ['en' => $title, 'fr' => $title],
            'contents' => ['en' => $message, 'fr' => $message],
            'included_segments' => empty($segments) ? ['All'] : $segments,
        ];
        
        if (!empty($data)) {
            $fields['data'] = $data;
        }
        
        if ($url) {
            $fields['url'] = $url;
        }
        
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['id'])) {
            return [
                'success' => true,
                'notification_id' => $result['id'],
                'recipients' => $result['recipients'] ?? 0
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['errors'][0] ?? 'Erreur inconnue',
                'response' => $result
            ];
        }
    }
    
    /**
     * Envoyer une notification à des utilisateurs spécifiques
     */
    public function sendToUsers($title, $message, $userIds, $data = [], $url = null) {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'API Key OneSignal non configurée'];
        }
        
        $fields = [
            'app_id' => $this->appId,
            'headings' => ['en' => $title, 'fr' => $title],
            'contents' => ['en' => $message, 'fr' => $message],
            'include_player_ids' => $userIds,
        ];
        
        if (!empty($data)) {
            $fields['data'] = $data;
        }
        
        if ($url) {
            $fields['url'] = $url;
        }
        
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['id'])) {
            return [
                'success' => true,
                'notification_id' => $result['id'],
                'recipients' => $result['recipients'] ?? 0
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['errors'][0] ?? 'Erreur inconnue',
                'response' => $result
            ];
        }
    }
    
    /**
     * Envoyer une notification à un segment spécifique
     */
    public function sendToSegment($title, $message, $segment, $data = [], $url = null) {
        return $this->sendNotification($title, $message, [$segment], $data, $url);
    }
    
    /**
     * Créer un segment personnalisé
     */
    public function createSegment($name, $filters = []) {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'API Key OneSignal non configurée'];
        }
        
        $fields = [
            'app_id' => $this->appId,
            'name' => $name,
            'filters' => $filters
        ];
        
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/segments');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200) {
            return ['success' => true, 'segment_id' => $result['id'] ?? null];
        } else {
            return [
                'success' => false,
                'error' => $result['errors'][0] ?? 'Erreur inconnue',
                'response' => $result
            ];
        }
    }
    
    /**
     * Obtenir les statistiques des notifications
     */
    public function getNotificationStats($notificationId) {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'API Key OneSignal non configurée'];
        }
        
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications/{$notificationId}?app_id={$this->appId}");
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200) {
            return ['success' => true, 'data' => $result];
        } else {
            return [
                'success' => false,
                'error' => $result['errors'][0] ?? 'Erreur inconnue',
                'response' => $result
            ];
        }
    }
}

// Fonctions utilitaires pour les notifications courantes
function sendWelcomeNotification($userId, $userName) {
    $service = new PushNotificationService();
    return $service->sendToUsers(
        'Bienvenue sur SchoolManager!',
        "Bonjour {$userName}, nous sommes ravis de vous accueillir sur notre plateforme de gestion scolaire.",
        [$userId],
        ['type' => 'welcome', 'user_id' => $userId],
        'https://gestion-rlhq.onrender.com/'
    );
}

function sendAssignmentNotification($userIds, $assignmentInfo) {
    $service = new PushNotificationService();
    return $service->sendToUsers(
        'Nouvelle assignation',
        "Vous avez une nouvelle assignation: {$assignmentInfo['course']} avec {$assignmentInfo['teacher']}",
        $userIds,
        ['type' => 'assignment', 'assignment_id' => $assignmentInfo['id']],
        'https://gestion-rlhq.onrender.com/module/admin/assignStudents.php'
    );
}

function sendSystemNotification($title, $message, $targetSegment = 'All') {
    $service = new PushNotificationService();
    return $service->sendToSegment($title, $message, $targetSegment);
}
?>
