<?php

namespace Macwinnie\WpDbPhinxHelper;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class DBUtilisator {

    protected static $scriptname = 'phinx.php';

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

    protected static function basePath($checkSetup = true) {
        $vendorpath = realpath(
            dirname( # macwinnie
                dirname( # macwinnie/wp-db-phinx-helper
                    dirname( # macwinnie/wp-db-phinx-helper/src
                        __FILE__
                    )
                )
            )
        );

        $basepath = dirname($vendorpath);
        while ( ! file_exists( implode( DIRECTORY_SEPARATOR, [ $basepath, 'composer.json' ] ) ) ) {
            $basepath = dirname($basepath);
        }

        if ( $checkSetup and ! file_exists( implode(DIRECTORY_SEPARATOR, [$basepath, static::$scriptname]) ) ) {
            static::setup();
        }

        return $basepath;
    }

    public static function get_phinx_config () {
        $base = static::basePath();

        global $wpdb;

        $dbhost_parts = explode(':', $wpdb->dbhost);
        if (count($dbhost_parts) == 1) {
            $dbhost_parts[] = "3306";
        }

        $phinx_config = [
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

        return $phinx_config;
    }

    protected static function prepare_phinx() {
        $phinx_config = static::get_phinx_config();

        return new Manager(
            new Config($phinx_config),
            new StringInput(' '),
            new NullOutput()
        );
    }

    public static function db_migrate() {
        $phinx = static::prepare_phinx();
        $phinx->migrate('wordpress');
    }

    public static function setup() {
        $phinxfile = implode(DIRECTORY_SEPARATOR, [ dirname(dirname(__FILE__)), 'files', static::$scriptname,]);
        $destination = implode(DIRECTORY_SEPARATOR, [ static::basePath(false), static::$scriptname,]);
        touch($destination);
        copy($phinxfile, $destination);
        static::db_migrate();
    }

    public static function plugin_activation_method () {
        static::setup();
    }

    public static function plugin_uninstall_method () {
        $phinx = static::prepare_phinx();
        $phinx->rollback('wordpress', 'all', true);
    }

}
