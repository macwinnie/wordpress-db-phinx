<?php

namespace Macwinnie\WpDbPhinxHelper;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class DBUtilisator {
    /**
     * The protected static variable is the name of the phinx config file residing in the
     * plugin root folder. Not to be changed.
     * @var string
     */
    protected static $scriptname = "phinx.php";

    /**
     * In your plugin main file, you should define a constant retrieving the plugin main path
     *
     * ```php
     * <?php
     * // ...
     * define('PLUGIN_NAME_PLUGIN_DIR', plugin_dir_path(__FILE__));
     * // ...
     * ```
     *
     * In `DBUtilisator` extension, you must define a protected static method `get_plugin_dir`
     * where you should just return the value of such a defined constant:
     *
     * ```php
     * <?php
     * // ...
     * protected static function get_plugin_dir(): string {
     *     return PLUGIN_NAME_PLUGIN_DIR;
     * }
     * // ...
     * ```
     *
     * Make sure, your constant is a uique name which does not cause name conflicts within WP ecosystem!
     *
     * @return string plugin root path
     */
    abstract protected static function get_plugin_dir(): string;

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
    public static function get_wp_main_path(): string {
        // Start from the directory of this file, ensure it’s a valid path
        $wp_main_path = self::assertValidPath(realpath(__DIR__));

        while (
            ! file_exists(implode(DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-admin' ])) and
            ! file_exists(implode(DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-content' ])) and
            ! file_exists(implode(DIRECTORY_SEPARATOR, [ $wp_main_path, 'wp-load.php' ]))
        ) {

            // Climb one level up
            $parent = dirname($wp_main_path);

            // If we can’t climb further, there is no WP installation
            if ($parent === $wp_main_path || $parent === DIRECTORY_SEPARATOR) {
                throw new \Exception("No WordPress installation found.", 1);
            }

            // Normalize / validate the parent path, using the same error logic
            $wp_main_path = self::assertValidPath(realpath($parent));
        }

        return $wp_main_path;
    }

    /**
     * Normalize a path from realpath() and throw a single, shared error
     * if it is invalid.
     *
     * @param  string|false  $path
     * @return string
     */
    private static function assertValidPath(string|false $path): string {
        if ($path === false) {
            throw new \Exception("No path found.", 1);
        }

        return $path;
    }

    /**
     * Using the static method `get_plugin_dir` to do some directory retrievals
     *
     * @param  bool $checkSetup shall the Phinx setup be checked? Default is `true`.
     * @return string              plugin path / base path of closest location containing a `composer.json` file
     */
    protected static function basePath($checkSetup = true) {
        $basepath = static::get_plugin_dir();

        while (! file_exists(implode(DIRECTORY_SEPARATOR, [ $basepath, 'composer.json' ]))) {
            $basepath = dirname($basepath);
        }

        if ($checkSetup and ! file_exists(implode(DIRECTORY_SEPARATOR, [$basepath, static::$scriptname]))) {
            static::setup();
        }

        return $basepath;
    }

    /**
     * building the phinx config out of WordPress variables to not keep duplicate config
     * @return array<string, mixed> Phinx config for WordPress, see https://book.cakephp.org/phinx/0/en/configuration.html
     */
    public static function get_phinx_config() {
        $base = static::basePath();

        /** @var \wpdb $wpdb */
        global $wpdb;

        /** @var string $dbhost */
        $dbhost = (string) $wpdb->dbhost;

        /** @var list<string> $dbhost_parts */
        $dbhost_parts = explode(':', $dbhost);

        if (count($dbhost_parts) == 1) {
            // set default port for being confident about the DB Port
            $dbhost_parts[] = "3306";
        }

        $phinx_config = [
            'paths' => [
                'migrations' => implode(DIRECTORY_SEPARATOR, [ $base, 'db', 'migrations' ]),
                'seeds' => implode(DIRECTORY_SEPARATOR, [ $base, 'db', 'seeds' ]),
            ],
            'environments' => [
                'default_migration_table' => $wpdb->prefix . static::$phinx_migration_table,
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
                ],
            ],
            'version_order' => static::$phinx_version_order,
        ];

        return $phinx_config;
    }

    /**
     * prepare Phinx for execution
     * @return Manager  Phinx Manager object to interact with
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
        $phinxfile = implode(DIRECTORY_SEPARATOR, [ dirname(__FILE__), 'files', static::$scriptname,]);
        $destination = implode(DIRECTORY_SEPARATOR, [ static::basePath(false), static::$scriptname,]);

        if (! file_exists($destination)) {
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
    public static function plugin_activation_method(): void {
        static::setup();
    }

    /**
     * plugin uninstall method that shall be called on plugin activation hook – needs to be called
     * in child classes!
     * @return void
     */
    public static function plugin_uninstall_method(): void {
        $phinx = static::prepare_phinx();
        $phinx->rollback('wordpress', 'all', true);
    }
}
