<?php
/**
 * Configuration de la base de données
 */

// Détection spécifique de Render.com
$is_render = (getenv('RENDER') == 'true' || getenv('IS_RENDER') == 'true' || strpos(getenv('RENDER_SERVICE_ID') ?: '', 'srv-') === 0);

// Journaliser la détection de l'environnement
error_log("Détection d'environnement - RENDER: " . ($is_render ? 'true' : 'false'));
error_log("Variables d'environnement disponibles: " . implode(', ', array_keys($_ENV)));

// Vérifier si nous sommes dans un environnement de production (Docker, Render.com, etc.)
if (file_exists('/.dockerenv') || getenv('DB_HOST') || $is_render) {
    // Définir des valeurs par défaut pour Render.com si détecté
    if ($is_render) {
        // Utiliser les paramètres de connexion externes pour Render.com
        // Ces valeurs doivent être configurées dans le dashboard Render.com
        $db_host = getenv('EXTERNAL_DATABASE_HOST');
        $db_user = getenv('EXTERNAL_DATABASE_USER');
        $db_password = getenv('EXTERNAL_DATABASE_PASSWORD');
        $db_name = getenv('EXTERNAL_DATABASE_NAME');
        $db_port = getenv('EXTERNAL_DATABASE_PORT') ?: '3306';
        $db_socket = '';
        
        // Vérifier que les variables d'environnement sont définies
        if (empty($db_host) || empty($db_user) || empty($db_name)) {
            error_log("ERREUR CRITIQUE: Variables d'environnement manquantes pour la base de données sur Render.com");
            error_log("Veuillez configurer EXTERNAL_DATABASE_HOST, EXTERNAL_DATABASE_USER, EXTERNAL_DATABASE_PASSWORD et EXTERNAL_DATABASE_NAME");
            // Afficher un message d'erreur plus convivial
            echo "<h1>Erreur de configuration</h1>";
            echo "<p>L'application n'est pas correctement configurée pour Render.com.</p>";
            echo "<p>Veuillez configurer les variables d'environnement pour la base de données dans le dashboard Render.com.</p>";
            exit;
        }
        
        error_log("Environnement Render.com détecté. Connexion à la base de données externe: $db_host");
    } else {
        // Configuration standard pour Docker ou autre environnement de production
        $db_host = getenv('DB_HOST') ?: 'fdb1034.awardspace.net';
        $db_user = getenv('DB_USER') ?: '4699098_root';
        $db_password = getenv('DB_PASSWORD') ?: '#Alamine123';
        $db_name = getenv('DB_NAME') ?: '4699098_root';
        $db_port = getenv('DB_PORT') ?: '3306';
        $db_socket = getenv('DB_SOCKET') ?: '';
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
