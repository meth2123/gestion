<?php
session_start();
require_once '../../service/mysqlcon.php';
require_once '../../db/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['usertype'])) {
    header("Location: ../../login.php");
    exit();
}

// Vérifier si c'est un admin ou un directeur
if ($_SESSION['usertype'] !== 'admin' && $_SESSION['usertype'] !== 'director') {
    header("Location: ../../index.php?error=unauthorized");
    exit();
}

// Vérifier si c'est une page de paiement ou de salaire
$payment_pages = ['payment.php', 'salary.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (in_array($current_page, $payment_pages)) {
    // Vérifier si c'est un admin essayant d'accéder à ces pages
    if ($_SESSION['usertype'] === 'admin') {
        header("Location: ../../index.php?error=unauthorized");
        exit();
    }
}
?>
