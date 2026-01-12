<?php
/**
 * Script de test pour diagnostiquer les problèmes Resend sur Render.com
 * Accédez à ce fichier via votre navigateur pour voir les informations de diagnostic
 */

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnostic Resend</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

// Section 1: Vérification des variables d'environnement
echo "<div class='section'>";
echo "<h2>1. Variables d'environnement</h2>";

$api_key_getenv = getenv('RESEND_API_KEY');
$api_key_env = isset($_ENV['RESEND_API_KEY']) ? $_ENV['RESEND_API_KEY'] : null;

echo "<p><strong>getenv('RESEND_API_KEY'):</strong> ";
if ($api_key_getenv !== false) {
    echo "<span class='success'>✅ Trouvé</span> (" . strlen($api_key_getenv) . " caractères)";
    echo "<br><small>Premiers caractères: " . substr($api_key_getenv, 0, 10) . "...</small>";
} else {
    echo "<span class='error'>❌ Non trouvé</span>";
}
echo "</p>";

echo "<p><strong>\$_ENV['RESEND_API_KEY']:</strong> ";
if ($api_key_env !== null) {
    echo "<span class='success'>✅ Trouvé</span> (" . strlen($api_key_env) . " caractères)";
    echo "<br><small>Premiers caractères: " . substr($api_key_env, 0, 10) . "...</small>";
} else {
    echo "<span class='error'>❌ Non trouvé</span>";
}
echo "</p>";

// Tester getEnvVar()
require_once(__DIR__ . '/service/resend_service.php');
$api_key_unified = getEnvVar('RESEND_API_KEY');
echo "<p><strong>getEnvVar('RESEND_API_KEY'):</strong> ";
if (!empty($api_key_unified)) {
    echo "<span class='success'>✅ Trouvé</span> (" . strlen($api_key_unified) . " caractères)";
} else {
    echo "<span class='error'>❌ Non trouvé</span>";
}
echo "</p>";

// Détection de l'environnement
$is_render = getenv('RENDER') === 'true' || getenv('IS_RENDER') === 'true';
echo "<p><strong>Environnement Render.com:</strong> ";
if ($is_render) {
    echo "<span class='success'>✅ Détecté</span>";
} else {
    echo "<span class='warning'>⚠️ Non détecté (environnement local ou autre)</span>";
}
echo "</p>";

echo "</div>";

// Section 2: Test de configuration Resend
echo "<div class='section'>";
echo "<h2>2. Configuration Resend</h2>";

$from_email = getEnvVar('RESEND_FROM_EMAIL', 'noreply@resend.dev');
$from_name = getEnvVar('RESEND_FROM_NAME', 'SchoolManager');

echo "<p><strong>Email expéditeur:</strong> " . htmlspecialchars($from_email) . "</p>";
echo "<p><strong>Nom expéditeur:</strong> " . htmlspecialchars($from_name) . "</p>";

$is_configured = is_resend_configured();
echo "<p><strong>Resend configuré:</strong> ";
if ($is_configured) {
    echo "<span class='success'>✅ Oui</span>";
} else {
    echo "<span class='error'>❌ Non</span>";
}
echo "</p>";

echo "</div>";

// Section 3: Test de connexion à l'API Resend
echo "<div class='section'>";
echo "<h2>3. Test de connexion à l'API Resend</h2>";

if (!$is_configured) {
    echo "<p class='error'>❌ Impossible de tester: RESEND_API_KEY non configurée</p>";
} else {
    // Test simple de connexion (sans envoyer d'email)
    $api_key = !empty($api_key_unified) ? $api_key_unified : ($api_key_getenv !== false ? $api_key_getenv : $api_key_env);
    $api_key = trim($api_key);
    
    echo "<p><strong>Test de connexion...</strong></p>";
    
    // Tester avec une requête simple (liste des domaines)
    $ch = curl_init('https://api.resend.com/domains');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "<p class='error'>❌ Erreur cURL: " . htmlspecialchars($curl_error) . " (Code: $curl_errno)</p>";
    } else {
        echo "<p><strong>Code HTTP:</strong> $http_code</p>";
        
        if ($http_code >= 200 && $http_code < 300) {
            echo "<p class='success'>✅ Connexion à l'API Resend réussie!</p>";
            $response_data = json_decode($response, true);
            if (isset($response_data['data'])) {
                echo "<p><strong>Domaines vérifiés:</strong> " . count($response_data['data']) . "</p>";
            }
        } elseif ($http_code === 401 || $http_code === 403) {
            echo "<p class='error'>❌ Clé API invalide ou expirée (HTTP $http_code)</p>";
            echo "<p><small>Vérifiez que votre RESEND_API_KEY est correcte sur Render.com</small></p>";
        } else {
            echo "<p class='warning'>⚠️ Réponse inattendue (HTTP $http_code)</p>";
        }
        
        if (!empty($response)) {
            echo "<details><summary>Réponse complète</summary><pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre></details>";
        }
    }
}

echo "</div>";

// Section 4: Test d'envoi d'email (optionnel)
echo "<div class='section'>";
echo "<h2>4. Test d'envoi d'email</h2>";

if (!$is_configured) {
    echo "<p class='error'>❌ Impossible de tester: RESEND_API_KEY non configurée</p>";
} else {
    echo "<p>Pour tester l'envoi d'email, utilisez la fonction <code>send_email_unified()</code> dans votre code.</p>";
    echo "<p><strong>Exemple:</strong></p>";
    echo "<pre>";
    echo "require_once(__DIR__ . '/service/email_config.php');\n";
    echo "\$result = send_email_unified(\n";
    echo "    'test@example.com',\n";
    echo "    'Test User',\n";
    echo "    'Test Resend',\n";
    echo "    '<h1>Test</h1><p>Ceci est un test</p>'\n";
    echo ");\n";
    echo "var_dump(\$result);\n";
    echo "</pre>";
}

echo "</div>";

// Section 5: Recommandations
echo "<div class='section'>";
echo "<h2>5. Recommandations</h2>";

if (!$is_configured) {
    echo "<ul>";
    if ($is_render) {
        echo "<li>✅ Vous êtes sur Render.com</li>";
        echo "<li>❌ <strong>Action requise:</strong> Vérifiez que <code>RESEND_API_KEY</code> est bien définie dans les variables d'environnement du dashboard Render.com</li>";
        echo "<li>📝 Allez dans votre service Render.com → Environment → Ajoutez/modifiez <code>RESEND_API_KEY</code></li>";
        echo "<li>🔄 Redéployez votre service après avoir ajouté la variable</li>";
    } else {
        echo "<li>⚠️ Vous êtes en environnement local</li>";
        echo "<li>📝 Créez un fichier <code>.env</code> à la racine du projet avec <code>RESEND_API_KEY=votre_clé_api</code></li>";
        echo "<li>📖 Consultez <code>ENV_SETUP.md</code> pour plus d'informations</li>";
    }
    echo "</ul>";
} else {
    echo "<ul>";
    echo "<li class='success'>✅ RESEND_API_KEY est configurée</li>";
    echo "<li>📧 Vous pouvez maintenant envoyer des emails via Resend</li>";
    echo "<li>📊 Consultez les logs Render.com pour voir les détails d'envoi</li>";
    echo "</ul>";
}

echo "</div>";

echo "<hr>";
echo "<p><small>Script de diagnostic généré le " . date('Y-m-d H:i:s') . "</small></p>";
?>

