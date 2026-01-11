<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Examples;

use Macwinnie\WpDbPhinxHelper\DBUtilisator;

/**
 * Example DB setup class.
 *
 * This class demonstrates how to integrate DBUtilisator into a plugin.
 *
 * In a real plugin, you would:
 *
 * 1. Define a plugin directory constant in your main plugin file, e.g.:
 *
 *    define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
 *
 * 2. Implement a DB setup class similar to this one, extending DBUtilisator:
 *
 *    final class DBSetup extends DBUtilisator {
 *        protected static function get_plugin_dir(): string {
 *            return MY_PLUGIN_DIR;
 *        }
 *    }
 *
 * 3. Call the helper methods from your plugin hooks:
 *
 *    register_activation_hook( __FILE__, [ \MyVendor\MyPlugin\DBSetup::class, 'activate' ] );
 *    register_uninstall_hook( __FILE__, [ \MyVendor\MyPlugin\DBSetup::class, 'uninstall' ] );
 *
 * This example uses the MACWINNIE_XYZ_PLUGIN_DIR constant from stubs/constants-stub.php
 * so it also works nicely with PHPStan and without a full WordPress runtime.
 */
final class DBSetup extends DBUtilisator {
    /**
     * Return the plugin root directory.
     *
     * In your own plugin, simply return your plugin dir constant here.
     */
    protected static function get_plugin_dir(): string {
        if (\defined('MACWINNIE_XYZ_PLUGIN_DIR')) {
            /** @var string $dir */
            $dir = MACWINNIE_XYZ_PLUGIN_DIR;

            return $dir;
        }

        return \dirname(__DIR__);
    }

    /**
     * Hook this into register_activation_hook in your plugin.
     *
     * Example in your main plugin file:
     *
     * register_activation_hook(
     *     MY_PLUGIN_FILE,
     *     [ \Macwinnie\WpDbPhinxHelper\Examples\DBSetup::class, 'activate' ]
     * );
     */
    public static function activate(): void {
        // This will create phinx.php in the plugin root (if missing),
        // run migrations, and ensure the schema is up to date.
        static::plugin_activation_method();
    }

    /**
     * Hook this into register_uninstall_hook in your plugin.
     *
     * Example in your main plugin file:
     *
     * register_uninstall_hook(
     *     MY_PLUGIN_FILE,
     *     [ \Macwinnie\WpDbPhinxHelper\Examples\DBSetup::class, 'uninstall' ]
     * );
     */
    public static function uninstall(): void {
        // This will roll back all migrations and clean up DB tables.
        static::plugin_uninstall_method();
    }

    /**
     * Optional: convenience method if you want to trigger migrations manually
     * from within your plugin code (e.g. via a WP-CLI command or admin action).
     */
    public static function migrate(): void {
        static::db_migrate();
    }
}
