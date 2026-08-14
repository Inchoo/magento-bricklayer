<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use PHPUnit\Framework\TestCase;

class ChecksConfigTest extends TestCase
{
    private string $tempDir;
    private ?string $originalMagentoRoot = null;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_checksconfig_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $reflection = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $reflection->getProperty('magentoRoot');
        $this->originalMagentoRoot = $prop->getValue();
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $reflection->getProperty('magentoRoot');
        $prop->setValue(null, $this->originalMagentoRoot);

        $this->removeDirectory($this->tempDir);
    }

    private function setMagentoRoot(string $root): void
    {
        $reflection = new \ReflectionClass(MagentoBootstrap::class);
        $prop = $reflection->getProperty('magentoRoot');
        $prop->setValue(null, $root);
    }

    private function callGetConfigLoader(object $subject): ConfigLoader
    {
        $method = new \ReflectionMethod($subject, 'getConfigLoader');
        return $method->invoke($subject);
    }

    public function testItSnapshotsConfigMtimeAfterTheInitialLoad(): void
    {
        // Create a config file so load() can resolve a real path with a real mtime
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode(['tools' => []])
        );

        // Point MagentoBootstrap at our temp dir so ConfigLoader::load() picks it up
        $this->setMagentoRoot($this->tempDir);

        $subject = new class {
            use ChecksConfig;
        };

        // First call: initialises configLoader (creates loader, calls load(), then snapshots mtime)
        $loader = $this->callGetConfigLoader($subject);

        // Force load() so projectRoot is set on the loader — this is what makes the
        // snapshot-vs-null mismatch observable: buggy code snapshots null mtime before
        // load(), so after load() sets projectRoot, isConfigStale() sees mtime≠null → stale.
        $loader->get('tools');

        // After initial load+snapshot, isConfigStale() must return false
        $this->assertFalse(
            $loader->isConfigStale(),
            'Config should not be stale immediately after getConfigLoader() initialises the loader'
        );
    }

    public function testItDoesNotReportTheConfigAsStalImmediatelyAfterLoading(): void
    {
        // Create a config file
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode(['tools' => []])
        );

        $this->setMagentoRoot($this->tempDir);

        $subject = new class {
            use ChecksConfig;
        };

        // First call: initialise the loader
        $loader = $this->callGetConfigLoader($subject);

        // Force load() so projectRoot is set and mtime is observable
        $loader->get('tools');

        // Check staleness — snapshot taken AFTER load() must match the file's actual mtime
        $staleBeforeSecondCall = $loader->isConfigStale();

        $this->assertFalse(
            $staleBeforeSecondCall,
            'getConfigLoader() must not leave the loader in a stale state after the initial call'
        );
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
