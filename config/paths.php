<?php
// Définir les chemins de base
define('BASE_PATH', dirname(dirname(__FILE__)));
define('SERVICE_PATH', BASE_PATH . '/service');
define('DB_PATH', BASE_PATH . '/db');
define('MODULE_PATH', BASE_PATH . '/module');

define('MYSQLCON_PATH', SERVICE_PATH . '/mysqlcon.php');
define('CONFIG_PATH', DB_PATH . '/config.php');
?>
