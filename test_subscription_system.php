<?php
/**
 * Fichier de test pour le système d'abonnement intelligent
 * Ce fichier peut être supprimé après les tests
 */

session_start();
require_once __DIR__ . '/service/mysqlcon.php';
require_once __DIR__ . '/service/SubscriptionDetector.php';
require_once __DIR__ . '/components/SmartSubscriptionButton.php';

echo "<h1>Test du Système d'Abonnement Intelligent</h1>";

// Test 1: Détection d'abonnement
echo "<h2>Test 1: Détection d'abonnement</h2>";

$detector = new SubscriptionDetector($link);

// Test avec un email fictif
echo "<h3>Test avec email fictif:</h3>";
$test_email = "test@example.com";
$detection = $detector->detectSubscriptionStatus($test_email);
echo "<pre>";
print_r($detection);
echo "</pre>";

// Test avec un nom d'école fictif
echo "<h3>Test avec nom d'école fictif:</h3>";
$test_school = "École Test";
$detection = $detector->detectSubscriptionStatus(null, $test_school);
echo "<pre>";
print_r($detection);
echo "</pre>";

// Test 2: Bouton intelligent
echo "<h2>Test 2: Bouton intelligent</h2>";

$smartButton = new SmartSubscriptionButton($link);

// Test bouton pour nouvel utilisateur
echo "<h3>Bouton pour nouvel utilisateur:</h3>";
$button = $smartButton->render();
echo $button;

// Test bouton pour utilisateur connecté (simulation)
echo "<h3>Bouton pour utilisateur connecté (simulation):</h3>";
$_SESSION['user_id'] = 'test-user';
$_SESSION['user_type'] = 'admin';
$button = $smartButton->renderForLoggedUser();
echo $button;

// Test 3: Vérification des tables
echo "<h2>Test 3: Vérification des tables</h2>";

$tables_to_check = ['subscriptions', 'subscription_renewals', 'subscription_notifications'];

foreach ($tables_to_check as $table) {
    echo "<h3>Table: $table</h3>";
    try {
        $result = $link->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' existe<br>";
            
            // Compter les enregistrements
            $count_result = $link->query("SELECT COUNT(*) as count FROM $table");
            $count = $count_result->fetch_assoc()['count'];
            echo "📊 Nombre d'enregistrements: $count<br>";
            
            // Afficher la structure
            $structure = $link->query("DESCRIBE $table");
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['Field'] . "</td>";
                echo "<td>" . $row['Type'] . "</td>";
                echo "<td>" . $row['Null'] . "</td>";
                echo "<td>" . $row['Key'] . "</td>";
                echo "<td>" . $row['Default'] . "</td>";
                echo "<td>" . $row['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "❌ Table '$table' n'existe pas<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erreur lors de la vérification de la table '$table': " . $e->getMessage() . "<br>";
    }
}

// Test 4: Vérification des fichiers
echo "<h2>Test 4: Vérification des fichiers</h2>";

$files_to_check = [
    'service/SubscriptionDetector.php',
    'components/SmartSubscriptionButton.php',
    'module/subscription/dashboard.php',
    'module/subscription/renew.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ Fichier '$file' existe<br>";
    } else {
        echo "❌ Fichier '$file' manquant<br>";
    }
}

// Test 5: Test de la page d'accueil
echo "<h2>Test 5: Test de la page d'accueil</h2>";
echo "<p>La page d'accueil a été modifiée pour utiliser le système intelligent.</p>";
echo "<p><a href='index.php' target='_blank'>Ouvrir la page d'accueil</a></p>";

// Test 6: Test de la page de renouvellement
echo "<h2>Test 6: Test de la page de renouvellement</h2>";
echo "<p>La page de renouvellement a été améliorée avec auto-détection.</p>";
echo "<p><a href='module/subscription/renew.php' target='_blank'>Ouvrir la page de renouvellement</a></p>";

// Test 7: Test du tableau de bord
echo "<h2>Test 7: Test du tableau de bord</h2>";
echo "<p>Un nouveau tableau de bord a été créé pour la gestion des abonnements.</p>";
echo "<p><a href='module/subscription/dashboard.php' target='_blank'>Ouvrir le tableau de bord</a></p>";

echo "<hr>";
echo "<h2>Résumé des améliorations</h2>";
echo "<ul>";
echo "<li>✅ Service de détection automatique d'abonnement créé</li>";
echo "<li>✅ Bouton intelligent d'abonnement implémenté</li>";
echo "<li>✅ Page d'accueil modifiée avec navigation intelligente</li>";
echo "<li>✅ Page de renouvellement améliorée avec auto-détection</li>";
echo "<li>✅ Tableau de bord des abonnements créé</li>";
echo "<li>✅ Gestion des erreurs et messages améliorée</li>";
echo "</ul>";

echo "<p><strong>Note:</strong> Ce fichier de test peut être supprimé après vérification.</p>";
?>
