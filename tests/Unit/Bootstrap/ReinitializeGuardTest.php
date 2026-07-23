<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Bootstrap;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Exception\BootstrapException;
use Inchoo\MagentoBricklayer\Exception\MagentoNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Guards around reinitialize(): the component registry must be consistent
 * with app/etc/config.php BEFORE the old ObjectManager is discarded and a
 * new one (which may regenerate shared caches) is created.
 */
class ReinitializeGuardTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        MagentoBootstrap::reset();
        $this->tmpDir = sys_get_temp_dir() . '/bricklayer_reinit_guard_test_' . uniqid();
        mkdir($this->tmpDir . '/app/etc', 0755, true);
    }

    protected function tearDown(): void
    {
        MagentoBootstrap::reset();
        $this->removeDirectory($this->tmpDir);
    }

    public function testResetClearsSentinelMtimesAndReinitStats(): void
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);

        $sentinels = $ref->getProperty('sentinelMtimes');
        $sentinels->setAccessible(true);
        $sentinels->setValue(null, ['app/etc/config.php' => 123]);

        $stats = $ref->getProperty('lastReinitStats');
        $stats->setAccessible(true);
        $stats->setValue(null, ['registration_files' => ['/some/file.php']]);

        MagentoBootstrap::reset();

        self::assertSame([], $sentinels->getValue(null), 'reset() must clear sentinel snapshot');
        self::assertSame([], MagentoBootstrap::getLastReinitStats(), 'reset() must clear reinit stats');
    }

    public function testReinitializeAbortsAndKeepsObjectManagerWhenRegistryIsStale(): void
    {
        // Make the process-global registry non-empty so the mismatch check is
        // meaningful, and enable one module that is deliberately unregistered.
        $registeredName = 'BricklayerTest_GuardRegistered' . uniqid();
        $ghostName = 'BricklayerTest_GuardGhost' . uniqid();

        $registeredPath = $this->tmpDir . '/app/code/BricklayerTest/GuardRegistered';
        mkdir($registeredPath, 0755, true);
        \Magento\Framework\Component\ComponentRegistrar::register(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $registeredName,
            $registeredPath
        );

        $this->writeConfigPhp([$registeredName => 1, $ghostName => 1]);

        $objectManagerMock = new \stdClass();
        $this->setStatic('magentoRoot', $this->tmpDir);
        $this->setStatic('objectManager', $objectManagerMock);

        try {
            MagentoBootstrap::reinitialize();
            self::fail('reinitialize() must abort on a stale component registry');
        } catch (BootstrapException $e) {
            self::assertStringContainsString($ghostName, $e->getMessage());
            self::assertStringContainsString('restart', $e->getMessage());
        }

        self::assertSame(
            $objectManagerMock,
            $this->getStatic('objectManager'),
            'the previous ObjectManager must survive an aborted reinitialize'
        );
    }

    public function testReinitializeProceedsPastGuardWhenRegistryIsConsistent(): void
    {
        $registeredName = 'BricklayerTest_GuardConsistent' . uniqid();
        $registeredPath = $this->tmpDir . '/app/code/BricklayerTest/GuardConsistent';
        mkdir($registeredPath, 0755, true);
        \Magento\Framework\Component\ComponentRegistrar::register(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $registeredName,
            $registeredPath
        );

        $this->writeConfigPhp([$registeredName => 1]);

        $this->setStatic('magentoRoot', $this->tmpDir);
        $this->setStatic('objectManager', new \stdClass());

        // The registry is consistent, so the guard passes and reinitialize()
        // reaches initialize() — which must then fail on the fake root (no
        // real Magento installation). Reaching MagentoNotFoundException
        // proves the guard did not falsely abort.
        try {
            MagentoBootstrap::reinitialize();
            self::fail('initialize() must fail on a directory without Magento');
        } catch (MagentoNotFoundException $e) {
            self::assertStringNotContainsString('Component registry is stale', $e->getMessage());
        }

        self::assertSame(
            [],
            MagentoBootstrap::getLastReinitStats()['registration_files'] ?? null,
            'reinit stats must record the (empty) registration scan'
        );
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
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
    }

    private function getStatic(string $property): mixed
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue(null);
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
