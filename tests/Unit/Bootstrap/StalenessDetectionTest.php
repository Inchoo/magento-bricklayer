<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Bootstrap;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use PHPUnit\Framework\TestCase;

class StalenessDetectionTest extends TestCase
{
    private string $tmpDir;
    private string $configFile;
    private string $metadataFile;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bricklayer_staleness_test_' . uniqid();

        // Create sentinel file directory structure
        mkdir($this->tmpDir . '/app/etc', 0755, true);
        mkdir($this->tmpDir . '/generated/metadata', 0755, true);

        $this->configFile = $this->tmpDir . '/app/etc/config.php';
        $this->metadataFile = $this->tmpDir . '/generated/metadata/global.php';

        file_put_contents($this->configFile, '<?php return [];');
        file_put_contents($this->metadataFile, '<?php return [];');

        // Reset static state
        MagentoBootstrap::reset();
        $this->resetSentinelMtimes();
    }

    protected function tearDown(): void
    {
        MagentoBootstrap::reset();
        $this->resetSentinelMtimes();
        $this->removeDirectory($this->tmpDir);
    }

    public function testIsStaleReturnsFalseWhenFilesUnchanged(): void
    {
        $this->setMagentoRoot($this->tmpDir);
        $this->callSnapshotSentinels();

        $this->assertFalse(MagentoBootstrap::isStale());
    }

    public function testIsStaleReturnsTrueWhenFileMtimeChanges(): void
    {
        $this->setMagentoRoot($this->tmpDir);
        $this->callSnapshotSentinels();

        // Touch config file with a future mtime
        touch($this->configFile, time() + 10);
        clearstatcache();

        $this->assertTrue(MagentoBootstrap::isStale());
    }

    public function testIsStaleReturnsTrueWhenSentinelFileDeleted(): void
    {
        $this->setMagentoRoot($this->tmpDir);
        $this->callSnapshotSentinels();

        unlink($this->metadataFile);
        clearstatcache();

        $this->assertTrue(MagentoBootstrap::isStale());
    }

    public function testSnapshotSentinelsUpdatesRecordedMtimes(): void
    {
        $this->setMagentoRoot($this->tmpDir);
        $this->callSnapshotSentinels();

        // Touch file to make stale
        touch($this->configFile, time() + 10);
        clearstatcache();
        $this->assertTrue(MagentoBootstrap::isStale());

        // Re-snapshot should pick up the new mtime
        $this->callSnapshotSentinels();
        $this->assertFalse(MagentoBootstrap::isStale());
    }

    private function setMagentoRoot(string $path): void
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $ref->getProperty('magentoRoot');
        $prop->setAccessible(true);
        $prop->setValue(null, $path);
    }

    private function callSnapshotSentinels(): void
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $method = $ref->getMethod('snapshotSentinels');
        $method->setAccessible(true);
        $method->invoke(null);
    }

    private function resetSentinelMtimes(): void
    {
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $ref->getProperty('sentinelMtimes');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
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
