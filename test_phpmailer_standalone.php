<?php
// Script de test PHPMailer totalement indépendant
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclure l'autoloader PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

// Charger la configuration SMTP depuis forgot_password.php
require_once __DIR__ . '/service/forgot_password.php';

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $smtp_config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_config['username'];
    $mail->Password = $smtp_config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_config['port'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
    $mail->addAddress('TON_EMAIL_ICI', 'Test PHPMailer'); // Remplace par ton adresse
    $mail->isHTML(true);
    $mail->Subject = 'Test PHPMailer Gestion Scolaire';
    $mail->Body    = 'Ceci est un test d\'envoi avec PHPMailer depuis Gestion Scolaire.';
    $mail->AltBody = 'Ceci est un test d\'envoi avec PHPMailer depuis Gestion Scolaire.';

    if ($mail->send()) {
        echo 'Mail envoyé avec succès !';
    } else {
        echo 'Erreur PHPMailer : ' . $mail->ErrorInfo;
    }
} catch (Exception $e) {
    echo 'Exception PHPMailer : ' . $mail->ErrorInfo . ' | Exception : ' . $e->getMessage();
}
