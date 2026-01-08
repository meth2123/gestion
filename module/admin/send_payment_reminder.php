<?php
include_once('main.php');
include_once('includes/auth_check.php');
require_once('../../db/config.php');
include_once('../../service/db_utils.php');
require_once('../../service/SmsService.php');

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données
$student_id = isset($_POST['student_id']) ? $_POST['student_id'] : '';
$phone = isset($_POST['phone']) ? $_POST['phone'] : '';
$student_name = isset($_POST['student_name']) ? $_POST['student_name'] : '';
$month = isset($_POST['month']) ? $_POST['month'] : '';
$year = isset($_POST['year']) ? $_POST['year'] : '';
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : null;

// Vérifier que les données nécessaires sont présentes
if (empty($student_id) || empty($phone) || empty($student_name) || empty($month) || empty($year)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

// Récupérer les informations du parent
$parent_info = db_fetch_row(
    "SELECT fathername, mothername FROM parents WHERE id = (SELECT parentid FROM students WHERE id = ?)",
    [$student_id],
    's'
);

$parent_name = $parent_info ? $parent_info['fathername'] . ' ' . $parent_info['mothername'] : 'Parent';

// Initialiser le service WhatsApp
$smsService = new SmsService();

// Envoyer le message WhatsApp
$result = $smsService->sendPaymentReminder($phone, $parent_name, $student_name, $month, $year, $amount);

// Enregistrer l'envoi dans l'historique
if ($result['success']) {
    $status = 'success';
    $message = isset($result['development_mode']) && $result['development_mode'] 
        ? 'Message WhatsApp simulé en mode développement' 
        : 'Message WhatsApp envoyé avec succès';
} else {
    $status = 'error';
    $message = 'Échec de l\'envoi du message WhatsApp: ' . $result['message'];
}

// Enregistrer dans l'historique
$smsService->logSms($phone, $result['content'] ?? 'Message de rappel de paiement', $status, $student_id, $admin_id);

// Retourner la réponse
echo json_encode([
    'success' => $result['success'],
    'message' => $message,
    'details' => $result
]);
?>
