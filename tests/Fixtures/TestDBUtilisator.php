<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\DBUtilisator;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

final class TestDBUtilisator extends DBUtilisator {
    /** @var string */
    private static $pluginDir;

    /** @var Manager|null */
    private static ?Manager $lastManager = null;

    public static function setPluginDir(string $dir): void {
        self::$pluginDir = $dir;
    }

    protected static function get_plugin_dir(): string {
        return self::$pluginDir;
    }

    /**
     * Helper for tests to inspect the last created Manager instance.
     */
    public static function getLastManager(): ?Manager {
        return self::$lastManager;
    }

    /**
     * @return \Phinx\Migration\Manager
     */
    protected static function prepare_phinx() {
        /** @var array<string, mixed> $configArray */
        $configArray = static::get_phinx_config();

        $config = new Config($configArray);
        $input = new StringInput(' ');
        $output = new NullOutput();

        // Return a Manager subclass that no-ops migrate/rollback
        $manager = new class ($config, $input, $output) extends Manager {
            public bool $migrated = false;
            public bool $rolledBack = false;

            /**
             * @param string   $environment
             * @param int|null $target
             * @param bool     $fake
             */
            public function migrate($environment, $target = null, $fake = false): void {
                $this->migrated = true;
                // no parent::migrate()
            }

            /**
             * @param string           $environment
             * @param int|string|null  $target
             * @param bool             $force
             * @param bool|null        $targetMustMatchVersion
             * @param bool             $fake
             */
            public function rollback($environment, $target = null, $force = false, $targetMustMatchVersion = null, $fake = false): void {
                $this->rolledBack = true;
                // no parent::rollback()
            }
        };

        self::$lastManager = $manager;

        return $manager;
    }
}
