<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../service/mysqlcon.php';
require_once __DIR__ . '/../service/OneSignalService.php';

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$user_id = $data['user_id'] ?? '';
$user_type = $data['user_type'] ?? '';
$title = $data['title'] ?? 'Notification Test';
$message = $data['message'] ?? 'Ceci est une notification de test';

if (empty($user_id) || empty($user_type)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    // Récupérer le player ID de l'utilisateur
    $table = '';
    switch ($user_type) {
        case 'student':
            $table = 'students';
            break;
        case 'teacher':
            $table = 'teachers';
            break;
        case 'parent':
            $table = 'parents';
            break;
        case 'admin':
            $table = 'admin';
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Type d\'utilisateur invalide']);
            exit;
    }

    $query = "SELECT onesignal_player_id FROM {$table} WHERE id = ?";
    $stmt = $link->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit;
    }

    $user = $result->fetch_assoc();
    $player_id = $user['onesignal_player_id'];

    if (empty($player_id)) {
        echo json_encode(['success' => false, 'message' => 'Aucun player ID enregistré pour cet utilisateur']);
        exit;
    }

    // Envoyer la notification
    $oneSignal = new OneSignalService();
    
    $notificationData = [
        'include_player_ids' => [$player_id],
        'headings' => ['en' => $title, 'fr' => $title],
        'contents' => ['en' => $message, 'fr' => $message],
        'data' => [
            'type' => 'test_notification',
            'user_id' => $user_id,
            'user_type' => $user_type
        ]
    ];

    $result = $oneSignal->sendNotification($notificationData);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
