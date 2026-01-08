<?php
/**
 * Script de test pour vérifier l'accès aux pages et à PHPMailer
 */

echo "<h1>Test d'accès - SchoolManager</h1>";

// Test 1: Vérifier PHPMailer
echo "<h2>1. Test PHPMailer</h2>";
$vendor_path = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendor_path)) {
    echo "<p style='color: green;'>✓ vendor/autoload.php existe</p>";
    require_once $vendor_path;
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "<p style='color: green;'>✓ PHPMailer est chargé correctement</p>";
    } else {
        echo "<p style='color: red;'>✗ PHPMailer n'est pas disponible</p>";
    }
} else {
    echo "<p style='color: red;'>✗ vendor/autoload.php n'existe pas</p>";
    echo "<p>Chemin attendu: $vendor_path</p>";
}

// Test 2: Vérifier les fichiers de pages
echo "<h2>2. Test des fichiers de pages</h2>";

$pages = [
    'Documentation' => __DIR__ . '/documentation/index.php',
    'Abonnement' => __DIR__ . '/module/subscription/register.php',
    'Login' => __DIR__ . '/login.php',
    'Index' => __DIR__ . '/index.php'
];

foreach ($pages as $name => $path) {
    if (file_exists($path)) {
        echo "<p style='color: green;'>✓ $name existe: $path</p>";
    } else {
        echo "<p style='color: red;'>✗ $name n'existe pas: $path</p>";
    }
}

// Test 3: Vérifier les permissions
echo "<h2>3. Test des permissions</h2>";
$test_dir = __DIR__;
echo "<p>Répertoire actuel: $test_dir</p>";
echo "<p>Permissions: " . substr(sprintf('%o', fileperms($test_dir)), -4) . "</p>";
echo "<p>Propriétaire: " . posix_getpwuid(fileowner($test_dir))['name'] . "</p>";

// Test 4: Vérifier Apache mod_rewrite
echo "<h2>4. Test Apache</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p style='color: green;'>✓ mod_rewrite est activé</p>";
    } else {
        echo "<p style='color: orange;'>⚠ mod_rewrite n'est pas activé</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ Impossible de vérifier les modules Apache</p>";
}

// Test 5: Vérifier .htaccess
echo "<h2>5. Test .htaccess</h2>";
$htaccess_path = __DIR__ . '/.htaccess';
if (file_exists($htaccess_path)) {
    echo "<p style='color: green;'>✓ .htaccess existe</p>";
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccess_path)) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ .htaccess n'existe pas</p>";
}

// Test 6: URLs de test
echo "<h2>6. URLs de test</h2>";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
            '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
echo "<p>URL de base: <a href='$base_url'>$base_url</a></p>";
echo "<ul>";
echo "<li><a href='$base_url/documentation/index.php'>Documentation</a></li>";
echo "<li><a href='$base_url/module/subscription/register.php'>Abonnement</a></li>";
echo "<li><a href='$base_url/login.php'>Login</a></li>";
echo "<li><a href='$base_url/index.php'>Index</a></li>";
echo "</ul>";

echo "<hr>";
echo "<p><small>Script de test généré le " . date('Y-m-d H:i:s') . "</small></p>";
?>

