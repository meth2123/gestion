<?php
session_start();
include_once('../../service/db_utils.php');

// Vérification de la session étudiant
if (!isset($_SESSION['login_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['login_id'];

// Vérifier si l'ID du document est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: documents.php?error=invalid_document");
    exit();
}
$document_id = $_GET['id'];

// Récupérer les informations du document
$document = db_fetch_row(
    "SELECT * FROM documents WHERE id = ?",
    [$document_id],
    'i'
);

if (!$document) {
    header("Location: documents.php?error=document_not_found");
    exit();
}

// Vérifier que l'étudiant appartient bien à la classe du document
$access_check = db_fetch_row(
    "SELECT 1 FROM students WHERE id = ? AND classid = ? LIMIT 1",
    [$user_id, $document['class_id']],
    'ss'
);

if (!$access_check) {
    header("Location: documents.php?error=access_denied");
    exit();
}

// Chemin du fichier
$file_path = "../../uploads/documents/" . $document['file_name'];

// Vérifier si le fichier existe
if (!file_exists($file_path)) {
    header("Location: documents.php?error=file_not_found");
    exit();
}

// Incrémenter le compteur de téléchargements
require_once('../../service/mysqlcon.php');
$link->query("UPDATE documents SET download_count = download_count + 1 WHERE id = " . intval($document_id));

// Définir les en-têtes pour le téléchargement
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $document['original_file_name'] . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit();
