<?php
// mailer_utils.php : Utilitaire d'envoi d'email SMTP via PHPMailer avec config personnalisée
// Utilisation : require ce fichier puis appeler envoyer_email_smtp(...)

// Vérifier si PHPMailer est installé
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    throw new Exception('PHPMailer (composer) non installé. Faites composer install dans le dossier racine.');
}
require_once(__DIR__ . '/../vendor/autoload.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie un email SMTP via PHPMailer avec la config fournie.
 * DÉPRÉCIÉ : Utilisez send_email_unified() de smtp_config.php qui supporte Resend et SMTP
 * 
 * @param string $to_email  Email du destinataire
 * @param string $to_name   Nom du destinataire
 * @param string $subject   Sujet du mail
 * @param string $body      Corps HTML du mail
 * @param array  $smtp_config  Tableau de config SMTP
 * @return bool|string  true si OK, sinon message d'erreur
 */
function envoyer_email_smtp($to_email, $to_name, $subject, $body, $smtp_config) {
    // Essayer d'utiliser la fonction unifiée (Resend ou SMTP)
    require_once(__DIR__ . '/smtp_config.php');
    if (function_exists('send_email_unified')) {
        $result = send_email_unified($to_email, $to_name, $subject, $body);
        return $result['success'] ? true : $result['message'];
    }
    
    // Fallback vers l'ancienne méthode SMTP uniquement
    try {
        // Utiliser le mot de passe nettoyé si disponible, sinon utiliser celui de la config
        $password = isset($smtp_config['password_clean']) ? $smtp_config['password_clean'] : 
                   (function_exists('get_clean_smtp_password') ? get_clean_smtp_password() : 
                   str_replace(' ', '', $smtp_config['password']));
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $password;
        $mail->SMTPSecure = isset($smtp_config['encryption']) && $smtp_config['encryption'] === 'ssl' 
                           ? PHPMailer::ENCRYPTION_SMTPS 
                           : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];
        $mail->CharSet = 'UTF-8';
        
        // Charger la fonction de configuration SMTP pour Render.com
        require_once(__DIR__ . '/smtp_config.php');
        configure_smtp_for_render($mail);
        $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        return 'Erreur PHPMailer: ' . $e->getMessage();
    }
}
