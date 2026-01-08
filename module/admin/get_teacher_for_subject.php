<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');

// Vérifier si l'utilisateur est connecté
$check = $_SESSION['login_id'] ?? null;
if(!isset($check)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

$admin_id = $_SESSION['login_id'];
$subject_id = $_GET['subject_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';

// Initialiser la réponse
$response = ['teacher_id' => ''];

if (!empty($subject_id) && !empty($class_id)) {
    // Récupérer l'enseignant associé à cette matière et cette classe
    $stmt = $link->prepare("
        SELECT teacherid FROM course 
        WHERE id = ? AND classid = ?
    ");
    $stmt->bind_param("ss", $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response['teacher_id'] = $row['teacherid'];
    }
}

// Renvoyer la réponse au format JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
