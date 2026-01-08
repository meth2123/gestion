<?php
/**
 * Test de la page de renouvellement corrigée
 */

echo "<h1>🔧 Test de la Page de Renouvellement Corrigée</h1>";

// Simuler les paramètres GET
$_GET['email'] = 'dmbosse104@gmail.com';

echo "<h2>Test avec email : dmbosse104@gmail.com</h2>";

// Capturer la sortie de la page de renouvellement
ob_start();
include 'module/subscription/renew.php';
$output = ob_get_clean();

// Analyser le résultat
if (strpos($output, 'Aucun abonnement trouvé') !== false) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ ERREUR: Aucun abonnement trouvé";
    echo "</div>";
} else {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ SUCCÈS: Abonnement trouvé";
    echo "</div>";
}

// Vérifier les éléments clés
if (strpos($output, 'meth ndiaye') !== false) {
    echo "<div style='color: blue; background: #e6f3ff; padding: 10px; border-radius: 5px;'>";
    echo "✅ École détectée: meth ndiaye";
    echo "</div>";
}

if (strpos($output, 'dmbosse104@gmail.com') !== false) {
    echo "<div style='color: blue; background: #e6f3ff; padding: 10px; border-radius: 5px;'>";
    echo "✅ Email détecté: dmbosse104@gmail.com";
    echo "</div>";
}

if (strpos($output, 'expired') !== false || strpos($output, 'Expiré') !== false) {
    echo "<div style='color: orange; background: #fff3e6; padding: 10px; border-radius: 5px;'>";
    echo "✅ Statut détecté: expired";
    echo "</div>";
}

if (strpos($output, 'Renouveler') !== false) {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ Bouton de renouvellement trouvé";
    echo "</div>";
}

if (strpos($output, '15000') !== false) {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ Montant affiché: 15 000 FCFA";
    echo "</div>";
}

echo "<h3>Lien de test dans le navigateur :</h3>";
echo "<p><a href='module/subscription/renew.php?email=dmbosse104%40gmail.com' target='_blank' class='btn btn-primary'>";
echo "Tester la page de renouvellement</a></p>";

echo "<h3>Sortie complète (premiers 2000 caractères) :</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 400px; overflow-y: auto;'>";
echo htmlspecialchars(substr($output, 0, 2000));
echo "</pre>";
?>
