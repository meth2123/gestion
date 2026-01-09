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
    !empty(getEnvVar('MYSQLHOST')) ||
    !empty(getEnvVar('MYSQL_HOST')) ||
    !empty(getEnvVar('MYSQL_URL')) ||
    !empty(getEnvVar('MYSQL_PUBLIC_URL')) ||
    !empty(getEnvVar('EXTERNAL_DATABASE_HOST')) // Garder pour compatibilité mais pas prioritaire
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
    // PRIORITÉ: Variables Railway standard (MYSQLHOST, MYSQLUSER, etc.) puis DB_* puis EXTERNAL_DATABASE_*
    if (empty($db_host)) {
        // Essayer d'abord les variables MySQL standard de Railway/Render
        $mysql_host = getEnvVar('MYSQLHOST') ?: getEnvVar('MYSQL_HOST');
        $mysql_user = getEnvVar('MYSQLUSER') ?: getEnvVar('MYSQL_USER');
        $mysql_password = getEnvVar('MYSQLPASSWORD') ?: getEnvVar('MYSQL_PASSWORD');
        $mysql_database = getEnvVar('MYSQLDATABASE') ?: getEnvVar('MYSQL_DATABASE');
        
        if (!empty($mysql_host) && !empty($mysql_user) && !empty($mysql_database)) {
            // Vérifier si c'est un hôte Railway interne (ne fonctionne que sur Railway)
            if ($mysql_host === 'mysql.railway.internal' || strpos($mysql_host, '.railway.internal') !== false) {
                error_log("ATTENTION: mysql.railway.internal détecté - cet hôte ne fonctionne que sur Railway");
                error_log("Tentative d'utilisation de MYSQL_PUBLIC_URL à la place...");
                
                // Essayer d'utiliser MYSQL_PUBLIC_URL qui contient l'hôte public
                $public_url = getEnvVar('MYSQL_PUBLIC_URL');
                if (!empty($public_url)) {
                    if (preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:]+):?(\d+)?\/([^?]+)/', $public_url, $url_matches)) {
                        $db_host = $url_matches[3];
                        $db_user = $url_matches[1];
                        $db_password = $url_matches[2];
                        $db_name = $url_matches[5];
                        error_log("Utilisation de l'hôte public Railway depuis MYSQL_PUBLIC_URL: $db_host");
                    }
                }
                
                // Si on n'a toujours pas d'hôte public, continuer avec les autres fallbacks
                if (empty($db_host)) {
                    error_log("MYSQL_PUBLIC_URL non disponible, utilisation des variables DB_* ou autres fallbacks");
                }
            } else {
                // Hôte public valide, l'utiliser directement
                $db_host = $mysql_host;
                $db_user = $mysql_user;
                $db_password = $mysql_password;
                $db_name = $mysql_database;
                error_log("Utilisation des variables MySQL standard Railway: $db_host, utilisateur: $db_user, base: $db_name");
            }
        }
        
        // Si toujours pas d'hôte, utiliser les variables DB_* (générées par Render via render.yaml)
        if (empty($db_host)) {
            $db_host = getEnvVar('DB_HOST');
            $db_user = getEnvVar('DB_USER');
            $db_password = getEnvVar('DB_PASSWORD');
            $db_name = getEnvVar('DB_NAME');
            
            if (!empty($db_host) && !empty($db_user) && !empty($db_name)) {
                error_log("Utilisation des variables DB_* (Render): $db_host, utilisateur: $db_user, base: $db_name");
            }
        }
        
        // Dernier recours: EXTERNAL_DATABASE_* (seulement si rien d'autre n'est disponible)
        if (empty($db_host)) {
            $db_host = getEnvVar('EXTERNAL_DATABASE_HOST');
            $db_user = getEnvVar('EXTERNAL_DATABASE_USER');
            $db_password = getEnvVar('EXTERNAL_DATABASE_PASSWORD');
            $db_name = getEnvVar('EXTERNAL_DATABASE_NAME');
            
            if (!empty($db_host) && !empty($db_user) && !empty($db_name)) {
                error_log("Utilisation des variables EXTERNAL_DATABASE_*: $db_host, utilisateur: $db_user, base: $db_name");
            }
        }
        
        // Valeurs par défaut uniquement si rien n'a été trouvé
        if (empty($db_host)) {
            $db_host = 'db';
            $db_user = 'root';
            $db_password = '';
            $db_name = 'gestion';
            error_log("ATTENTION: Aucune variable d'environnement de base de données trouvée, utilisation des valeurs par défaut");
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
    
    // Créer la connexion avec gestion d'erreurs et exceptions
    try {
        // Désactiver temporairement les exceptions mysqli pour gérer les erreurs manuellement
        $old_report_mode = mysqli_report(MYSQLI_REPORT_OFF);
        
        $conn = @new mysqli($host, DB_USER, DB_PASSWORD, DB_NAME);
        
        // Restaurer le mode de rapport précédent
        mysqli_report($old_report_mode);
        
        // Vérifier les erreurs de connexion
        if ($conn->connect_error) {
            $error_msg = "Erreur de connexion à la base de données: " . $conn->connect_error;
            $error_msg .= " (Host: $host, User: " . DB_USER . ", Database: " . DB_NAME . ")";
            
            // Message d'aide spécifique pour Railway
            if (strpos($conn->connect_error, 'getaddrinfo') !== false && strpos($host, 'railway') !== false) {
                $error_msg .= " | ASTUCE: Si vous êtes sur Render.com avec une base Railway, utilisez MYSQL_PUBLIC_URL au lieu de MYSQL_URL.";
            }
            
            // Message pour les timeouts
            if (strpos($conn->connect_error, 'Connection timed out') !== false || strpos($conn->connect_error, 'timed out') !== false) {
                $error_msg .= " | ERREUR: La connexion a expiré. Vérifiez que:";
                $error_msg .= " 1) Le serveur MySQL est accessible depuis cet environnement";
                $error_msg .= " 2) Les variables d'environnement EXTERNAL_DATABASE_HOST sont correctement configurées";
                $error_msg .= " 3) Le firewall/autorisations réseau permettent la connexion";
            }
            
            error_log($error_msg);
            return null;
        }
    } catch (mysqli_sql_exception $e) {
        // Restaurer le mode de rapport en cas d'exception
        if (isset($old_report_mode)) {
            mysqli_report($old_report_mode);
        } else {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        
        $error_msg = "Exception de connexion à la base de données: " . $e->getMessage();
        $error_msg .= " (Host: $host, User: " . DB_USER . ", Database: " . DB_NAME . ")";
        
        // Messages d'aide spécifiques selon le type d'erreur
        if (strpos($e->getMessage(), 'Connection timed out') !== false || strpos($e->getMessage(), 'timed out') !== false) {
            $error_msg .= " | ERREUR: La connexion a expiré. Vérifiez que:";
            $error_msg .= " 1) Le serveur MySQL est accessible depuis cet environnement";
            $error_msg .= " 2) Les variables d'environnement EXTERNAL_DATABASE_HOST sont correctement configurées";
            $error_msg .= " 3) Le firewall/autorisations réseau permettent la connexion";
        } elseif (strpos($e->getMessage(), 'getaddrinfo') !== false && strpos($host, 'railway') !== false) {
            $error_msg .= " | ASTUCE: Si vous êtes sur Render.com avec une base Railway, utilisez MYSQL_PUBLIC_URL au lieu de MYSQL_URL.";
        }
        
        error_log($error_msg);
        return null;
    } catch (Exception $e) {
        // Restaurer le mode de rapport en cas d'exception
        if (isset($old_report_mode)) {
            mysqli_report($old_report_mode);
        } else {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        
        $error_msg = "Erreur inattendue lors de la connexion: " . $e->getMessage();
        $error_msg .= " (Host: $host, User: " . DB_USER . ", Database: " . DB_NAME . ")";
        error_log($error_msg);
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