<?php
require_once __DIR__ . '/../../service/mysqlcon.php';
require_once __DIR__ . '/../../service/paydunya_service.php';

// Fonction pour afficher les informations de manière formatée
function displayInfo($title, $data) {
    echo "<h3>$title</h3>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    echo "<hr>";
}

try {
<<<<<<< C:\wamp64\www\gestion\module\subscription\test_paydunya.php
=======
    // Afficher les informations de configuration d'environnement
    echo "<h2>Variables d'Environnement</h2>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Variable</th><th>Statut</th><th>Valeur (tronquée)</th></tr>";
    
    $env_vars = [
        'APP_URL' => getenv('APP_URL'),
        'PAYDUNYA_MASTER_KEY' => getenv('PAYDUNYA_MASTER_KEY'),
        'PAYDUNYA_PUBLIC_KEY' => getenv('PAYDUNYA_PUBLIC_KEY'),
        'PAYDUNYA_PRIVATE_KEY' => getenv('PAYDUNYA_PRIVATE_KEY'),
        'PAYDUNYA_TOKEN' => getenv('PAYDUNYA_TOKEN')
    ];
    
    foreach ($env_vars as $key => $value) {
        $status = $value ? "✅ Définie" : "❌ Non définie";
        $display_value = $value ? substr($value, 0, 15) . '...' : 'N/A';
        echo "<tr><td>$key</td><td>$status</td><td>$display_value</td></tr>";
    }
    echo "</table>";
    
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-df691a30\module\subscription\test_paydunya.php
    // Initialiser le service PayDunya
    $paydunya = new PayDunyaService($link);
    
    // Afficher les informations de configuration
    echo "<h2>Configuration PayDunya</h2>";
    echo "<p>Mode: " . ($paydunya->getMode() === 'test' ? 'Test' : 'Production') . "</p>";
    
<<<<<<< C:\wamp64\www\gestion\module\subscription\test_paydunya.php
=======
    // Afficher la configuration brute
    $config = require __DIR__ . '/../../service/paydunya_env.php';
    echo "<h3>Détails de la configuration</h3>";
    echo "<pre>";
    echo "Website URL: " . $config['store']['website_url'] . "\n";
    echo "Callback URL: " . $config['store']['callback_url'] . "\n";
    echo "Master Key: " . substr($config['api_keys']['master_key'], 0, 15) . "...\n";
    echo "Public Key: " . substr($config['api_keys']['public_key'], 0, 15) . "...\n";
    echo "Private Key: " . substr($config['api_keys']['private_key'], 0, 15) . "...\n";
    echo "Token: " . substr($config['api_keys']['token'], 0, 15) . "...\n";
    echo "</pre>";
    
>>>>>>> c:\Users\DELL\.windsurf\worktrees\gestion\gestion-df691a30\module\subscription\test_paydunya.php
    // Afficher les méthodes de paiement disponibles
    echo "<h3>Méthodes de Paiement</h3>";
    echo "<ul>";
    foreach ($paydunya->getPaymentMethods() as $method) {
        echo "<li>" . ucfirst(str_replace('-', ' ', $method)) . "</li>";
    }
    echo "</ul>";
    
    // Afficher les informations d'abonnement
    echo "<h3>Informations d'Abonnement</h3>";
    echo "<p>Montant: " . number_format($paydunya->getSubscriptionAmount(), 0, ',', ' ') . " FCFA</p>";
    echo "<p>Description: " . $paydunya->getSubscriptionDescription() . "</p>";
    
    // Créer un abonnement de test
    $test_subscription = [
        'id' => 1,
        'school_name' => 'École Test',
        'admin_email' => 'test@example.com',
        'admin_phone' => '+221XXXXXXXXX'
    ];
    
    try {
        // Tenter de créer un paiement
        echo "<h3>Test de Création de Paiement</h3>";
        $result = $paydunya->createPayment($test_subscription);
        
        if ($result['success']) {
            echo "<div style='color: green;'>";
            echo "<p>✅ Paiement créé avec succès!</p>";
            echo "<p>Token: " . htmlspecialchars($result['token']) . "</p>";
            echo "<p>URL de paiement: <a href='" . htmlspecialchars($result['invoice_url']) . "' target='_blank'>Cliquer ici pour payer</a></p>";
            echo "</div>";
        } else {
            echo "<div style='color: red;'>";
            echo "<p>❌ Erreur lors de la création du paiement</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='color: red;'>";
        echo "<p>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0;'>";
    echo "❌ Erreur critique : " . $e->getMessage();
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Configuration PayDunya</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            background-color: #f8f9fa;
        }
        h2 {
            color: #4F46E5;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 10px;
        }
        h3 {
            color: #2d3748;
            margin-top: 20px;
        }
        pre {
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Le contenu PHP sera affiché ici -->
</body>
</html> 