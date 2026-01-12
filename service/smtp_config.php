<?php
/**
 * FICHIER DE COMPATIBILITÉ - REDIRIGE VERS email_config.php
 * 
 * Ce fichier existe uniquement pour maintenir la compatibilité avec les anciens appels.
 * Tous les appels sont redirigés vers email_config.php qui utilise Resend uniquement.
 * 
 * IMPORTANT : SMTP a été complètement supprimé. Utilisez email_config.php directement.
 */

// Rediriger vers le nouveau fichier
require_once(__DIR__ . '/email_config.php');

// Fonctions de compatibilité (dépréciées)
function get_smtp_config() {
    error_log("⚠️ get_smtp_config() est dépréciée. Utilisez send_email_unified() de email_config.php");
    return [];
}

function get_clean_smtp_password() {
    error_log("⚠️ get_clean_smtp_password() est dépréciée. SMTP a été supprimé.");
    return '';
}

function configure_smtp_for_render($mail) {
    error_log("⚠️ configure_smtp_for_render() est dépréciée. SMTP a été supprimé. Utilisez Resend uniquement.");
}
