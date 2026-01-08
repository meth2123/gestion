<?php
/**
 * Configuration de la base de données
 */

// Fonction helper pour obtenir les variables d'environnement (compatible avec getenv et $_ENV)
function getEnvVar($key, $default = null) {
    $value = getenv($key);
    if ($value === false && isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }
    return $value !== false ? $value : $default;
}

// Détection spécifique de Render.com
$is_render = (
    getEnvVar('RENDER') == 'true' || 
    getEnvVar('IS_RENDER') == 'true' || 
    strpos(getEnvVar('RENDER_SERVICE_ID', ''), 'srv-') === 0 ||
    !empty(getEnvVar('EXTERNAL_DATABASE_HOST')) // Si EXTERNAL_DATABASE_HOST est défini, on est probablement sur Render
);

// Journaliser la détection de l'environnement
error_log("Détection d'environnement - RENDER: " . ($is_render ? 'true' : 'false'));
error_log("RENDER env: " . getEnvVar('RENDER', 'non défini'));
error_log("IS_RENDER env: " . getEnvVar('IS_RENDER', 'non défini'));
error_log("EXTERNAL_DATABASE_HOST: " . (getEnvVar('EXTERNAL_DATABASE_HOST') ?: 'non défini'));

// Vérifier si nous sommes dans un environnement de production (Docker, Render.com, etc.)
if (file_exists('/.dockerenv') || getenv('DB_HOST') || $is_render) {
    // Définir des valeurs par défaut pour Render.com si détecté
    if ($is_render) {
        // Utiliser les paramètres de connexion externes pour Render.com
        // Ces valeurs doivent être configurées dans le dashboard Render.com
        $db_host = getEnvVar('EXTERNAL_DATABASE_HOST');
        $db_user = getEnvVar('EXTERNAL_DATABASE_USER');
        $db_password = getEnvVar('EXTERNAL_DATABASE_PASSWORD');
        $db_name = getEnvVar('EXTERNAL_DATABASE_NAME');
        $db_port = getEnvVar('EXTERNAL_DATABASE_PORT', '3306');
        $db_socket = '';
        
        // Vérifier que les variables d'environnement sont définies
        if (empty($db_host) || empty($db_user) || empty($db_name)) {
            error_log("ERREUR CRITIQUE: Variables d'environnement manquantes pour la base de données sur Render.com");
            error_log("EXTERNAL_DATABASE_HOST: " . ($db_host ?: 'VIDE'));
            error_log("EXTERNAL_DATABASE_USER: " . ($db_user ?: 'VIDE'));
            error_log("EXTERNAL_DATABASE_NAME: " . ($db_name ?: 'VIDE'));
            error_log("Veuillez configurer EXTERNAL_DATABASE_HOST, EXTERNAL_DATABASE_USER, EXTERNAL_DATABASE_PASSWORD et EXTERNAL_DATABASE_NAME");
            // Afficher un message d'erreur plus convivial
            echo "<h1>Erreur de configuration</h1>";
            echo "<p>L'application n'est pas correctement configurée pour Render.com.</p>";
            echo "<p>Veuillez configurer les variables d'environnement suivantes dans le dashboard Render.com :</p>";
            echo "<ul>";
            echo "<li>EXTERNAL_DATABASE_HOST</li>";
            echo "<li>EXTERNAL_DATABASE_USER</li>";
            echo "<li>EXTERNAL_DATABASE_PASSWORD</li>";
            echo "<li>EXTERNAL_DATABASE_NAME</li>";
            echo "</ul>";
            echo "<p>Et au moins une de ces variables : RENDER=true ou IS_RENDER=true</p>";
            exit;
        }
        
        // Validation supplémentaire : s'assurer que $db_host n'est pas vide ou égal à "root"
        if ($db_host === 'root' || empty(trim($db_host))) {
            error_log("ERREUR CRITIQUE: EXTERNAL_DATABASE_HOST a une valeur invalide: '$db_host'");
            echo "<h1>Erreur de configuration</h1>";
            echo "<p>La variable EXTERNAL_DATABASE_HOST a une valeur invalide. Elle ne peut pas être 'root' ou vide.</p>";
            echo "<p>Veuillez vérifier la configuration dans le dashboard Render.com.</p>";
            exit;
        }
        
        error_log("Environnement Render.com détecté. Connexion à la base de données externe: $db_host:$db_port, utilisateur: $db_user, base: $db_name");
    } else {
        // Configuration standard pour Docker ou autre environnement de production
        $db_host = getEnvVar('DB_HOST', 'fdb1034.awardspace.net');
        $db_user = getEnvVar('DB_USER', '4699098_root');
        $db_password = getEnvVar('DB_PASSWORD', '#Alamine123');
        $db_name = getEnvVar('DB_NAME', '4699098_root');
        $db_port = getEnvVar('DB_PORT', '3306');
        $db_socket = getEnvVar('DB_SOCKET', '');
    }
    
    // Journaliser les informations de connexion (sans le mot de passe)
    error_log("Environnement de production détecté. Connexion à la base de données: $db_host:$db_port, utilisateur: $db_user, base: $db_name");
} else {
    // Paramètres pour l'environnement local (WAMP)
    $db_host = 'localhost';
    $db_user = 'root';      // Utilisateur par défaut pour WAMP
    $db_password = '';      // Mot de passe par défaut pour WAMP
    $db_name = 'gestion';   // Nom de la base de données de l'application
    $db_port = '3306';      // Port par défaut pour MySQL
    $db_socket = '';        // Socket par défaut (vide pour TCP/IP)
}

// Charset et collation
$db_charset = 'utf8mb4';
$db_collation = 'utf8mb4_unicode_ci';
?>
