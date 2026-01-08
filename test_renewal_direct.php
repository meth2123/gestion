<?php
/**
 * Test direct de la page de renouvellement avec simulation
 */

echo "<h1>Test direct de la page de renouvellement</h1>";

// Simuler les paramètres GET
$_GET['email'] = 'dmbosse104@gmail.com';

echo "<h2>Simulation de l'accès à la page de renouvellement</h2>";

// Capturer la sortie de la page de renouvellement
ob_start();
include 'module/subscription/renew.php';
$output = ob_get_clean();

// Extraire les messages d'erreur et de succès
if (strpos($output, 'Aucun abonnement trouvé') !== false) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ ERREUR: Aucun abonnement trouvé";
    echo "</div>";
} else {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ SUCCÈS: Abonnement trouvé";
    echo "</div>";
}

// Afficher les informations importantes
if (strpos($output, 'meth ndiaye') !== false) {
    echo "<div style='color: blue; background: #e6f3ff; padding: 10px; border-radius: 5px;'>";
    echo "✅ École détectée: meth ndiaye";
    echo "</div>";
}

if (strpos($output, 'expired') !== false) {
    echo "<div style='color: orange; background: #fff3e6; padding: 10px; border-radius: 5px;'>";
    echo "✅ Statut détecté: expired";
    echo "</div>";
}

if (strpos($output, 'Renouveler') !== false) {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ Bouton de renouvellement trouvé";
    echo "</div>";
}

echo "<h3>Lien de test :</h3>";
echo "<p><a href='module/subscription/renew.php?email=dmbosse104%40gmail.com' target='_blank' class='btn btn-primary'>";
echo "Tester la page de renouvellement dans le navigateur</a></p>";

echo "<h3>Sortie complète (premiers 1000 caractères) :</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
echo htmlspecialchars(substr($output, 0, 1000));
echo "</pre>";
?>

