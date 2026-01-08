<?php
// Démarrer la session seulement si elle n'existe pas déjà
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger la configuration de la base de données
require_once __DIR__ . '/db_config.php';

// Vérifier et définir les valeurs par défaut si elles ne sont pas définies
// Validation stricte pour éviter les erreurs de connexion
$host = isset($db_host) && !empty(trim($db_host)) && trim($db_host) !== 'root' ? trim($db_host) : 'localhost';
$username = isset($db_user) && !empty($db_user) ? $db_user : 'root';
$password = isset($db_password) ? $db_password : '';
$database_name = isset($db_name) && !empty($db_name) ? $db_name : 'gestion';

// Validation supplémentaire : s'assurer que $host n'est pas "root" (erreur courante)
if ($host === 'root') {
    error_log("ERREUR CRITIQUE: Le nom d'hôte de la base de données est 'root' au lieu d'un nom d'hôte valide.");
    error_log("db_host depuis db_config.php: " . (isset($db_host) ? $db_host : 'NON DÉFINI'));
    error_log("Vérifiez que les variables d'environnement sont correctement configurées sur Render.com");
    die("Erreur de configuration de la base de données : le nom d'hôte ne peut pas être 'root'. Veuillez vérifier les variables d'environnement EXTERNAL_DATABASE_HOST sur Render.com.");
}

// Définir le port et le socket avec des valeurs par défaut
$db_port = isset($db_port) && !empty($db_port) ? $db_port : '3306';
$db_socket = isset($db_socket) ? $db_socket : '';

// Journaliser la configuration utilisée pour le débogage
error_log("Configuration DB - Host: $host, User: $username, Database: $database_name, Port: $db_port");

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fonction pour vérifier si un hôte est résolvable
function is_host_resolvable($host) {
    // Vérifier si c'est localhost ou une adresse IP
    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        return true;
    }
    
    // Essayer de résoudre le nom d'hôte
    $ip = gethostbyname($host);
    return $ip !== $host; // Si l'IP est différente du nom, c'est résolu
}

// Fonction pour attendre que la base de données soit prête
function wait_for_db($host, $username, $password, $db_name, $port = 3306, $socket = '', $max_attempts = 15) {
    $attempts = 0;
    $connected = false;
    
    // Vérifier d'abord si l'hôte est résolvable (sauf pour localhost)
    if ($host !== 'localhost' && !is_host_resolvable($host)) {
        error_log("ERREUR: Le nom d'hôte '$host' ne peut pas être résolu. Vérifiez que le service de base de données est démarré et accessible.");
        return false;
    }
    
    while (!$connected && $attempts < $max_attempts) {
        error_log("Tentative de connexion à la base de données ($host:$port) - tentative " . ($attempts + 1) . "/$max_attempts");
        try {
            // Utiliser le socket si spécifié, sinon utiliser le port
            if (!empty($socket)) {
                error_log("Connexion via socket: $socket");
                $temp_link = @new mysqli($host, $username, $password, $db_name, null, $socket);
            } else {
                error_log("Connexion via port: $port");
                $temp_link = @new mysqli($host, $username, $password, $db_name, $port);
            }
            
            if ($temp_link && !$temp_link->connect_error) {
                error_log("Connexion réussie à la base de données après " . ($attempts + 1) . " tentative(s)");
                $temp_link->close();
                $connected = true;
            } else {
                $error_msg = $temp_link ? $temp_link->connect_error : 'Impossible de créer la connexion';
                error_log("Échec de la connexion: " . $error_msg);
                $attempts++;
                if ($attempts < $max_attempts) {
                    sleep(2); // Attendre 2 secondes avant de réessayer
                }
            }
        } catch (Exception $e) {
            error_log("Exception lors de la tentative de connexion: " . $e->getMessage());
            $attempts++;
            if ($attempts < $max_attempts) {
                sleep(2);
            }
        }
    }
    
    if (!$connected) {
        error_log("ERREUR CRITIQUE: Impossible de se connecter à la base de données après $max_attempts tentatives");
        if ($host === 'db') {
            error_log("Le service 'db' n'est pas accessible. Vérifiez que:");
            error_log("1. Le conteneur de base de données est démarré (docker-compose up -d db)");
            error_log("2. Les conteneurs sont dans le même réseau Docker");
            error_log("3. Le service s'appelle bien 'db' dans docker-compose.yml");
        }
    }
    
    return $connected;
}

// Dans l'environnement de production, attendre que la base de données soit prête
$is_docker_env = file_exists('/.dockerenv') || getenv('DB_HOST') || getenv('RENDER');
if ($is_docker_env) {
    $db_ready = wait_for_db($host, $username, $password, $database_name, $db_port, $db_socket);
    if (!$db_ready && $host === 'db') {
        // Message d'erreur plus explicite pour Docker
        $error_msg = "Impossible de se connecter au service de base de données 'db'.\n\n";
        $error_msg .= "Vérifications à effectuer:\n";
        $error_msg .= "1. Vérifiez que le conteneur de base de données est démarré: docker-compose ps\n";
        $error_msg .= "2. Démarrez les services si nécessaire: docker-compose up -d\n";
        $error_msg .= "3. Vérifiez les logs de la base de données: docker-compose logs db\n";
        $error_msg .= "4. Vérifiez que les conteneurs sont dans le même réseau Docker\n\n";
        $error_msg .= "Configuration actuelle:\n";
        $error_msg .= "- Host: $host\n";
        $error_msg .= "- Port: $db_port\n";
        $error_msg .= "- Database: $database_name\n";
        $error_msg .= "- User: $username\n";
        
        error_log($error_msg);
        die("<h1>Erreur de connexion à la base de données</h1><pre>" . htmlspecialchars($error_msg) . "</pre>");
    }
}

// Créer la connexion avec mysqli
if (!empty($database_name)) {
    // Utiliser le socket si spécifié, sinon utiliser le port
    if (!empty($db_socket)) {
        error_log("Connexion principale via socket: $db_socket");
        $link = new mysqli($host, $username, $password, $database_name, null, $db_socket);
    } else {
        error_log("Connexion principale via port: $db_port");
        $link = new mysqli($host, $username, $password, $database_name, $db_port);
    }
} else {
    // Se connecter sans spécifier de base de données
    if (!empty($db_socket)) {
        $link = new mysqli($host, $username, $password, null, null, $db_socket);
    } else {
        $link = new mysqli($host, $username, $password, null, $db_port);
    }
}

// Vérifier la connexion
if ($link->connect_error) {
    $error_msg = "Erreur de connexion à la base de données: " . $link->connect_error;
    error_log($error_msg);
    
    // Message d'erreur plus détaillé pour Docker
    if ($host === 'db' && strpos($link->connect_error, 'getaddrinfo') !== false) {
        $detailed_error = "<h1>Erreur de connexion à la base de données</h1>\n";
        $detailed_error .= "<p><strong>Le service de base de données 'db' n'est pas accessible.</strong></p>\n";
        $detailed_error .= "<p>Erreur: " . htmlspecialchars($link->connect_error) . "</p>\n";
        $detailed_error .= "<h2>Solutions possibles:</h2>\n";
        $detailed_error .= "<ol>\n";
        $detailed_error .= "<li>Vérifiez que le conteneur de base de données est démarré:<br>\n";
        $detailed_error .= "<code>docker-compose ps</code></li>\n";
        $detailed_error .= "<li>Démarrez les services si nécessaire:<br>\n";
        $detailed_error .= "<code>docker-compose up -d</code></li>\n";
        $detailed_error .= "<li>Vérifiez les logs de la base de données:<br>\n";
        $detailed_error .= "<code>docker-compose logs db</code></li>\n";
        $detailed_error .= "<li>Vérifiez que les conteneurs sont dans le même réseau Docker</li>\n";
        $detailed_error .= "</ol>\n";
        $detailed_error .= "<h3>Configuration actuelle:</h3>\n";
        $detailed_error .= "<ul>\n";
        $detailed_error .= "<li>Host: " . htmlspecialchars($host) . "</li>\n";
        $detailed_error .= "<li>Port: " . htmlspecialchars($db_port) . "</li>\n";
        $detailed_error .= "<li>Database: " . htmlspecialchars($database_name) . "</li>\n";
        $detailed_error .= "<li>User: " . htmlspecialchars($username) . "</li>\n";
        $detailed_error .= "</ul>\n";
        die($detailed_error);
    }
    
    die("La connexion a échoué: " . htmlspecialchars($link->connect_error));
}

// Définir le jeu de caractères
if (!$link->set_charset("utf8")) {
    error_log("Erreur lors de la définition du jeu de caractères utf8: " . $link->error);
}

// Désactiver le mode strict SQL
$link->query("SET sql_mode = ''");

error_log("Connexion à la base de données réussie");
?>
