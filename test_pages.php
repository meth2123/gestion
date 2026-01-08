<?php
/**
 * Script de test pour vérifier l'accès aux pages documentation et abonnement
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test d'accès aux pages - SchoolManager</h1>";

// Test 1: Vérifier les fichiers
echo "<h2>1. Vérification des fichiers</h2>";

$files_to_check = [
    'Documentation' => [
        'path' => __DIR__ . '/documentation/index.php',
        'required' => __DIR__ . '/service/db_utils.php'
    ],
    'Abonnement' => [
        'path' => __DIR__ . '/module/subscription/register.php',
        'required' => [
            __DIR__ . '/service/paydunya_service.php',
            __DIR__ . '/service/paydunya_env.php',
            __DIR__ . '/service/db_utils.php'
        ]
    ]
];

foreach ($files_to_check as $name => $info) {
    echo "<h3>$name</h3>";
    
    if (file_exists($info['path'])) {
        echo "<p style='color: green;'>✓ Fichier principal existe: " . basename($info['path']) . "</p>";
        
        // Vérifier les fichiers requis
        $required = is_array($info['required']) ? $info['required'] : [$info['required']];
        foreach ($required as $req_file) {
            if (file_exists($req_file)) {
                echo "<p style='color: green;'>✓ Fichier requis existe: " . basename($req_file) . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Fichier requis manquant: " . basename($req_file) . "</p>";
                echo "<p>Chemin attendu: $req_file</p>";
            }
        }
        
        // Tester si le fichier peut être inclus
        try {
            ob_start();
            $old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
            
            // Simuler l'inclusion pour voir les erreurs
            $content = file_get_contents($info['path']);
            if (strpos($content, 'require_once') !== false || strpos($content, 'include_once') !== false) {
                echo "<p style='color: orange;'>⚠ Le fichier contient des require/include - vérifiez les chemins</p>";
            }
            
            restore_error_handler();
            ob_end_clean();
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Erreur lors de la lecture: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Fichier principal n'existe pas: " . basename($info['path']) . "</p>";
        echo "<p>Chemin attendu: {$info['path']}</p>";
    }
}

// Test 2: Vérifier les chemins relatifs
echo "<h2>2. Test des chemins relatifs</h2>";

$base_dir = __DIR__;
echo "<p>Répertoire de base: $base_dir</p>";

$relative_paths = [
    'documentation/index.php' => '../service/db_utils.php',
    'module/subscription/register.php' => '../../service/db_utils.php'
];

foreach ($relative_paths as $page => $relative_path) {
    $page_dir = dirname($base_dir . '/' . $page);
    $resolved_path = realpath($page_dir . '/' . $relative_path);
    
    echo "<h3>$page</h3>";
    echo "<p>Répertoire de la page: $page_dir</p>";
    echo "<p>Chemin relatif: $relative_path</p>";
    
    if ($resolved_path && file_exists($resolved_path)) {
        echo "<p style='color: green;'>✓ Chemin résolu correctement: $resolved_path</p>";
    } else {
        echo "<p style='color: red;'>✗ Chemin ne peut pas être résolu</p>";
        echo "<p>Chemin tenté: " . $page_dir . '/' . $relative_path . "</p>";
    }
}

// Test 3: URLs de test
echo "<h2>3. URLs de test</h2>";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
            '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
echo "<p>URL de base: <a href='$base_url'>$base_url</a></p>";
echo "<ul>";
echo "<li><a href='$base_url/documentation/index.php' target='_blank'>Documentation</a></li>";
echo "<li><a href='$base_url/module/subscription/register.php' target='_blank'>Abonnement</a></li>";
echo "</ul>";

// Test 4: Vérifier les permissions
echo "<h2>4. Permissions</h2>";
$dirs_to_check = [
    'documentation' => __DIR__ . '/documentation',
    'module/subscription' => __DIR__ . '/module/subscription'
];

foreach ($dirs_to_check as $name => $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $readable = is_readable($dir) ? '✓' : '✗';
        $writable = is_writable($dir) ? '✓' : '✗';
        echo "<p>$name: Permissions=$perms, Lisible=$readable, Écriture=$writable</p>";
    } else {
        echo "<p style='color: red;'>✗ $name: Le répertoire n'existe pas</p>";
    }
}

echo "<hr>";
echo "<p><small>Script de test généré le " . date('Y-m-d H:i:s') . "</small></p>";
?>

