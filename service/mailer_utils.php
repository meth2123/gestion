<?php
// mailer_utils.php : Utilitaire d'envoi d'email via Resend (SMTP supprimé)
// Utilisation : require ce fichier puis appeler envoyer_email_smtp(...)
// NOTE: Le nom de la fonction est conservé pour compatibilité mais utilise maintenant Resend uniquement

/**
 * Envoie un email via Resend (SMTP supprimé)
 * DÉPRÉCIÉ : Utilisez directement send_email_unified() de smtp_config.php
 * 
 * @param string $to_email  Email du destinataire
 * @param string $to_name   Nom du destinataire
 * @param string $subject   Sujet du mail
 * @param string $body      Corps HTML du mail
 * @param array  $smtp_config  Tableau de config SMTP (ignoré, conservé pour compatibilité)
 * @return bool|string  true si OK, sinon message d'erreur
 */
function envoyer_email_smtp($to_email, $to_name, $subject, $body, $smtp_config) {
    // Utiliser la fonction unifiée (Resend uniquement)
    require_once(__DIR__ . '/smtp_config.php');
    if (function_exists('send_email_unified')) {
        $result = send_email_unified($to_email, $to_name, $subject, $body);
        return $result['success'] ? true : $result['message'];
    }
    
    return 'Erreur: send_email_unified() non disponible. Veuillez configurer RESEND_API_KEY.';
}
