<?php
/**
 * Helper pour générer les URLs correctement selon l'environnement
 * Gère les différences entre environnement local (/gestion/) et production (racine)
 */

/**
 * Génère une URL relative ou absolue selon l'environnement
 * @param string $path Chemin relatif (ex: 'login.php' ou 'module/admin/index.php')
 * @param bool $absolute Si true, retourne une URL absolue avec le domaine
 * @return string URL générée
 */
function url($path, $absolute = false) {
    // Enlever le slash initial si présent
    $path = ltrim($path, '/');
    
    // Détecter si on est en environnement local (WAMP) ou production
    $is_local = (
        strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
        strpos($_SERVER['HTTP_HOST'] ?? '', ':8080') !== false ||
        strpos($_SERVER['HTTP_HOST'] ?? '', ':8095') !== false
    );
    
    // En local, ajouter /gestion/ si nécessaire
    if ($is_local && strpos($path, 'gestion/') === false) {
        $path = 'gestion/' . $path;
    }
    
    // Ajouter le slash initial
    $path = '/' . $path;
    
    // Si URL absolue demandée, ajouter le protocole et le domaine
    if ($absolute) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . $path;
    }
    
    return $path;
}

/**
 * Génère une URL absolue avec le domaine complet
 * @param string $path Chemin relatif
 * @return string URL absolue
 */
function url_absolute($path) {
    return url($path, true);
}

