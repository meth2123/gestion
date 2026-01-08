<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once('../../service/mysqlcon.php');
include_once('../../service/db_utils.php');

if (!isset($_SESSION['teacher_id']) || empty($_SESSION['teacher_id'])) {
    echo '<pre>Session teacher_id absente ou vide : ';
    var_dump($_SESSION);
    echo '</pre>';
    exit('Erreur de session : teacher_id non défini.');
}
$check = $_SESSION['teacher_id'];
$row = db_fetch_row("SELECT name FROM teachers WHERE id = ?", [$check], 's');
$login_session = $loged_user_name = $row['name'] ?? null;

if(!isset($login_session)) {
    echo '<pre>Impossible de trouver le nom de l\'enseignant pour l\'id : ' . htmlspecialchars($check) . '</pre>';
    exit('Erreur : enseignant non trouvé dans la base.');
}
?>
