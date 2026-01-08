<?php
// Définir le chemin absolu du dossier racine
define('ROOT_PATH', realpath(dirname(__FILE__))); // Utiliser realpath pour un chemin absolu

define('SERVICE_PATH', ROOT_PATH . '/service');
define('DB_PATH', ROOT_PATH . '/db');
define('MODULE_PATH', ROOT_PATH . '/module');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

define('MYSQLCON_PATH', SERVICE_PATH . '/mysqlcon.php');
define('CONFIG_PATH', DB_PATH . '/config.php');

define('DB_CONFIG_PATH', CONFIG_PATH);
?>
