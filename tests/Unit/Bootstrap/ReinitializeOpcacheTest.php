<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Bootstrap;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Exception\BootstrapException;
use PHPUnit\Framework\TestCase;

/**
 * reinitialize() must read app/etc/config.php from disk, not from opcache.
 *
 * In a long-running CLI process opcache never revalidates timestamps: the
 * revalidate window is measured against the request start time, which is
 * frozen at process start. A config.php cached at daemon boot is therefore
 * served in its boot-time version forever — the stale-registry guard and
 * the fresh ObjectManager both see the old module list, and a module
 * enabled mid-session stays invisible no matter how often reinitialize()
 * runs.
 *
 * Requires an active opcache with a non-zero revalidate window (run with
 * -d opcache.enable_cli=1 -d opcache.revalidate_freq=2); skipped otherwise,
 * because without those the staleness cannot exist (revalidate_freq=0
 * re-checks the timestamp on every include even with a frozen request time).
 */
class ReinitializeOpcacheTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        MagentoBootstrap::reset();
        $this->tmpDir = sys_get_temp_dir() . '/bricklayer_reinit_opcache_test_' . uniqid();
        mkdir($this->tmpDir . '/app/etc', 0755, true);
    }

    protected function tearDown(): void
    {
        MagentoBootstrap::reset();
        $this->removeDirectory($this->tmpDir);
    }

    public function testReinitializeSeesConfigPhpChangesHiddenByOpcache(): void
    {
        if (!function_exists('opcache_get_status') || opcache_get_status(false) === false) {
            self::markTestSkipped('opcache not active — run phpunit with -d opcache.enable_cli=1');
        }

        $registeredName = 'BricklayerTest_OpcacheRegistered' . uniqid();
        $ghostName = 'BricklayerTest_OpcacheGhost' . uniqid();

        $registeredPath = $this->tmpDir . '/app/code/BricklayerTest/OpcacheRegistered';
        mkdir($registeredPath, 0755, true);
        \Magento\Framework\Component\ComponentRegistrar::register(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $registeredName,
            $registeredPath
        );

        $configPath = $this->tmpDir . '/app/etc/config.php';

        // Boot-time state: only the registered module. Age the mtime so
        // opcache.file_update_protection does not keep the file uncached.
        $this->writeConfigPhp([$registeredName => 1]);
        touch($configPath, time() - 3600);
        include $configPath; // compile + cache

        // Mid-session change: a second module gets enabled on disk. It is
        // deliberately NOT registered, so a reinitialize() that reads the
        // real file must abort on the stale-registry guard.
        $this->writeConfigPhp([$registeredName => 1, $ghostName => 1]);

        // Precondition: opcache actually hides the change from a plain
        // include. If the environment revalidates, this test cannot prove
        // anything either way.
        $cached = include $configPath;
        if (isset($cached['modules'][$ghostName])) {
            self::markTestSkipped('opcache revalidated the fixture — staleness not reproducible here');
        }

        $this->setStatic('magentoRoot', $this->tmpDir);
        $this->setStatic('objectManager', new \stdClass());

        try {
            MagentoBootstrap::reinitialize();
            self::fail('reinitialize() must abort: the on-disk config.php enables an unregistered module');
        } catch (BootstrapException $e) {
            self::assertStringContainsString(
                $ghostName,
                $e->getMessage(),
                'reinitialize() read the opcached boot-time config.php instead of the on-disk file'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, int> $modules
     */
    private function writeConfigPhp(array $modules): void
    {
        file_put_contents(
            $this->tmpDir . '/app/etc/config.php',
            '<?php return ' . var_export(['modules' => $modules], true) . ';'
        );
    }

    private function setStatic(string $property, mixed $value): void
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $ref->getProperty($property);
        $prop->setValue(null, $value);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
