<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Unit;

use Macwinnie\WpDbPhinxHelper\DBUtilisator;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\BasePrepareDBUtilisator;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\TestDBUtilisator;
use Macwinnie\WpDbPhinxHelper\Tests\TestCase;

use Phinx\Migration\Manager;

final class DBUtilisatorTest extends TestCase {
    public function testBasePathCreatesPhinxFileOnSetup(): void {
        $base = (new \ReflectionClass(TestDBUtilisator::class))
            ->getMethod('basePath');
        $base->setAccessible(true);

        // first call triggers setup() and should create phinx.php
        $path = $base->invoke(null); // static protected

        $this->assertSame($this->tmpPluginDir, $path);
        $this->assertFileExists($this->tmpPluginDir . DIRECTORY_SEPARATOR . 'phinx.php');
    }

    public function testGetPhinxConfigUsesWpdbGlobals(): void {
        /** @var array<string, mixed> $config */
        $config = TestDBUtilisator::get_phinx_config();

        $this->assertArrayHasKey('paths', $config);
        $this->assertArrayHasKey('environments', $config);

        /** @var array<string, mixed> $environments */
        $environments = $config['environments'];

        $this->assertSame(
            $this->wpdb->prefix . 'phinxlog',
            $environments['default_migration_table']
        );

        $this->assertSame(
            'wordpress',
            $environments['default_environment']
        );

        /** @var array<string, mixed> $wordpressEnv */
        $wordpressEnv = $environments['wordpress'];

        $this->assertSame('mysql', $wordpressEnv['adapter']);
        $this->assertSame($this->wpdb->dbhost, $wordpressEnv['host']);
        $this->assertSame($this->wpdb->dbname, $wordpressEnv['name']);
        $this->assertSame($this->wpdb->dbuser, $wordpressEnv['user']);
        $this->assertSame($this->wpdb->dbpassword, $wordpressEnv['pass']);
        $this->assertSame($this->wpdb->charset, $wordpressEnv['charset']);
        $this->assertSame($this->wpdb->collate, $wordpressEnv['collation']);
        $this->assertSame($this->wpdb->prefix, $wordpressEnv['table_prefix']);
    }

    public function testPluginActivationMethodRunsSetup(): void {
        $phinxFile = $this->tmpPluginDir . DIRECTORY_SEPARATOR . 'phinx.php';

        $this->assertFileDoesNotExist($phinxFile);

        TestDBUtilisator::plugin_activation_method();

        $this->assertFileExists($phinxFile);
    }

    public function testPluginUninstallMethodCallsRollback(): void {
        TestDBUtilisator::plugin_uninstall_method();

        $manager = TestDBUtilisator::getLastManager();
        $this->assertNotNull($manager);

        /** @phpstan-ignore-next-line property defined on test subclass */
        $this->assertTrue($manager->rolledBack);
    }

    public function testDbMigrateCallsManagerMigrate(): void {
        TestDBUtilisator::db_migrate();

        $manager = TestDBUtilisator::getLastManager();
        $this->assertNotNull($manager);

        /** @phpstan-ignore-next-line property defined on test subclass */
        $this->assertTrue($manager->migrated);
    }

    public function testGetWpMainPathReturnsRootWhenWpLoadExists(): void {
        // Project root should be two levels above tests/Unit
        $projectRoot = \realpath(\dirname(__DIR__, 2));
        $this->assertNotFalse($projectRoot);

        $wpLoad = $projectRoot . DIRECTORY_SEPARATOR . 'wp-load.php';

        // If this is a real WP project, don't mess with it
        if (file_exists($wpLoad)) {
            $this->markTestSkipped('Real wp-load.php exists; skipping synthetic test.');
        }

        // Create fake wp-load.php to satisfy get_wp_main_path
        touch($wpLoad);

        $result = DBUtilisator::get_wp_main_path();

        $this->assertSame($projectRoot, $result);

        unlink($wpLoad);
    }

    public function testGetWpMainPathThrowsWhenNoWordPressFound(): void {
        $projectRoot = \realpath(\dirname(__DIR__, 2));
        $this->assertNotFalse($projectRoot);

        $wpLoad = $projectRoot . DIRECTORY_SEPARATOR . 'wp-load.php';

        // If real wp-load.php exists, we can't test the failure branch safely.
        if (file_exists($wpLoad)) {
            $this->markTestSkipped('Real wp-load.php exists; skipping failure-path test.');
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No WordPress installation found.');

        DBUtilisator::get_wp_main_path();
    }

    public function testBasePathWithoutSetupDoesNotCreatePhinxFile(): void {
        $base = (new \ReflectionClass(TestDBUtilisator::class))
            ->getMethod('basePath');
        $base->setAccessible(true);

        // call with $checkSetup = false
        $path = $base->invoke(null, false);

        $this->assertSame($this->tmpPluginDir, $path);
        $this->assertFileDoesNotExist($this->tmpPluginDir . DIRECTORY_SEPARATOR . 'phinx.php');
    }

    public function testSetupDoesNotOverwriteExistingPhinxFile(): void {
        $phinxFile = $this->tmpPluginDir . DIRECTORY_SEPARATOR . 'phinx.php';

        // Create a fake existing file with a known marker
        file_put_contents($phinxFile, 'ORIGINAL');

        TestDBUtilisator::setup();

        $contents = file_get_contents($phinxFile);
        $this->assertSame('ORIGINAL', $contents);
    }

    public function testBasePreparePhinxCreatesRealManagerInstance(): void {
        // Use the same temp plugin dir we already prepared in setUp()
        BasePrepareDBUtilisator::setPluginDir($this->tmpPluginDir);

        // Ensure phinx.php exists so basePath()/setup() don’t fail
        $phinxFile = $this->tmpPluginDir . DIRECTORY_SEPARATOR . 'phinx.php';

        if (! file_exists($phinxFile)) {
            file_put_contents($phinxFile, '<?php return [];');
        }

        $ref = new \ReflectionClass(BasePrepareDBUtilisator::class);
        $method = $ref->getMethod('prepare_phinx');
        $method->setAccessible(true);

        /** @var Manager $manager */
        $manager = $method->invoke(null);

        $this->assertInstanceOf(Manager::class, $manager);
    }

    public function testAssertValidPathThrowsOnFalse(): void {
        $ref = new \ReflectionClass(DBUtilisator::class);
        $method = $ref->getMethod('assertValidPath');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No path found.');

        $method->invoke(null, false);
    }

    public function testBasePathClimbsUntilComposerJsonIsFound(): void {
        // Create a nested temp directory: /tmp/plugin/subdir
        $pluginDir = $this->tmpPluginDir . '/subdir';
        mkdir($pluginDir);

        // Place composer.json only in the parent directory (tmpPluginDir)
        file_put_contents($this->tmpPluginDir . '/composer.json', '{}');

        // Configure TestDBUtilisator to start at the subdir
        TestDBUtilisator::setPluginDir($pluginDir);

        // Reflect basePath() to call it with $checkSetup = false
        $ref = new \ReflectionClass(TestDBUtilisator::class);
        $method = $ref->getMethod('basePath');
        $method->setAccessible(true);

        // Expect the method to climb up from /subdir → /tmpPluginDir
        $result = $method->invoke(null, false);

        $this->assertSame($this->tmpPluginDir, $result);
    }

    public function testGetByIdThrowsModelNotFoundExceptionWhenNoRowFound(): void {
        // Ensure we have some data – but no row with ID 999
        $table = $this->wpdb->prefix . 'test_posts';
        $this->wpdb->tableRows[$table] = [
            [
                'id' => 1,
                'uuid' => 'uuid-1',
                'slug' => 'first-post',
                'title' => 'First',
                'content' => '...',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
                'content_hash' => 'hash1',
            ],
        ];

        $this->expectException(\Macwinnie\WpDbPhinxHelper\Exceptions\ModelNotFoundException::class);
        $this->expectExceptionMessage(
            'Model Macwinnie\WpDbPhinxHelper\Tests\Fixtures\TestPostModel with ID 999 not found'
        );

        \Macwinnie\WpDbPhinxHelper\Tests\Fixtures\TestPostModel::getByID(999);
    }
}
