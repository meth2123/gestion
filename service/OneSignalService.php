<?php

/**
 * Service OneSignal pour les notifications push
 * Gère l'envoi de notifications via l'API OneSignal
 */
class OneSignalService
{
    private $appId;
    private $apiKey;
    private $apiUrl = 'https://onesignal.com/api/v1/notifications';
    
    public function __construct()
    {
        // Récupérer les clés depuis les variables d'environnement ou config
        $this->appId = getenv('ONESIGNAL_APP_ID') ?: 'YOUR_APP_ID';
        $this->apiKey = getenv('ONESIGNAL_API_KEY') ?: 'YOUR_API_KEY';
    }
    
    /**
     * Envoyer une notification push
     * 
     * @param array $config Configuration de la notification
     * @return array Résultat de l'envoi
     */
    public function sendNotification($config)
    {
        $defaultConfig = [
            'app_id' => $this->appId,
            'headings' => ['en' => 'Notification'],
            'contents' => ['en' => 'Vous avez une nouvelle notification'],
            'included_segments' => ['All']
        ];
        
        $payload = array_merge($defaultConfig, $config);
        
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode === 200,
            'response' => json_decode($response, true),
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Envoyer une notification à des utilisateurs spécifiques
     * 
     * @param array $userIds Liste des IDs utilisateur OneSignal
     * @param string $title Titre de la notification
     * @param string $message Contenu de la notification
     * @param array $data Données supplémentaires
     * @return array
     */
    public function sendToUsers($userIds, $title, $message, $data = [])
    {
        $config = [
            'include_player_ids' => $userIds,
            'headings' => ['en' => $title, 'fr' => $title],
            'contents' => ['en' => $message, 'fr' => $message],
            'data' => $data
        ];
        
        return $this->sendNotification($config);
    }
    
    /**
     * Envoyer une notification à un segment
     * 
     * @param array $segments Segments cibles
     * @param string $title Titre de la notification
     * @param string $message Contenu de la notification
     * @param array $data Données supplémentaires
     * @return array
     */
    public function sendToSegments($segments, $title, $message, $data = [])
    {
        $config = [
            'included_segments' => $segments,
            'headings' => ['en' => $title, 'fr' => $title],
            'contents' => ['en' => $message, 'fr' => $message],
            'data' => $data
        ];
        
        return $this->sendNotification($config);
    }
    
    /**
     * Envoyer une notification par tags
     * 
     * @param array $tags Tags pour filtrer les utilisateurs
     * @param string $title Titre de la notification
     * @param string $message Contenu de la notification
     * @param array $data Données supplémentaires
     * @return array
     */
    public function sendByTags($tags, $title, $message, $data = [])
    {
        $operator = ['OR'];
        $fields = [];
        
        foreach ($tags as $key => $value) {
            $fields[] = [
                'field' => 'tag',
                'key' => $key,
                'relation' => '=',
                'value' => $value
            ];
        }
        
        $config = [
            'filters' => $fields,
            'headings' => ['en' => $title, 'fr' => $title],
            'contents' => ['en' => $message, 'fr' => $message],
            'data' => $data
        ];
        
        return $this->sendNotification($config);
    }
    
    /**
     * Créer des segments personnalisés pour les rôles
     * 
     * @param string $role Rôle (admin, teacher, student, parent)
     * @param string $adminId ID de l'admin créateur
     * @return array
     */
    public function createRoleSegment($role, $adminId = null)
    {
        $tags = ['role' => $role];
        
        if ($adminId) {
            $tags['created_by'] = $adminId;
        }
        
        return $this->sendByTags($tags, '', '');
    }
}
