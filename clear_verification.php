<?php
session_start();
require_once __DIR__ . '/components/SecureSubscriptionChecker.php';

// Nettoyer la vérification
$checker = new SecureSubscriptionChecker(null);
$checker->clearVerification();

// Rediriger vers la page de vérification
header("Location: secure_subscription_check.php");
exit;
?>

