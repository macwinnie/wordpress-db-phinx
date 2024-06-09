<?php

require __DIR__ . '/vendor/autoload.php';

use Macwinnie\WpDbPhinxHelper\DBUtilisator;

$wp_main_path = DBUtilisator::get_wp_main_path();
require implode( DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-load.php' ] );

$obj = new DBUtilisator();
return $obj->get_phinx_config();
