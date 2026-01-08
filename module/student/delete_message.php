<?php
include_once('main.php');
require_once '../../service/db_utils.php';

// Vérification de la session
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$student_id = $_SESSION['login_id'];
$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;

// Vérifier que le message existe et appartient à l'étudiant
$message = db_fetch_row(
    "SELECT * FROM chat_messages WHERE id = ? AND sender_id = ? AND sender_type = 'student'",
    [$message_id, $student_id],
    'is'
);

if (!$message) {
    // Si l'étudiant n'est pas l'auteur du message, vérifier s'il s'agit d'une erreur ou d'une tentative non autorisée
    $message_exists = db_fetch_row(
        "SELECT * FROM chat_messages WHERE id = ?",
        [$message_id],
        'i'
    );
    
    if ($message_exists) {
        // Le message existe mais n'appartient pas à l'étudiant
        header("Location: chat.php?room=" . $room_id . "&error=unauthorized");
    } else {
        // Le message n'existe pas
        header("Location: chat.php?room=" . $room_id . "&error=message_not_found");
    }
    exit();
}

// Supprimer d'abord les pièces jointes associées au message
$attachments = db_fetch_all(
    "SELECT * FROM chat_attachments WHERE message_id = ?",
    [$message_id],
    'i'
);

// Supprimer les fichiers physiques
foreach ($attachments as $attachment) {
    $file_path = '../../uploads/chat/' . $attachment['file_name'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Supprimer les enregistrements des pièces jointes
db_execute(
    "DELETE FROM chat_attachments WHERE message_id = ?",
    [$message_id],
    'i'
);

// Supprimer le message
$deleted = db_execute(
    "DELETE FROM chat_messages WHERE id = ?",
    [$message_id],
    'i'
);

// Rediriger vers la page du chat
if ($deleted) {
    header("Location: chat.php?room=" . $room_id . "&success=message_deleted");
} else {
    header("Location: chat.php?room=" . $room_id . "&error=delete_failed");
}
exit();
?>
