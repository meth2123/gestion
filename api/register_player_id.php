<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../service/mysqlcon.php';
require_once __DIR__ . '/../service/PushNotificationService.php';

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$user_id = $data['user_id'] ?? '';
$user_type = $data['user_type'] ?? '';
$player_id = $data['player_id'] ?? '';

if (empty($user_id) || empty($user_type) || empty($player_id)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    $pushService = new PushNotificationService($link);
    $pushService->setupDatabase();
    
    $result = $pushService->updatePlayerId($user_id, $user_type, $player_id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Player ID enregistré avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
