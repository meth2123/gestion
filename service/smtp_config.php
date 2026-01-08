<?php
/**
 * Configuration SMTP centralisée
 * Utilisez ce fichier pour obtenir la configuration SMTP dans tout le projet
 */

// Configuration SMTP pour Gmail
$smtp_config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'methndiaye43@gmail.com',
    'password' => 'ccsf pihw falx zbnu', // Mot de passe d'application Gmail
    'from_email' => 'methndiaye43@gmail.com',
    'from_name' => 'SchoolManager',
    'encryption' => 'tls' // STARTTLS
];

// Fonction pour obtenir la configuration SMTP
function get_smtp_config() {
    global $smtp_config;
    return $smtp_config;
}

// Fonction pour nettoyer le mot de passe (enlever les espaces)
function get_clean_smtp_password() {
    global $smtp_config;
    return str_replace(' ', '', $smtp_config['password']);
}

