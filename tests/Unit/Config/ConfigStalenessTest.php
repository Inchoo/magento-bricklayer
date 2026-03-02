<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Config;

use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

class ConfigStalenessTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_staleness_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testIsConfigStaleReturnsFalseWhenUnchanged(): void
    {
        $config = ['tools' => ['code-runner' => ['enabled' => true]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        $this->assertFalse($loader->isConfigStale());
    }

    public function testIsConfigStaleReturnsTrueAfterFileModification(): void
    {
        $config = ['tools' => ['code-runner' => ['enabled' => true]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        // Modify file — touch with future timestamp to guarantee mtime change
        touch($this->tempDir . '/.bricklayer.json', time() + 10);

        $this->assertTrue($loader->isConfigStale());
    }

    public function testIsConfigStaleReturnsTrueAfterFileDeletion(): void
    {
        $config = ['tools' => ['code-runner' => ['enabled' => true]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        unlink($this->tempDir . '/.bricklayer.json');

        $this->assertTrue($loader->isConfigStale());
    }

    public function testIsConfigStaleReturnsFalseWhenNoConfigFileExisted(): void
    {
        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        // No config file existed before and still doesn't
        $this->assertFalse($loader->isConfigStale());
    }

    public function testReloadIfStaleReturnsFalseWhenUnchanged(): void
    {
        $config = ['tools' => ['code-runner' => ['enabled' => true]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        $this->assertFalse($loader->reloadIfStale());
    }

    public function testReloadIfStalePicksUpNewValues(): void
    {
        $config = ['tools' => ['code-runner' => ['enabled' => true]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        // Verify initial value
        $this->assertTrue($loader->get('tools.code-runner.enabled'));

        // Modify config on disk
        $config['tools']['code-runner']['enabled'] = false;
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );
        touch($this->tempDir . '/.bricklayer.json', time() + 10);

        // Reload should detect change and return true
        $this->assertTrue($loader->reloadIfStale());

        // New value should be available
        $this->assertFalse($loader->get('tools.code-runner.enabled'));
    }

    public function testReloadIfStaleDoesNotReloadWhenNotStale(): void
    {
        $config = ['tools' => ['code-runner' => ['enabled' => true]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        // First call — not stale
        $this->assertFalse($loader->reloadIfStale());

        // Value should still be the same
        $this->assertTrue($loader->get('tools.code-runner.enabled'));
    }

    public function testSnapshotConfigMtimeUpdatesAfterReload(): void
    {
        $config = ['tools' => ['test-tool' => ['value' => 1]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );

        $loader = new ConfigLoader();
        $loader->load($this->tempDir);
        $loader->snapshotConfigMtime();

        // Modify and trigger reload
        $config['tools']['test-tool']['value'] = 2;
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($config)
        );
        touch($this->tempDir . '/.bricklayer.json', time() + 10);

        $loader->reloadIfStale();

        // After reload, should no longer be stale
        $this->assertFalse($loader->isConfigStale());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $contents = scandir($dir);
        if ($contents === false) {
            return;
        }

        $files = array_diff($contents, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
