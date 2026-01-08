<?php
include_once('main.php');
include_once('../../service/db_utils.php');

// Vérification de la session
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['login_id'];
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'teacher';

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

// Vérifier si l'utilisateur a accès au document
$has_access = false;

if ($user_type === 'teacher') {
    // Les enseignants ont accès à leurs propres documents et aux documents des classes qu'ils enseignent
    $access_check = db_fetch_row(
        "SELECT 1 
         FROM course 
         WHERE teacherid = ? AND classid = ? 
         LIMIT 1",
        [$user_id, $document['class_id']],
        'ss'
    );
    
    $has_access = ($access_check || $document['teacher_id'] === $user_id);
} elseif ($user_type === 'student') {
    // Les élèves ont accès aux documents de leur classe
    $access_check = db_fetch_row(
        "SELECT 1 
         FROM students 
         WHERE id = ? AND classid = ? 
         LIMIT 1",
        [$user_id, $document['class_id']],
        'ss'
    );
    
    $has_access = $access_check;
} elseif ($user_type === 'admin') {
    // Les administrateurs ont accès à tous les documents
    $has_access = true;
}

if (!$has_access) {
    // Bloc de debug temporaire
    echo '<h2>DEBUG ACCES TELECHARGEMENT</h2>';
    echo '<pre>';
    echo 'user_type: ' . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'NON DEFINI') . "\n";
    echo 'user_id (login_id): ' . (isset($_SESSION['login_id']) ? $_SESSION['login_id'] : 'NON DEFINI') . "\n";
    echo 'Paramètres SQL : id=' . htmlspecialchars($user_id) . ', classid=' . htmlspecialchars($document['class_id']) . "\n";
    echo 'Resultat requête accès étudiant : ' . var_export($access_check, true) . "\n";
    echo 'document_id: ' . htmlspecialchars($document_id) . "\n";
    echo 'document[teacher_id]: ' . htmlspecialchars($document['teacher_id']) . "\n";
    echo 'document[class_id]: ' . htmlspecialchars($document['class_id']) . "\n";
    echo '</pre>';
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
db_execute(
    "UPDATE documents SET download_count = download_count + 1 WHERE id = ?",
    [$document_id],
    'i'
);

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
?>
