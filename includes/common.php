<?php
// Fonction pour charger les dépendances communes
function loadCommonDependencies() {
    require_once dirname(dirname(__FILE__)) . '/config/paths.php';
    require_once MYSQLCON_PATH;
    require_once CONFIG_PATH;
}
?>
