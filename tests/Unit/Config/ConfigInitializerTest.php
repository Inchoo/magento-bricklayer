<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Config;

use Inchoo\MagentoBricklayer\Config\ConfigInitializer;
use PHPUnit\Framework\TestCase;

class ConfigInitializerTest extends TestCase
{
    private ConfigInitializer $initializer;
    private string $tempDir;

    protected function setUp(): void
    {
        ConfigInitializer::clearConfigurableToolsCache();
        $this->initializer = new ConfigInitializer();
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_initializer_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        ConfigInitializer::clearConfigurableToolsCache();
        $this->removeDirectory($this->tempDir);
    }

    public function testBuildConfigProductionDisablesDestructiveTools(): void
    {
        $config = $this->initializer->buildConfig('production');

        $destructiveTools = [
            'product-delete',
            'category-delete',
            'customer-delete',
            'customer-address-delete',
            'order-cancel',
            'order-create',
            'creditmemo-create',
            'generate-module',
            'generate-model',
            'generate-controller',
            'generate-api',
        ];

        foreach ($destructiveTools as $tool) {
            $this->assertArrayHasKey($tool, $config['tools'], "Tool '$tool' should exist in production config");
            $this->assertFalse(
                $config['tools'][$tool]['enabled'],
                "Tool '$tool' should be disabled in production config"
            );
        }
    }

    public function testBuildConfigProductionDisablesCodeRunner(): void
    {
        $config = $this->initializer->buildConfig('production');

        $this->assertFalse($config['tools']['code-runner']['enabled']);
        $this->assertFalse($config['tools']['code-runner']['allow_write']);
    }

    public function testBuildConfigProductionReducesQueryLimit(): void
    {
        $config = $this->initializer->buildConfig('production');

        $this->assertEquals(50, $config['tools']['database-query']['max_rows']);
    }

    public function testBuildConfigDeveloperExplicitlyDisablesDestructiveTools(): void
    {
        $config = $this->initializer->buildConfig('developer');

        $destructiveTools = [
            'product-delete',
            'category-delete',
            'customer-delete',
            'customer-address-delete',
            'order-cancel',
            'order-create',
            'creditmemo-create',
            'generate-module',
            'generate-model',
            'generate-controller',
            'generate-api',
        ];

        foreach ($destructiveTools as $tool) {
            $this->assertArrayHasKey(
                $tool,
                $config['tools'],
                "Destructive tool '$tool' should appear in developer config"
            );
            $this->assertFalse(
                $config['tools'][$tool]['enabled'],
                "Destructive tool '$tool' should be explicitly disabled in developer config"
            );
        }
    }

    public function testBuildConfigDeveloperEnablesNonDestructiveWriteTools(): void
    {
        $config = $this->initializer->buildConfig('developer');

        $nonDestructive = [
            'product-create',
            'product-update',
            'category-create',
            'customer-create',
            'order-add-comment',
            'invoice-create',
            'shipment-create',
        ];

        foreach ($nonDestructive as $tool) {
            $this->assertArrayHasKey($tool, $config['tools']);
            $this->assertTrue($config['tools'][$tool]['enabled']);
        }
    }

    public function testBuildConfigDeveloperEnablesCodeRunnerReadOnly(): void
    {
        $config = $this->initializer->buildConfig('developer');

        $this->assertTrue($config['tools']['code-runner']['enabled']);
        $this->assertFalse($config['tools']['code-runner']['allow_write']);
        $this->assertEquals(60, $config['tools']['code-runner']['max_timeout']);
    }

    public function testBuildConfigDeveloperSetsLogAndDatabaseDefaults(): void
    {
        $config = $this->initializer->buildConfig('developer');

        $this->assertEquals(100, $config['tools']['database-query']['max_rows']);
        $this->assertEquals(500, $config['tools']['log']['max_lines']);
    }

    public function testBuildConfigDefaultModeMatchesDeveloper(): void
    {
        $configDefault = $this->initializer->buildConfig('default');
        $configDeveloper = $this->initializer->buildConfig('developer');

        $this->assertEquals($configDefault, $configDeveloper);
    }

    public function testBuildConfigDoesNotContainProductionSafety(): void
    {
        $configProduction = $this->initializer->buildConfig('production');
        $configDeveloper = $this->initializer->buildConfig('developer');

        $this->assertArrayNotHasKey('production_safety', $configProduction);
        $this->assertArrayNotHasKey('production_safety', $configDeveloper);
    }

    public function testGenerateCreatesFile(): void
    {
        $result = $this->initializer->generate($this->tempDir);

        $this->assertTrue($result['created']);
        $this->assertFileExists($this->tempDir . '/.bricklayer.json');
        $this->assertArrayHasKey('disabled_tools', $result);
    }

    public function testGenerateDoesNotOverwriteWithoutForce(): void
    {
        // Create initial file
        file_put_contents($this->tempDir . '/.bricklayer.json', '{"tools":{}}');

        $result = $this->initializer->generate($this->tempDir);

        $this->assertFalse($result['created']);
        // Original content should be preserved
        $content = file_get_contents($this->tempDir . '/.bricklayer.json');
        $this->assertEquals('{"tools":{}}', $content);
    }

    public function testGenerateOverwritesWithForce(): void
    {
        // Create initial file
        file_put_contents($this->tempDir . '/.bricklayer.json', '{"tools":{}}');

        $result = $this->initializer->generate($this->tempDir, true);

        $this->assertTrue($result['created']);
        // Content should be regenerated
        $content = file_get_contents($this->tempDir . '/.bricklayer.json');
        $this->assertNotEquals('{"tools":{}}', $content);
    }

    public function testGenerateReportsDisabledToolCount(): void
    {
        // Create env.php for production detection
        mkdir($this->tempDir . '/app/etc', 0755, true);
        file_put_contents(
            $this->tempDir . '/app/etc/env.php',
            '<?php return ["MAGE_MODE" => "production"];'
        );

        $result = $this->initializer->generate($this->tempDir);

        $this->assertTrue($result['created']);
        $this->assertEquals('production', $result['deploy_mode']);
        // Production config disables code-runner + 11 destructive tools = 12
        $this->assertEquals(12, $result['disabled_tools']);
    }

    public function testGenerateDeveloperDisablesDestructiveTools(): void
    {
        $result = $this->initializer->generate($this->tempDir);

        // Developer mode disables the 11 destructive tools by default
        $this->assertEquals(11, $result['disabled_tools']);
    }

    public function testDetectDeployModeReadsEnvPhp(): void
    {
        mkdir($this->tempDir . '/app/etc', 0755, true);
        file_put_contents(
            $this->tempDir . '/app/etc/env.php',
            '<?php return ["MAGE_MODE" => "production"];'
        );

        $mode = $this->initializer->detectDeployMode($this->tempDir);
        $this->assertEquals('production', $mode);
    }

    public function testDetectDeployModeReturnsDefaultWhenNoEnvPhp(): void
    {
        $mode = $this->initializer->detectDeployMode($this->tempDir);
        $this->assertEquals('default', $mode);
    }

    public function testDetectDeployModeReturnsDefaultWhenNoMageMode(): void
    {
        mkdir($this->tempDir . '/app/etc', 0755, true);
        file_put_contents(
            $this->tempDir . '/app/etc/env.php',
            '<?php return ["db" => ["host" => "localhost"]];'
        );

        $mode = $this->initializer->detectDeployMode($this->tempDir);
        $this->assertEquals('default', $mode);
    }

    public function testExistsReturnsTrueWhenFilePresent(): void
    {
        file_put_contents($this->tempDir . '/.bricklayer.json', '{}');

        $this->assertTrue($this->initializer->exists($this->tempDir));
    }

    public function testExistsReturnsFalseWhenFileMissing(): void
    {
        $this->assertFalse($this->initializer->exists($this->tempDir));
    }

    public function testItReportsCreatedFalseWhenTheConfigFileWriteFails(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('chmod-based unwritable-directory simulation has no effect when running as root.');
        }

        // Make the directory read-only so file_put_contents() fails
        chmod($this->tempDir, 0444);

        $result = $this->initializer->generate($this->tempDir);

        // Restore permissions for tearDown cleanup
        chmod($this->tempDir, 0755);

        $this->assertFalse($result['created']);
    }

    public function testItReportsCreatedTrueWhenTheConfigFileWriteSucceeds(): void
    {
        $result = $this->initializer->generate($this->tempDir);

        $this->assertTrue($result['created']);
        $this->assertFileExists($this->tempDir . '/.bricklayer.json');
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
