<?php
session_start();
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once MYSQLCON_PATH;
require_once DB_CONFIG_PATH;

// Vérifier si l'utilisateur est connecté et est un directeur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['usertype'])) {
    header("Location: ../../login.php");
    exit();
}

// Vérifier si c'est un directeur
if ($_SESSION['usertype'] !== 'director') {
    header("Location: ../../index.php?error=unauthorized");
    exit();
}

// Rediriger vers la page appropriée
$page = $_GET['page'] ?? 'payment';
$valid_pages = ['payment', 'salary'];

if (in_array($page, $valid_pages)) {
    header("Location: ../admin/{$page}.php");
    exit();
} else {
    header("Location: ../../index.php?error=invalid_page");
    exit();
}
?>
