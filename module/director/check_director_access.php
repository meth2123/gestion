<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once MYSQLCON_PATH;
require_once DB_CONFIG_PATH;

// Vérifier si l'utilisateur est connecté et est un directeur
if (!isset($_SESSION['userid']) || !isset($_SESSION['usertype'])) {
    header("Location: ../../login.php");
    exit();
}

// Vérifier si c'est un directeur
if ($_SESSION['usertype'] !== 'director') {
    header("Location: ../../index.php?error=unauthorized");
    exit();
}

// Vérifier si l'utilisateur est dans la table director
$check_director = $link->prepare("SELECT * FROM director WHERE userid = ?");
$check_director->bind_param("s", $_SESSION['userid']);
$check_director->execute();
$result = $check_director->get_result();

if ($result->num_rows === 0) {
    header("Location: ../../index.php?error=unauthorized");
    exit();
}
?>
