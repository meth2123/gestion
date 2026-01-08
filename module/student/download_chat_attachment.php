<?php
include_once('main.php');
require_once '../../service/db_utils.php';

// Vérification de la session
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$student_id = $_SESSION['login_id'];
$attachment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$attachment_id) {
    header("Location: chat.php?error=invalid_attachment");
    exit();
}

// Récupérer les informations sur la pièce jointe
$attachment = db_fetch_row(
    "SELECT a.*, m.room_id, m.sender_id 
     FROM chat_attachments a
     JOIN chat_messages m ON a.message_id = m.id
     WHERE a.id = ?",
    [$attachment_id],
    'i'
);

if (!$attachment) {
    header("Location: chat.php?error=attachment_not_found");
    exit();
}

// Vérifier si l'étudiant a accès à ce salon
$has_access = db_fetch_row(
    "SELECT 1 FROM chat_participants p
     JOIN chat_rooms r ON p.room_id = r.id
     WHERE p.room_id = ? AND p.user_id = ? AND p.user_type = 'student'
     UNION
     SELECT 1 FROM students s
     JOIN chat_rooms r ON s.classid = r.class_id
     WHERE r.id = ? AND s.id = ? AND r.is_class_room = 1",
    [$attachment['room_id'], $student_id, $attachment['room_id'], $student_id],
    'isis'
);

if (!$has_access) {
    header("Location: chat.php?error=access_denied");
    exit();
}

// Chemin du fichier
$file_path = '../../uploads/chat/' . $attachment['file_name'];

// Vérifier si le fichier existe
if (!file_exists($file_path)) {
    header("Location: chat.php?error=file_not_found");
    exit();
}

// Définir les en-têtes pour le téléchargement
header('Content-Description: File Transfer');
header('Content-Type: ' . $attachment['file_type']);
header('Content-Disposition: attachment; filename="' . $attachment['original_file_name'] . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Lire et envoyer le fichier
readfile($file_path);
exit();
?>
