<?php
/**
 * Test de la durée de renouvellement (1 mois)
 */

echo "<h1>📅 Test de la Durée de Renouvellement</h1>";

// Simuler la date d'expiration après renouvellement
$current_date = date('Y-m-d H:i:s');
$new_expiry_date = date('Y-m-d H:i:s', strtotime('+1 month'));

echo "<h2>Calcul de la nouvelle date d'expiration :</h2>";
echo "<p><strong>Date actuelle :</strong> " . $current_date . "</p>";
echo "<p><strong>Nouvelle date d'expiration :</strong> " . $new_expiry_date . "</p>";

$days_added = (strtotime($new_expiry_date) - strtotime($current_date)) / (60 * 60 * 24);
echo "<p><strong>Jours ajoutés :</strong> " . round($days_added) . " jours</p>";

if ($days_added >= 28 && $days_added <= 31) {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ Durée correcte : 1 mois";
    echo "</div>";
} else {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ Durée incorrecte : " . round($days_added) . " jours";
    echo "</div>";
}

echo "<h2>Exemples de dates d'expiration :</h2>";
echo "<ul>";
for ($i = 1; $i <= 12; $i++) {
    $test_date = date('Y-m-d H:i:s', strtotime("+{$i} month"));
    echo "<li>Renouvellement {$i} : {$test_date}</li>";
}
echo "</ul>";

echo "<h2>Test avec différentes dates de base :</h2>";
$test_dates = [
    '2025-01-15 10:00:00',
    '2025-02-28 10:00:00', // Année bissextile
    '2025-03-31 10:00:00',
    '2025-04-30 10:00:00'
];

foreach ($test_dates as $test_date) {
    $expiry = date('Y-m-d H:i:s', strtotime($test_date . ' +1 month'));
    echo "<p><strong>Base :</strong> {$test_date} → <strong>Expire :</strong> {$expiry}</p>";
}
?>

