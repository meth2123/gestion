<?php
/**
 * Configuration SMTP centralisée
 * Utilisez ce fichier pour obtenir la configuration SMTP dans tout le projet
 * Supporte les variables d'environnement pour Render.com et autres plateformes
 */

// Fonction helper pour obtenir les variables d'environnement (compatible avec getenv et $_ENV)
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        return $value !== false ? $value : $default;
    }
}

// Configuration SMTP : utiliser les variables d'environnement si disponibles, sinon valeurs par défaut
$smtp_config = [
    'host' => getEnvVar('SMTP_HOST', 'smtp.gmail.com'),
    'port' => (int)getEnvVar('SMTP_PORT', 587),
    'username' => getEnvVar('SMTP_USERNAME', 'methndiaye43@gmail.com'),
    'password' => getEnvVar('SMTP_PASSWORD', 'ccsf pihw falx zbnu'), // Mot de passe d'application Gmail
    'from_email' => getEnvVar('SMTP_FROM_EMAIL', 'methndiaye43@gmail.com'),
    'from_name' => getEnvVar('SMTP_FROM_NAME', 'SchoolManager'),
    'encryption' => getEnvVar('SMTP_ENCRYPTION', 'tls') // STARTTLS par défaut
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

