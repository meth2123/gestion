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

// Détection spécifique de Render.com (PRIORITAIRE - vérifier en premier)
// Render utilise Docker mais a ses propres variables d'environnement
// Render génère automatiquement des variables MySQL standard : MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, etc.
$is_render = (
    getEnvVar('RENDER') == 'true' || 
    getEnvVar('IS_RENDER') == 'true' || 
    strpos(getEnvVar('RENDER_SERVICE_ID', ''), 'srv-') === 0 ||
    !empty(getEnvVar('EXTERNAL_DATABASE_HOST')) || // Si EXTERNAL_DATABASE_HOST est défini
    getEnvVar('RENDER_EXTERNAL_HOST') !== null || // Variable Render spécifique
    !empty(getEnvVar('MYSQLHOST')) || // Variable MySQL standard de Render
    !empty(getEnvVar('MYSQL_HOST')) || // Alternative
    (file_exists('/.dockerenv') && getEnvVar('DB_HOST') && getEnvVar('DB_HOST') !== 'db') // Docker mais pas le service local 'db'
);

// Journaliser la détection de l'environnement
error_log("Détection d'environnement - RENDER: " . ($is_render ? 'true' : 'false'));
error_log("RENDER env: " . getEnvVar('RENDER', 'non défini'));
error_log("IS_RENDER env: " . getEnvVar('IS_RENDER', 'non défini'));
error_log("DB_HOST env: " . getEnvVar('DB_HOST', 'non défini'));
error_log("MYSQLHOST env: " . getEnvVar('MYSQLHOST', 'non défini'));
error_log("MYSQL_HOST env: " . getEnvVar('MYSQL_HOST', 'non défini'));
error_log("MYSQLUSER env: " . getEnvVar('MYSQLUSER', 'non défini'));
error_log("MYSQL_USER env: " . getEnvVar('MYSQL_USER', 'non défini'));
error_log("MYSQ_LUSER env: " . getEnvVar('MYSQ_LUSER', 'non défini') . " (vérification faute de frappe)");
error_log("MYSQLDATABASE env: " . getEnvVar('MYSQLDATABASE', 'non défini'));
error_log("MYSQL_DATABASE env: " . getEnvVar('MYSQL_DATABASE', 'non défini'));
error_log("EXTERNAL_DATABASE_HOST: " . (getEnvVar('EXTERNAL_DATABASE_HOST') ?: 'non défini'));
error_log("/.dockerenv existe: " . (file_exists('/.dockerenv') ? 'oui' : 'non'));

// Vérifier si nous sommes dans un environnement de production (Docker, Render.com, etc.)
// PRIORITÉ: Render.com d'abord, puis Docker local
if ($is_render || file_exists('/.dockerenv') || getenv('DB_HOST')) {
    // Définir des valeurs par défaut pour Render.com si détecté (PRIORITÉ)
    if ($is_render) {
        // Sur Render.com, on peut utiliser plusieurs sources de configuration :
        // 1. Variables MySQL standard de Render (MYSQLHOST, MYSQLUSER, etc.) - PRIORITÉ
        // 2. EXTERNAL_DATABASE_* (base externe)
        // 3. DB_* (base Render générée automatiquement via render.yaml)
        
        // PRIORITÉ 1: Vérifier les variables MySQL standard (Render, Railway, etc.)
        // Supporte plusieurs formats : MYSQLHOST, MYSQL_HOST, MYSQ_LUSER (faute de frappe courante)
        $mysql_host = getEnvVar('MYSQLHOST') ?: getEnvVar('MYSQL_HOST');
        $mysql_user = getEnvVar('MYSQLUSER') ?: getEnvVar('MYSQL_USER') ?: getEnvVar('MYSQ_LUSER'); // Gère aussi la faute de frappe MYSQ_LUSER
        $mysql_password = getEnvVar('MYSQLPASSWORD') ?: getEnvVar('MYSQL_PASSWORD');
        $mysql_database = getEnvVar('MYSQLDATABASE') ?: getEnvVar('MYSQL_DATABASE');
        $mysql_port = getEnvVar('MYSQLPORT') ?: getEnvVar('MYSQL_PORT', '3306');
        
        if (!empty($mysql_host) && !empty($mysql_user) && !empty($mysql_database)) {
            // Utiliser les variables MySQL standard de Render
            $db_host = $mysql_host;
            $db_user = $mysql_user;
            $db_password = $mysql_password ?: getEnvVar('MYSQL_ROOT_PASSWORD', '');
            $db_name = $mysql_database;
            $db_port = $mysql_port;
            $db_socket = '';
            
            // Détecter si c'est Railway (mysql.railway.internal) ou Render
            $is_railway = (strpos($db_host, 'railway') !== false);
            $platform = $is_railway ? 'Railway' : 'Render.com';
            error_log("Environnement $platform détecté. Utilisation des variables MySQL standard: $db_host:$db_port, utilisateur: $db_user, base: $db_name");
        }
        // PRIORITÉ 2: Vérifier si EXTERNAL_DATABASE_HOST est défini (base externe)
        elseif (!empty(getEnvVar('EXTERNAL_DATABASE_HOST'))) {
            $external_host = getEnvVar('EXTERNAL_DATABASE_HOST');
            // Validation : EXTERNAL_DATABASE_HOST ne doit pas être 'db' (nom de service Docker local)
            if ($external_host === 'db' || $external_host === 'localhost') {
                error_log("ERREUR CRITIQUE: EXTERNAL_DATABASE_HOST est défini à '$external_host' - c'est incorrect pour Render.com");
                echo "<h1>Erreur de configuration Render.com</h1>";
                echo "<p><strong>EXTERNAL_DATABASE_HOST est défini à 'db' ou 'localhost', ce qui est incorrect.</strong></p>";
                echo "<p>'db' est le nom d'un service Docker local, pas une adresse de base de données sur Render.</p>";
                echo "<h2>Solutions :</h2>";
                echo "<h3>Option 1 : Utiliser la base de données Render (recommandé)</h3>";
                echo "<p>Si vous utilisez la base de données MySQL créée via render.yaml :</p>";
                echo "<ol>";
                echo "<li>Supprimez toutes les variables EXTERNAL_DATABASE_* du dashboard Render</li>";
                echo "<li>Laissez Render générer automatiquement les variables DB_* via render.yaml</li>";
                echo "<li>Les variables DB_HOST, DB_USER, DB_PASSWORD, DB_NAME seront créées automatiquement</li>";
                echo "</ol>";
                echo "<h3>Option 2 : Utiliser une vraie base de données externe</h3>";
                echo "<p>Si vous utilisez une base externe (PlanetScale, Railway, etc.) :</p>";
                echo "<ol>";
                echo "<li>Remplacez EXTERNAL_DATABASE_HOST par l'adresse réelle de votre base (ex: mysql.example.com)</li>";
                echo "<li>Ne mettez pas 'db' ou 'localhost'</li>";
                echo "</ol>";
                echo "<h3>Configuration actuelle détectée :</h3>";
                echo "<ul>";
                echo "<li>EXTERNAL_DATABASE_HOST: <strong>" . htmlspecialchars($external_host) . "</strong> (incorrect)</li>";
                echo "<li>EXTERNAL_DATABASE_USER: " . htmlspecialchars(getEnvVar('EXTERNAL_DATABASE_USER', 'non défini')) . "</li>";
                echo "<li>EXTERNAL_DATABASE_NAME: " . htmlspecialchars(getEnvVar('EXTERNAL_DATABASE_NAME', 'non défini')) . "</li>";
                echo "</ul>";
                exit;
            }
            
            // Utiliser les paramètres de connexion externes pour Render.com
            $db_host = $external_host;
            $db_user = getEnvVar('EXTERNAL_DATABASE_USER');
            $db_password = getEnvVar('EXTERNAL_DATABASE_PASSWORD');
            $db_name = getEnvVar('EXTERNAL_DATABASE_NAME');
            $db_port = getEnvVar('EXTERNAL_DATABASE_PORT', '3306');
            $db_socket = '';
            
            // Vérifier que les variables d'environnement sont définies
            if (empty($db_host) || empty($db_user) || empty($db_name)) {
                error_log("ERREUR CRITIQUE: Variables d'environnement manquantes pour la base de données externe sur Render.com");
                error_log("EXTERNAL_DATABASE_HOST: " . ($db_host ?: 'VIDE'));
                error_log("EXTERNAL_DATABASE_USER: " . ($db_user ?: 'VIDE'));
                error_log("EXTERNAL_DATABASE_NAME: " . ($db_name ?: 'VIDE'));
                echo "<h1>Erreur de configuration</h1>";
                echo "<p>L'application n'est pas correctement configurée pour Render.com.</p>";
                echo "<p>Si vous utilisez une base de données externe, veuillez configurer les variables suivantes :</p>";
                echo "<ul>";
                echo "<li>EXTERNAL_DATABASE_HOST (doit être une adresse réelle, pas 'db')</li>";
                echo "<li>EXTERNAL_DATABASE_USER</li>";
                echo "<li>EXTERNAL_DATABASE_PASSWORD</li>";
                echo "<li>EXTERNAL_DATABASE_NAME</li>";
                echo "</ul>";
                exit;
            }
            
            error_log("Environnement Render.com détecté. Utilisation de la base de données EXTERNE: $db_host:$db_port, utilisateur: $db_user, base: $db_name");
        }
        // PRIORITÉ 3: Utiliser les variables DB_* générées automatiquement par Render (via render.yaml)
        else {
            $db_host = getEnvVar('DB_HOST');
            $db_user = getEnvVar('DB_USER');
            $db_password = getEnvVar('DB_PASSWORD');
            $db_name = getEnvVar('DB_NAME');
            $db_port = getEnvVar('DB_PORT', '3306');
            $db_socket = getEnvVar('DB_SOCKET', '');
            
            // Vérifier que les variables sont définies
            if (empty($db_host) || empty($db_user) || empty($db_name)) {
                error_log("ERREUR CRITIQUE: Variables DB_* manquantes sur Render.com");
                error_log("DB_HOST: " . ($db_host ?: 'VIDE'));
                error_log("DB_USER: " . ($db_user ?: 'VIDE'));
                error_log("DB_NAME: " . ($db_name ?: 'VIDE'));
                error_log("Vérifiez que render.yaml configure correctement la base de données avec 'fromDatabase'");
                echo "<h1>Erreur de configuration Render.com</h1>";
                echo "<p><strong>Les variables de base de données DB_* ne sont pas définies sur Render.com.</strong></p>";
                echo "<h2>Solutions :</h2>";
                echo "<h3>Option 1 : Vérifier que la base de données est créée</h3>";
                echo "<ol>";
                echo "<li>Allez dans votre dashboard Render.com</li>";
                echo "<li>Vérifiez que le service MySQL <strong>schoolmanager-db</strong> existe et est actif</li>";
                echo "<li>Si la base n'existe pas, créez-la via le dashboard ou attendez que render.yaml la crée</li>";
                echo "</ol>";
                echo "<h3>Option 2 : Vérifier les variables d'environnement</h3>";
                echo "<ol>";
                echo "<li>Dans le dashboard Render, allez dans votre service web <strong>schoolmanager</strong></li>";
                echo "<li>Allez dans l'onglet <strong>Environment</strong></li>";
                echo "<li>Vérifiez que les variables suivantes existent :</li>";
                echo "<ul>";
                echo "<li>DB_HOST (doit être une adresse, pas 'db')</li>";
                echo "<li>DB_USER</li>";
                echo "<li>DB_PASSWORD</li>";
                echo "<li>DB_NAME (doit être 'gestion')</li>";
                echo "</ul>";
                echo "<li>Si elles n'existent pas, Render devrait les créer automatiquement via render.yaml</li>";
                echo "</ol>";
                echo "<h3>Option 3 : Utiliser une base de données externe</h3>";
                echo "<p>Si vous préférez utiliser une base externe, configurez ces variables dans Render :</p>";
                echo "<ul>";
                echo "<li>EXTERNAL_DATABASE_HOST</li>";
                echo "<li>EXTERNAL_DATABASE_USER</li>";
                echo "<li>EXTERNAL_DATABASE_PASSWORD</li>";
                echo "<li>EXTERNAL_DATABASE_NAME</li>";
                echo "</ul>";
                echo "<h3>État actuel détecté :</h3>";
                echo "<ul>";
                echo "<li>RENDER env: " . htmlspecialchars(getEnvVar('RENDER', 'non défini')) . "</li>";
                echo "<li>IS_RENDER env: " . htmlspecialchars(getEnvVar('IS_RENDER', 'non défini')) . "</li>";
                echo "<li>DB_HOST: " . htmlspecialchars($db_host ?: 'VIDE') . "</li>";
                echo "<li>DB_USER: " . htmlspecialchars($db_user ?: 'VIDE') . "</li>";
                echo "<li>DB_NAME: " . htmlspecialchars($db_name ?: 'VIDE') . "</li>";
                echo "</ul>";
                exit;
            }
            
            // Validation spéciale pour Render : DB_HOST ne doit pas être 'db' (nom de service Docker local)
            if ($db_host === 'db') {
                error_log("ERREUR CRITIQUE: DB_HOST est 'db' sur Render.com - c'est une erreur de configuration");
                echo "<h1>Erreur de configuration Render.com</h1>";
                echo "<p><strong>Le système détecte Render.com mais DB_HOST est défini à 'db' (nom de service Docker local).</strong></p>";
                echo "<p>Sur Render.com, DB_HOST doit être l'adresse du serveur MySQL de Render, pas 'db'.</p>";
                echo "<h2>Solutions :</h2>";
                echo "<ol>";
                echo "<li><strong>Vérifiez render.yaml :</strong> Assurez-vous que la section <code>envVars</code> du service web configure DB_HOST avec <code>fromDatabase</code></li>";
                echo "<li><strong>Vérifiez le dashboard Render :</strong> Allez dans votre service web > Environment et vérifiez que DB_HOST est bien défini (ne doit pas être 'db')</li>";
                echo "<li><strong>Si vous utilisez une base externe :</strong> Configurez EXTERNAL_DATABASE_HOST au lieu de DB_HOST</li>";
                echo "</ol>";
                echo "<h3>Configuration actuelle détectée :</h3>";
                echo "<ul>";
                echo "<li>DB_HOST: <strong>" . htmlspecialchars($db_host) . "</strong> (incorrect pour Render)</li>";
                echo "<li>DB_USER: " . htmlspecialchars($db_user ?: 'non défini') . "</li>";
                echo "<li>DB_NAME: " . htmlspecialchars($db_name ?: 'non défini') . "</li>";
                echo "<li>RENDER env: " . htmlspecialchars(getEnvVar('RENDER', 'non défini')) . "</li>";
                echo "<li>IS_RENDER env: " . htmlspecialchars(getEnvVar('IS_RENDER', 'non défini')) . "</li>";
                echo "</ul>";
                exit;
            }
            
            error_log("Environnement Render.com détecté. Utilisation de la base de données Render (via render.yaml): $db_host:$db_port, utilisateur: $db_user, base: $db_name");
        }
        
        // Validation supplémentaire : s'assurer que $db_host n'est pas vide ou égal à "root"
        if ($db_host === 'root' || empty(trim($db_host))) {
            error_log("ERREUR CRITIQUE: DB_HOST a une valeur invalide: '$db_host'");
            echo "<h1>Erreur de configuration</h1>";
            echo "<p>La variable DB_HOST a une valeur invalide. Elle ne peut pas être 'root' ou vide.</p>";
            echo "<p>Veuillez vérifier la configuration dans le dashboard Render.com ou dans render.yaml.</p>";
            exit;
        }
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
