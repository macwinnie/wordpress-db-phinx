<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests;

use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\TestDBUtilisator;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {
    /** @var \wpdb */
    protected $wpdb;
    protected string $tmpPluginDir;

    /**
     * setup test environment
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        /** @var \wpdb $wpdb */
        $wpdb = $GLOBALS['wpdb'];

        // reset in-memory DB between tests
        $this->wpdb = $wpdb;
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->last_error = '';
        $this->wpdb->insert_id = 0;

        $this->tmpPluginDir = sys_get_temp_dir() . '/wpdb_phinx_helper_tests_' . uniqid('', true);
        mkdir($this->tmpPluginDir, 0777, true);

        // composer.json is required by basePath()
        file_put_contents($this->tmpPluginDir . '/composer.json', '{}');

        TestDBUtilisator::setPluginDir($this->tmpPluginDir);
    }

    /**
     * clean up after every test run
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->wpdb->resetTables();
        $this->wpdb->last_error = '';
        $this->wpdb->forcedVarResult = null;

        // existing cleanup for $this->tmpPluginDir ...
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpPluginDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        @rmdir($this->tmpPluginDir);

        parent::tearDown();
    }

    /**
     * Access a protected/private property via reflection
     *
     * @param  class-string|object $class
     * @param  string              $property
     *
     * @return \ReflectionProperty
     */
    protected function getProtected($class, $property): \ReflectionProperty {
        $ref = new \ReflectionProperty($class, $property);
        $ref->setAccessible(true);

        return $ref;
    }

    /**
     * Read protected/private static property value
     *
     * @param  class-string|object $class
     * @param  string              $property
     *
     * @return mixed
     */
    protected function getProtectedValue($class, $property): mixed {
        return $this->getProtected($class, $property)->getValue();
    }

    /**
     * Set protected/private static property value
     *
     * @param  class-string|object $class
     * @param  string              $property
     *
     * @return void
     */
    protected function setProtectedValue($class, $property, mixed $value): void {
        $this->getProtected($class, $property)->setValue($value);
    }
}
