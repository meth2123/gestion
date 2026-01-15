<?php
require_once __DIR__ . '/../service/email_config.php';

header('Content-Type: application/json');

try {
    echo "<h2>Test Configuration Brevo</h2>";
    
    // Afficher les variables d'environnement
    echo "<h3>Variables d'environnement</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Variable</th><th>Statut</th><th>Valeur (tronquée)</th></tr>";
    
    $env_vars = [
        'BREVO_API_KEY' => getenv('BREVO_API_KEY'),
        'BREVO_EMAIL' => getenv('BREVO_EMAIL'),
        'BREVO_NAME' => getenv('BREVO_NAME')
    ];
    
    foreach ($env_vars as $key => $value) {
        $status = $value ? "✅ Définie" : "❌ Non définie";
        $display_value = $value ? substr($value, 0, 15) . '...' : 'N/A';
        echo "<tr><td>$key</td><td>$status</td><td>$display_value</td></tr>";
    }
    echo "</table>";
    
    // Test de connexion
    echo "<h3>Test de connexion à Brevo</h3>";
    $test_result = test_brevo_config();
    
    if ($test_result['success']) {
        echo "<div style='color: green;'>";
        echo "<p>✅ " . htmlspecialchars($test_result['message']) . "</p>";
        if (isset($test_result['account_info'])) {
            echo "<pre>";
            echo "Email: " . htmlspecialchars($test_result['account_info']['email']) . "\n";
            echo "Crédits: " . htmlspecialchars($test_result['account_info']['credits']) . "\n";
            echo "</pre>";
        }
        echo "</div>";
        
        // Test d'envoi d'email
        echo "<h3>Test d'envoi d'email</h3>";
        $test_email = 'test@example.com'; // Remplacez par votre email pour tester
        
        $html_content = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { padding: 20px; background: #f8f9fa; }
                .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Test Brevo</h1>
                </div>
                <p>Ceci est un email de test pour vérifier que Brevo fonctionne correctement.</p>
                <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
        </body>
        </html>";
        
        $email_result = send_email_unified(
            $test_email,
            'Test User',
            'Test Email - SchoolManager Brevo',
            $html_content,
            'Ceci est un email de test pour vérifier que Brevo fonctionne correctement.'
        );
        
        if ($email_result['success']) {
            echo "<div style='color: green;'>";
            echo "<p>✅ Email de test envoyé avec succès à $test_email</p>";
            echo "<p>" . htmlspecialchars($email_result['message']) . "</p>";
            echo "</div>";
        } else {
            echo "<div style='color: red;'>";
            echo "<p>❌ Échec de l'envoi de l'email de test</p>";
            echo "<p>" . htmlspecialchars($email_result['message']) . "</p>";
            echo "</div>";
        }
        
    } else {
        echo "<div style='color: red;'>";
        echo "<p>❌ " . htmlspecialchars($test_result['message']) . "</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px;'>";
    echo "<strong>Erreur:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Brevo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        pre { background: #f5f5f5; padding: 10px; }
    </style>
</head>
<body>
</body>
</html>
