<?php
// SEO global (meta tags communs)
$seo_base_url = getenv('APP_URL');
if (!$seo_base_url) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $seo_base_url = $scheme . '://' . $host;
}

$seo_title = $page_title ?? 'SchoolManager - Système de Gestion Scolaire';
$seo_description = $page_description ?? 'Plateforme SchoolManager pour la gestion scolaire : inscriptions, présences, bulletins, paiements, emplois du temps, messagerie et suivi des élèves.';
$seo_url = $page_url ?? rtrim($seo_base_url, '/') . ($_SERVER['REQUEST_URI'] ?? '/');
$seo_image = $page_image ?? rtrim($seo_base_url, '/') . '/source/logo.jpg';
$seo_robots = $robots ?? 'index, follow';
$include_google_verification = $include_google_verification ?? false;

$seo_title_esc = htmlspecialchars($seo_title, ENT_QUOTES, 'UTF-8');
$seo_description_esc = htmlspecialchars($seo_description, ENT_QUOTES, 'UTF-8');
$seo_url_esc = htmlspecialchars($seo_url, ENT_QUOTES, 'UTF-8');
$seo_image_esc = htmlspecialchars($seo_image, ENT_QUOTES, 'UTF-8');
$seo_robots_esc = htmlspecialchars($seo_robots, ENT_QUOTES, 'UTF-8');
?>
<meta name="description" content="<?php echo $seo_description_esc; ?>">
<meta name="robots" content="<?php echo $seo_robots_esc; ?>">
<link rel="canonical" href="<?php echo $seo_url_esc; ?>">

<meta property="og:title" content="<?php echo $seo_title_esc; ?>">
<meta property="og:description" content="<?php echo $seo_description_esc; ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo $seo_url_esc; ?>">
<meta property="og:image" content="<?php echo $seo_image_esc; ?>">
<meta property="og:site_name" content="SchoolManager">
<meta property="og:locale" content="fr_FR">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo $seo_title_esc; ?>">
<meta name="twitter:description" content="<?php echo $seo_description_esc; ?>">
<meta name="twitter:image" content="<?php echo $seo_image_esc; ?>">

<?php if ($include_google_verification): ?>
<meta name="google-site-verification" content="ZK6ZLI269e46n8Bv-yWiW7JdX_ctad7bvs_9z4bkKaE">
<?php endif; ?>
