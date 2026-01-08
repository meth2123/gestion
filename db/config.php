<?php
// Database configuration
// Utiliser la même logique que service/db_config.php pour la compatibilité

// Fonction helper pour obtenir les variables d'environnement
function getEnvVar($key, $default = null) {
    $value = getenv($key);
    if ($value === false && isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }
    return $value !== false ? $value : $default;
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
    // Utiliser les variables d'environnement avec fallback
    $db_host = getEnvVar('DB_HOST') ?: getEnvVar('MYSQLHOST') ?: getEnvVar('MYSQL_HOST') ?: getEnvVar('EXTERNAL_DATABASE_HOST') ?: 'db';
    $db_user = getEnvVar('DB_USER') ?: getEnvVar('MYSQLUSER') ?: getEnvVar('MYSQL_USER') ?: getEnvVar('EXTERNAL_DATABASE_USER') ?: 'root';
    $db_password = getEnvVar('DB_PASSWORD') ?: getEnvVar('MYSQLPASSWORD') ?: getEnvVar('MYSQL_PASSWORD') ?: getEnvVar('EXTERNAL_DATABASE_PASSWORD') ?: '';
    $db_name = getEnvVar('DB_NAME') ?: getEnvVar('MYSQLDATABASE') ?: getEnvVar('MYSQL_DATABASE') ?: getEnvVar('EXTERNAL_DATABASE_NAME') ?: 'gestion';
    
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
    
    // Créer la connexion avec gestion d'erreurs
    $conn = @new mysqli($host, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Erreur de connexion à la base de données: " . $conn->connect_error);
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