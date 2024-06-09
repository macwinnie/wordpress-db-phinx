<?php

namespace Macwinnie\WpDbPhinxHelper;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class DBUtilisator {

    protected static $scriptname = 'phinx.php';

    public function __construct() {
        $base = realpath( static::basePath() );

        if ( ! file_exists( implode(DIRECTORY_SEPARATOR, [$base, static::$scriptname]) ) ) {
            static::setup();
        }

        global $wpdb;

        $dbhost_parts = explode(':', $wpdb->dbhost);
        if (count($dbhost_parts) == 1) {
            $dbhost_parts[] = "3306";
        }

        $this->phinx_config = [
            'paths' => [
                'migrations' => implode( DIRECTORY_SEPARATOR, [ $base, 'db', 'migrations' ] ),
                'seeds' => implode( DIRECTORY_SEPARATOR, [ $base, 'db', 'seeds' ] ),
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog', # change to env variable?
                'default_environment' => 'wordpress',
                'wordpress' => [
                    'adapter' => 'mysql',
                    'host' => $dbhost_parts[0],
                    'name' => $wpdb->dbname,
                    'user' => $wpdb->dbuser,
                    'pass' => $wpdb->dbpassword,
                    'port' => $dbhost_parts[1],
                    'charset' => $wpdb->charset,
                    'collation' => $wpdb->collate,
                    'table_prefix' => $wpdb->prefix,
                ]
            ],
            'version_order' => 'creation' # change to env variable?
        ];
    }

    public function get_phinx_config() {
        return $this->phinx_config;
    }

    public function db_migrate() {
        $phinx = new Manager(
            new Config($this->phinx_config),
            new StringInput(' '),
            new NullOutput()
        );

        // Run any migrations.
        $phinx->migrate('wordpress');
    }

    public static function get_wp_main_path() {
        $wp_main_path = realpath( __DIR__ );
        while (
            ! file_exists( implode( DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-admin' ] ) ) and
            ! file_exists( implode( DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-content' ] ) ) and
            ! file_exists( implode( DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-load.php' ] ) )
        ) {
            $wp_main_path = realpath( dirname( $wp_main_path ));
            if ( $wp_main_path == DIRECTORY_SEPARATOR ) {
                throw new \Exception("No WordPress installation found.", 1);
            }
        }
        return $wp_main_path;
    }

    protected static function basePath() {
        return dirname(\Composer\Factory::getComposerFile());
    }

    public static function setup() {
        $phinxfile = implode(DIRECTORY_SEPARATOR, [ dirname(dirname(__FILE__)), 'files', static::$scriptname,]);
        $destination = implode(DIRECTORY_SEPARATOR, [ static::basePath(), static::$scriptname,]);
        touch($destination);
        copy($phinxfile, $destination);
    }

}
