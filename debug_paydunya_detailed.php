<?php
require_once __DIR__ . '/service/mysqlcon.php';
require_once __DIR__ . '/service/paydunya_service.php';

echo "<h2>Diagnostic PayDunya Détaillé</h2>";

try {
    // Récupérer la configuration brute
    $config = require __DIR__ . '/service/paydunya_env.php';
    
    echo "<h3>1. Configuration des variables d'environnement</h3>";
    echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
    echo "<tr><th>Variable</th><th>Valeur</th><th>Statut</th></tr>";
    
    $env_vars = [
        'APP_URL' => getenv('APP_URL'),
        'PAYDUNYA_MASTER_KEY' => getenv('PAYDUNYA_MASTER_KEY'),
        'PAYDUNYA_PUBLIC_KEY' => getenv('PAYDUNYA_PUBLIC_KEY'),
        'PAYDUNYA_PRIVATE_KEY' => getenv('PAYDUNYA_PRIVATE_KEY'),
        'PAYDUNYA_TOKEN' => getenv('PAYDUNYA_TOKEN')
    ];
    
    foreach ($env_vars as $key => $value) {
        $status = $value ? "✅ Définie" : "❌ Non définie";
        $display_value = $value ? substr($value, 0, 10) . '...' : 'N/A';
        echo "<tr><td>$key</td><td>$display_value</td><td>$status</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>2. Configuration PayDunya</h3>";
    echo "<pre>";
    echo "Mode: " . $config['mode'] . "\n";
    echo "Store Name: " . $config['store']['name'] . "\n";
    echo "Website URL: " . $config['store']['website_url'] . "\n";
    echo "Callback URL: " . $config['store']['callback_url'] . "\n";
    echo "Cancel URL: " . $config['store']['cancel_url'] . "\n";
    echo "Return URL: " . $config['store']['return_url'] . "\n";
    echo "</pre>";
    
    echo "<h3>3. Test de connexion à l'API PayDunya</h3>";
    
    // Test direct avec cURL
    $ch = curl_init('https://app.paydunya.com/api/v1/checkout-invoice/create');
    
    $headers = [
        'PAYDUNYA-MASTER-KEY: ' . $config['api_keys']['master_key'],
        'PAYDUNYA-PUBLIC-KEY: ' . $config['api_keys']['public_key'],
        'PAYDUNYA-PRIVATE-KEY: ' . $config['api_keys']['private_key'],
        'PAYDUNYA-TOKEN: ' . $config['api_keys']['token'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'invoice' => [
            'items' => [[
                'name' => 'Test',
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
    ]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "<p><strong>Code HTTP:</strong> $http_code</p>";
    echo "<p><strong>Erreur cURL:</strong> " . ($curl_error ?: 'Aucune') . "</p>";
    
    if ($response) {
        echo "<p><strong>Réponse brute:</strong></p>";
        echo "<pre style='background: #f0f0f0; padding: 10px;'>";
        echo htmlspecialchars($response);
        echo "</pre>";
        
        $decoded = json_decode($response, true);
        if ($decoded) {
            echo "<p><strong>Réponse décodée:</strong></p>";
            echo "<pre style='background: #f0f0f0; padding: 10px;'>";
            echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT));
            echo "</pre>";
            
            if (isset($decoded['response_code'])) {
                echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107;'>";
                echo "<strong>Code de réponse PayDunya:</strong> " . $decoded['response_code'] . "<br>";
                if (isset($decoded['response_text'])) {
                    echo "<strong>Message:</strong> " . htmlspecialchars($decoded['response_text']);
                }
                echo "</div>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;'>";
    echo "<strong>Erreur:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic PayDunya</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
</body>
</html>
