<?php
// mailer_utils.php : Utilitaire d'envoi d'email via Resend
// DÉPRÉCIÉ : Utilisez directement send_email_unified() de email_config.php

/**
 * Envoie un email via Resend
 * DÉPRÉCIÉ : Utilisez directement send_email_unified() de email_config.php
 * 
 * @param string $to_email  Email du destinataire
 * @param string $to_name   Nom du destinataire
 * @param string $subject   Sujet du mail
 * @param string $body      Corps HTML du mail
 * @param array  $smtp_config  Ignoré (conservé pour compatibilité)
 * @return bool|string  true si OK, sinon message d'erreur
 */
function envoyer_email_smtp($to_email, $to_name, $subject, $body, $smtp_config) {
    // Utiliser la fonction unifiée (Resend uniquement)
    require_once(__DIR__ . '/email_config.php');
    if (function_exists('send_email_unified')) {
        $result = send_email_unified($to_email, $to_name, $subject, $body);
        return $result['success'] ? true : $result['message'];
    }
    
    return 'Erreur: send_email_unified() non disponible. Veuillez configurer RESEND_API_KEY.';
}
