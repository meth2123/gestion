<?php
require_once __DIR__ . '/../../service/mysqlcon.php';
require_once __DIR__ . '/../../service/paydunya_service.php';

header('Content-Type: application/json');

try {
    // Récupérer la configuration brute
    $config = require __DIR__ . '/../../service/paydunya_env.php';
    
    $diagnostic = [
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => [
            'app_url' => getenv('APP_URL') ?: 'non définie',
            'master_key_set' => !empty(getenv('PAYDUNYA_MASTER_KEY')),
            'public_key_set' => !empty(getenv('PAYDUNYA_PUBLIC_KEY')),
            'private_key_set' => !empty(getenv('PAYDUNYA_PRIVATE_KEY')),
            'token_set' => !empty(getenv('PAYDUNYA_TOKEN'))
        ],
        'config' => [
            'mode' => $config['mode'],
            'store_name' => $config['store']['name'],
            'website_url' => $config['store']['website_url'],
            'callback_url' => $config['store']['callback_url']
        ],
        'api_keys' => [
            'master_key_prefix' => substr($config['api_keys']['master_key'], 0, 8) . '...',
            'public_key_prefix' => substr($config['api_keys']['public_key'], 0, 8) . '...',
            'private_key_prefix' => substr($config['api_keys']['private_key'], 0, 8) . '...',
            'token_prefix' => substr($config['api_keys']['token'], 0, 8) . '...'
        ]
    ];
    
    // Test de connexion API
    $ch = curl_init('https://app.paydunya.com/api/v1/checkout-invoice/create');
    
    $headers = [
        'PAYDUNYA-MASTER-KEY: ' . $config['api_keys']['master_key'],
        'PAYDUNYA-PUBLIC-KEY: ' . $config['api_keys']['public_key'],
        'PAYDUNYA-PRIVATE-KEY: ' . $config['api_keys']['private_key'],
        'PAYDUNYA-TOKEN: ' . $config['api_keys']['token'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $test_payload = [
        'invoice' => [
            'items' => [[
                'name' => 'Test Diagnostic',
                'quantity' => 1,
                'unit_price' => 100,
                'total_price' => 100,
                'description' => 'Test item'
            ]],
            'total_amount' => 100,
            'description' => 'Test invoice'
        ],
        'store' => [
            'name' => $config['store']['name'],
            'tagline' => $config['store']['tagline'],
            'postal_address' => $config['store']['postal_address'],
            'phone' => $config['store']['phone_number'],
            'website_url' => $config['store']['website_url']
        ],
        'actions' => [
            'callback_url' => $config['store']['callback_url'],
            'cancel_url' => $config['store']['cancel_url'],
            'return_url' => $config['store']['return_url']
        ]
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $diagnostic['api_test'] = [
        'http_code' => $http_code,
        'curl_error' => $curl_error ?: 'Aucune',
        'response' => $response ? json_decode($response, true) : null,
        'raw_response' => substr($response, 0, 500) // Limiter pour éviter les réponses trop longues
    ];
    
    echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
