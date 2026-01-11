<?php

require __DIR__ . '/vendor/autoload.php';

use Macwinnie\WpDbPhinxHelper\DBUtilisator;

$wp_main_path = DBUtilisator::get_wp_main_path();
require implode( DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-load.php' ] );

return DBUtilisator::get_phinx_config();
