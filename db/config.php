<?php
// Database configuration
// Utiliser la même logique que service/db_config.php pour la compatibilité

// Fonction helper pour obtenir les variables d'environnement (seulement si pas déjà définie)
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        return $value !== false ? $value : $default;
    }
}

// Détecter l'environnement (Render, Railway, Docker, local)
$is_render = (
    getEnvVar('RENDER') == 'true' || 
    getEnvVar('IS_RENDER') == 'true' || 
    !empty(getEnvVar('EXTERNAL_DATABASE_HOST')) ||
    !empty(getEnvVar('MYSQLHOST')) ||
    !empty(getEnvVar('MYSQL_HOST'))
);

// Déterminer les paramètres de connexion
if ($is_render || file_exists('/.dockerenv') || getenv('DB_HOST')) {
    // Environnement de production (Render, Railway, Docker)
    // PRIORITÉ 1: Vérifier MYSQL_URL (Railway fournit souvent cette variable)
    $mysql_url = getEnvVar('MYSQL_URL') ?: getEnvVar('MYSQLURL');
    $db_host = null;
    $db_user = null;
    $db_password = null;
    $db_name = null;
    
    if (!empty($mysql_url)) {
        // Parser l'URL MySQL (format: mysql://user:password@host:port/database)
        if (preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:]+):?(\d+)?\/([^?]+)/', $mysql_url, $matches)) {
            $db_user = $matches[1];
            $db_password = $matches[2];
            $db_host = $matches[3];
            $db_name = $matches[5];
            
            // Vérifier si c'est un hôte Railway interne (ne fonctionne que sur Railway)
            if ($db_host === 'mysql.railway.internal' || strpos($db_host, '.railway.internal') !== false) {
                error_log("ATTENTION: MYSQL_URL contient mysql.railway.internal - cet hôte ne fonctionne que sur Railway");
                error_log("Tentative d'utilisation de MYSQL_PUBLIC_URL à la place...");
                
                // Essayer d'utiliser MYSQL_PUBLIC_URL qui contient l'hôte public
                $public_url = getEnvVar('MYSQL_PUBLIC_URL');
                if (!empty($public_url)) {
                    if (preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:]+):?(\d+)?\/([^?]+)/', $public_url, $public_matches)) {
                        $db_host = $public_matches[3];
                        $db_user = $public_matches[1];
                        $db_password = $public_matches[2];
                        $db_name = $public_matches[5];
                        error_log("Utilisation de l'hôte public Railway depuis MYSQL_PUBLIC_URL: $db_host");
                    }
                } else {
                    // Si MYSQL_PUBLIC_URL n'est pas disponible, utiliser les variables individuelles
                    error_log("MYSQL_PUBLIC_URL non disponible, utilisation des variables individuelles");
                    $db_host = null; // Réinitialiser pour utiliser les fallbacks ci-dessous
                }
            }
        }
    }
    
    // Si on n'a pas réussi à obtenir les infos depuis MYSQL_URL, utiliser les fallbacks
    if (empty($db_host)) {
        $db_host = getEnvVar('DB_HOST') ?: getEnvVar('MYSQLHOST') ?: getEnvVar('MYSQL_HOST') ?: getEnvVar('EXTERNAL_DATABASE_HOST') ?: 'db';
        $db_user = getEnvVar('DB_USER') ?: getEnvVar('MYSQLUSER') ?: getEnvVar('MYSQL_USER') ?: getEnvVar('EXTERNAL_DATABASE_USER') ?: 'root';
        $db_password = getEnvVar('DB_PASSWORD') ?: getEnvVar('MYSQLPASSWORD') ?: getEnvVar('MYSQL_PASSWORD') ?: getEnvVar('EXTERNAL_DATABASE_PASSWORD') ?: '';
        $db_name = getEnvVar('DB_NAME') ?: getEnvVar('MYSQLDATABASE') ?: getEnvVar('MYSQL_DATABASE') ?: getEnvVar('EXTERNAL_DATABASE_NAME') ?: 'gestion';
    }
    
    // Vérifier à nouveau si l'hôte est Railway interne (après les fallbacks)
    if ($db_host === 'mysql.railway.internal' || strpos($db_host, '.railway.internal') !== false) {
        error_log("ERREUR: L'hôte de base de données est toujours mysql.railway.internal");
        error_log("Cet hôte ne fonctionne que si l'application est déployée sur Railway");
        error_log("Si vous êtes sur Render.com, vous devez utiliser MYSQL_PUBLIC_URL ou configurer EXTERNAL_DATABASE_HOST");
        
        // Essayer une dernière fois avec MYSQL_PUBLIC_URL
        $public_url = getEnvVar('MYSQL_PUBLIC_URL');
        if (!empty($public_url) && preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:]+):?(\d+)?\/([^?]+)/', $public_url, $public_matches)) {
            $db_host = $public_matches[3];
            $db_user = $public_matches[1];
            $db_password = $public_matches[2];
            $db_name = $public_matches[5];
            error_log("Utilisation de l'hôte public Railway depuis MYSQL_PUBLIC_URL: $db_host");
        } else {
            // Si toujours Railway interne, utiliser EXTERNAL_DATABASE_HOST comme dernier recours
            $external_host = getEnvVar('EXTERNAL_DATABASE_HOST');
            if (!empty($external_host)) {
                $db_host = $external_host;
                $db_user = getEnvVar('EXTERNAL_DATABASE_USER') ?: $db_user;
                $db_password = getEnvVar('EXTERNAL_DATABASE_PASSWORD') ?: $db_password;
                $db_name = getEnvVar('EXTERNAL_DATABASE_NAME') ?: $db_name;
                error_log("Utilisation de EXTERNAL_DATABASE_HOST: $db_host");
            }
        }
    }
    
    // Si DB_HOST est 'localhost', utiliser '127.0.0.1' pour forcer TCP/IP au lieu du socket Unix
    if ($db_host === 'localhost') {
        $db_host = '127.0.0.1';
    }
} else {
    // Environnement local (WAMP)
    $db_host = 'localhost';
    $db_user = 'root';
    $db_password = '';
    $db_name = 'gestion';
}

define('DB_HOST', $db_host);
define('DB_USER', $db_user);
define('DB_PASSWORD', $db_password);
define('DB_NAME', $db_name);

// Session configuration
if (!isset($_SESSION)) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone
date_default_timezone_set('UTC');

// Character encoding
ini_set('default_charset', 'UTF-8');

// Database connection function avec gestion d'erreurs améliorée
function getDbConnection() {
    // Timeouts configurables
    $connect_timeout = (int)getEnvVar('DB_CONNECT_TIMEOUT') ?: 10;
    $read_timeout = (int)getEnvVar('DB_READ_TIMEOUT') ?: 30;
    
    // Forcer TCP/IP si localhost (éviter les sockets Unix)
    $host = DB_HOST;
    if ($host === 'localhost') {
        $host = '127.0.0.1';
    }
    
    // Vérifier si l'hôte est Railway interne (ne fonctionne que sur Railway)
    if ($host === 'mysql.railway.internal' || strpos($host, '.railway.internal') !== false) {
        $error_msg = "ERREUR: Tentative de connexion à mysql.railway.internal qui ne fonctionne que sur Railway.";
        $error_msg .= " Si vous êtes sur Render.com ou un autre hébergeur, vous devez utiliser MYSQL_PUBLIC_URL ou EXTERNAL_DATABASE_HOST.";
        error_log($error_msg);
        // Ne pas utiliser die() - retourner null pour permettre la gestion d'erreur
        return null;
    }
    
    // Créer la connexion avec gestion d'erreurs
    $conn = @new mysqli($host, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        $error_msg = "Erreur de connexion à la base de données: " . $conn->connect_error;
        $error_msg .= " (Host: $host, User: " . DB_USER . ", Database: " . DB_NAME . ")";
        
        // Message d'aide spécifique pour Railway
        if (strpos($conn->connect_error, 'getaddrinfo') !== false && strpos($host, 'railway') !== false) {
            $error_msg .= " | ASTUCE: Si vous êtes sur Render.com avec une base Railway, utilisez MYSQL_PUBLIC_URL au lieu de MYSQL_URL.";
        }
        
        error_log($error_msg);
        // Ne pas utiliser die() - retourner null pour permettre la gestion d'erreur
        return null;
    }
    
    // Configurer les timeouts
    if (method_exists($conn, 'options')) {
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, $connect_timeout);
        $conn->options(MYSQLI_OPT_READ_TIMEOUT, $read_timeout);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Common utility functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Session utility functions
function isLoggedIn() {
    return isset($_SESSION['admin']) || isset($_SESSION['admin_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getAdminId() {
    return $_SESSION['user_id'] ?? 0;
}

// Constants for attendance status
define('STATUS_PRESENT', 'Present');
define('STATUS_ABSENT', 'Absent');
?> 