<?php

namespace Macwinnie\WpDbPhinxHelper;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class DBUtilisator {

    /**
     * The protected static variable is the name of the phinx config file residing in the
     * plugin root folder. Not to be changed.
     * @var string
     */
    protected static $scriptname = "phinx.php";

    /**
     * https://book.cakephp.org/phinx/0/en/configuration.html#version-order
     * @var string
     */
    protected static $phinx_version_order = "creation";

    /**
     * name of the table (without WP DB Prefix) the executed migrations are documented
     * @var string
     */
    protected static $phinx_migration_table = "phinxlog";

    /**
     * method to retrieve the root path of the wordpress installation
     * @return string root path of WordPress, not plugin
     */
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

    /**
     * retrieve base path of plugin – or at least the closest path containing a `composer.json` file
     * @param  boolean $checkSetup shall the Phinx setup be checked? Default is `true`.
     * @return string              plugin path / base path of closest location containing a `composer.json` file
     */
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

    /**
     * building the phinx config out of WordPress variables to not keep duplicate config
     * @return [string] Phinx config for WordPress, see https://book.cakephp.org/phinx/0/en/configuration.html
     */
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
                'default_migration_table' => $wpdb->prefix . static::phinx_migration_table,
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
            'version_order' => static::phinx_version_order,
        ];

        return $phinx_config;
    }

    /**
     * prepare Phinx for execution
     * @return Phinx\Migration\Manager Phinx Manager object to interact with
     */
    protected static function prepare_phinx() {
        $phinx_config = static::get_phinx_config();

        return new Manager(
            new Config($phinx_config),
            new StringInput(' '),
            new NullOutput()
        );
    }

    /**
     * method to actually run migrations
     * @return void
     */
    public static function db_migrate(): void {
        $phinx = static::prepare_phinx();
        $phinx->migrate('wordpress');
    }

    /**
     * setup Phinx – and place `phinx.php` script template in Plugin root if it does not already
     * exist there, so config can be applied.
     * @return void
     */
    public static function setup(): void {
        $phinxfile = implode(DIRECTORY_SEPARATOR, [ dirname(dirname(__FILE__)), 'files', static::$scriptname,]);
        $destination = implode(DIRECTORY_SEPARATOR, [ static::basePath(false), static::$scriptname,]);
        if ( ! file_exists($destination) ) {
            touch($destination);
            copy($phinxfile, $destination);
        }
        static::db_migrate();
    }

    /**
     * plugin activation method that shall be called on plugin activation hook – needs to be called
     * in child classes!
     * @return void
     */
    public static function plugin_activation_method (): void {
        static::setup();
    }

    /**
     * plugin uninstall method that shall be called on plugin activation hook – needs to be called
     * in child classes!
     * @return void
     */
    public static function plugin_uninstall_method (): void {
        $phinx = static::prepare_phinx();
        $phinx->rollback('wordpress', 'all', true);
    }

}
