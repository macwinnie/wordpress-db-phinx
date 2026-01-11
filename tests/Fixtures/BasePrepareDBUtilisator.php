<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\DBUtilisator;

/**
 * Helper class to test the base DBUtilisator::prepare_phinx implementation.
 *
 * It only provides get_plugin_dir; everything else comes from DBUtilisator.
 */
final class BasePrepareDBUtilisator extends DBUtilisator {
    /** @var string */
    private static $pluginDir;

    public static function setPluginDir(string $dir): void {
        self::$pluginDir = $dir;
    }

    protected static function get_plugin_dir(): string {
        return self::$pluginDir;
    }
}
