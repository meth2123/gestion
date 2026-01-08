<?php
include_once('main.php');
include_once('../../service/mysqlcon.php');
include_once('../../service/db_utils.php');

// Vérifier si l'utilisateur est connecté et a des privilèges d'administrateur
if (!isset($_SESSION['login_id'])) {
    header("Location: ../../index.php");
    exit();
}

$admin_id = $_SESSION['login_id'];

// Vérifier si l'ID de l'examen est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Rediriger vers la page de visualisation des examens avec un message d'erreur
    header("Location: viewExamSchedule.php?error=id_missing");
    exit();
}

$exam_id = $_GET['id'];

// Vérifier si l'examen appartient à cet administrateur
$check_query = "SELECT id FROM examschedule WHERE id = ? AND created_by = ?";
$exam = db_fetch_row($check_query, [$exam_id, $admin_id], 'ss');

if (!$exam) {
    // L'examen n'existe pas ou n'appartient pas à cet administrateur
    header("Location: viewExamSchedule.php?error=unauthorized");
    exit();
}

// Supprimer l'examen
$delete_query = "DELETE FROM examschedule WHERE id = ? AND created_by = ?";
$result = db_execute($delete_query, [$exam_id, $admin_id], 'ss');

if ($result) {
    // Rediriger avec un message de succès
    header("Location: viewExamSchedule.php?success=deleted");
} else {
    // Rediriger avec un message d'erreur
    header("Location: viewExamSchedule.php?error=delete_failed");
}
exit();
?>
