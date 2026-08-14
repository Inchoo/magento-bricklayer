<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Config;

use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Config\EnvironmentResolver;
use Inchoo\MagentoBricklayer\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

class ConfigLoaderTest extends TestCase
{
    private ConfigLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->loader = new ConfigLoader();
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_config_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testLoadReturnsDefaultConfigWhenNoProjectConfig(): void
    {
        $config = $this->loader->load($this->tempDir);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tools', $config);
        $this->assertArrayHasKey('guidelines', $config);
        $this->assertArrayHasKey('agents', $config);
    }

    public function testLoadMergesProjectConfig(): void
    {
        $projectConfig = [
            'tools' => [
                'code-runner' => [
                    'enabled' => false,
                ],
            ],
        ];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($projectConfig)
        );

        $config = $this->loader->load($this->tempDir);

        $this->assertFalse($config['tools']['code-runner']['enabled']);
    }

    public function testLoadThrowsExceptionForInvalidJson(): void
    {
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            'invalid json {'
        );

        $this->expectException(ConfigurationException::class);
        $this->loader->load($this->tempDir);
    }

    public function testGetReturnsNestedValue(): void
    {
        $projectConfig = [
            'tools' => [
                'database-query' => [
                    'max_rows' => 50,
                ],
            ],
        ];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($projectConfig)
        );

        $this->loader->load($this->tempDir);
        $value = $this->loader->get('tools.database-query.max_rows');

        $this->assertEquals(50, $value);
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->loader->load($this->tempDir);
        $value = $this->loader->get('non.existent.key', 'default');

        $this->assertEquals('default', $value);
    }

    public function testIsToolEnabledReturnsTrue(): void
    {
        $this->loader->load($this->tempDir);
        $enabled = $this->loader->isToolEnabled('application-info');

        $this->assertTrue($enabled);
    }

    public function testIsToolEnabledReturnsFalseWhenDisabled(): void
    {
        $projectConfig = [
            'tools' => [
                'code-runner' => [
                    'enabled' => false,
                ],
            ],
        ];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($projectConfig)
        );

        $this->loader->load($this->tempDir);
        $enabled = $this->loader->isToolEnabled('code-runner');

        $this->assertFalse($enabled);
    }

    public function testGetToolConfigReturnsEmptyArrayForUnknownTool(): void
    {
        $this->loader->load($this->tempDir);
        $config = $this->loader->getToolConfig('unknown-tool');

        $this->assertEquals([], $config);
    }

    public function testClearCacheResetsConfig(): void
    {
        $projectConfig = ['tools' => ['test' => ['value' => 1]]];
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($projectConfig)
        );

        $this->loader->load($this->tempDir);
        $this->loader->clearCache();

        // Modify config file
        $projectConfig['tools']['test']['value'] = 2;
        file_put_contents(
            $this->tempDir . '/.bricklayer.json',
            json_encode($projectConfig)
        );

        $newConfig = $this->loader->load($this->tempDir);
        $this->assertEquals(2, $newConfig['tools']['test']['value']);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
