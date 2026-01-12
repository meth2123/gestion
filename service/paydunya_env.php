<?php
// Configuration de l'environnement PayDunya pour Render.com
// Les variables d'environnement peuvent être configurées dans le dashboard Render.com
// sous Settings > Environment Variables

// Fonction helper pour obtenir les variables d'environnement
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = null) {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        return $value !== false ? $value : $default;
    }
}

// Récupération des variables d'environnement si définies sur Render.com
$base_url_raw = getEnvVar('APP_URL', 'https://gestion-rlhq.onrender.com');
// Nettoyer l'URL : enlever le slash final s'il existe
$base_url = rtrim($base_url_raw, '/');

$master_key = getEnvVar('PAYDUNYA_MASTER_KEY', 'J8Bk1t8t-AWZp-kVD1-WbjB-CndDy4hrVS7J');
$public_key = getEnvVar('PAYDUNYA_PUBLIC_KEY', 'test_public_9zzBrzEfagNrSYsVi3I3nreNKXV');
$private_key = getEnvVar('PAYDUNYA_PRIVATE_KEY', 'test_private_0WuP5er1GGbqeJggPclXAyWcKad');
$token = getEnvVar('PAYDUNYA_TOKEN', 'IeXty0flMeb4AfmTtkR7');

// Définir les URLs de callback
$callback_url = $base_url . '/module/subscription/callback.php';
$cancel_url = $base_url . '/module/subscription/cancel.php';
$return_url = $base_url . '/module/subscription/success.php';

return [
    'mode' => 'live', // Mode production
    'store' => [
        'name' => 'SchoolManager',
        'tagline' => 'Système de Gestion Scolaire',
        'postal_address' => 'Dakar, Sénégal',
        'phone_number' => '+221 77 807 25 70',
        'website_url' => $base_url,
        'logo_url' => $base_url . '/source/logo.jpg',
        'callback_url' => $callback_url,
        'cancel_url' => $cancel_url,
        'return_url' => $return_url
    ],
    'api_keys' => [
        'master_key' => $master_key,
        'public_key' => $public_key,
        'private_key' => $private_key,
        'token' => $token
    ],
    'payment_methods' => [
        'orange-money' => true,
        'wave' => true,
        'visa' => true,
        'mastercard' => true
    ],
    'subscription' => [
        'amount' => 15000.00, // 15 000 FCFA
        'description' => 'Abonnement mensuel à SchoolManager - Système de Gestion Scolaire'
    ]
];

// Log de la configuration
error_log("=== Configuration PayDunya ===");
error_log("Mode: Production");
error_log("APP_URL (raw): " . $base_url_raw);
error_log("Base URL (nettoyée): " . $base_url);
error_log("Callback URL: " . $callback_url);
error_log("Cancel URL: " . $cancel_url);
error_log("Return URL: " . $return_url);
error_log("Website URL: " . $base_url);
error_log("Logo URL: " . $base_url . '/source/logo.jpg');

// Vérification de la sécurité
if (strpos($base_url, 'https://') !== 0) {
    error_log("⚠️ ATTENTION: L'URL de base doit utiliser HTTPS pour PayDunya");
    error_log("   URL actuelle: " . $base_url);
}

// Vérifier que l'URL n'est pas localhost
if (strpos($base_url, 'localhost') !== false || strpos($base_url, '127.0.0.1') !== false) {
    error_log("⚠️ ATTENTION: L'URL ne peut pas être localhost pour PayDunya en production");
    error_log("   Utilisez une URL publique comme https://gestion-rlhq.onrender.com");
} 