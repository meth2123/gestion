<?php
// Sitemap dynamique (pages publiques)
header('Content-Type: application/xml; charset=UTF-8');

$base_url = getenv('APP_URL');
if (!$base_url) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = $scheme . '://' . $host;
}
$base_url = rtrim($base_url, '/');

$paths = [];
$paths[] = '/';

$root_files = ['index.php', 'login.php'];
foreach ($root_files as $file) {
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $file)) {
        $paths[] = '/' . $file;
    }
}

$doc_dir = __DIR__ . DIRECTORY_SEPARATOR . 'documentation';
if (is_dir($doc_dir)) {
    foreach (glob($doc_dir . DIRECTORY_SEPARATOR . '*.php') as $file) {
        $name = basename($file);
        if (preg_match('/index_debug|\.bak|\.fixed|test/i', $name)) {
            continue;
        }
        $paths[] = '/documentation/' . $name;
    }
}

$sub_dir = __DIR__ . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'subscription';
if (is_dir($sub_dir)) {
    foreach (glob($sub_dir . DIRECTORY_SEPARATOR . '*.php') as $file) {
        $name = basename($file);
        if (preg_match('/\.bak|\.fixed|test/i', $name)) {
            continue;
        }
        $paths[] = '/module/subscription/' . $name;
    }
}

if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'module' . DIRECTORY_SEPARATOR . 'help_center.php')) {
    $paths[] = '/module/help_center.php';
}
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'help_center.php')) {
    $paths[] = '/help_center.php';
}

$paths = array_values(array_unique($paths));

$xml = [];
$xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
$xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($paths as $p) {
    $loc = $base_url . $p;
    $xml[] = '  <url>';
    $xml[] = '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . '</loc>';
    $xml[] = '    <changefreq>weekly</changefreq>';
    $xml[] = '    <priority>0.6</priority>';
    $xml[] = '  </url>';
}
$xml[] = '</urlset>';

echo implode("\n", $xml);
