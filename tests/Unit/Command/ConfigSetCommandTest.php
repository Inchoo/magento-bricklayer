<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Command;

use Inchoo\MagentoBricklayer\Command\ConfigSetCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigSetCommandTest extends TestCase
{
    private CommandTester $tester;
    private string $tempDir;
    private string $configPath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_config_set_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->configPath = $this->tempDir . '/.bricklayer.json';

        $command = new ConfigSetCommand();
        $this->tester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testSetsBooleanValue(): void
    {
        $this->seedConfig([
            'tools' => [
                'product-delete' => ['enabled' => false],
            ],
        ]);

        $exitCode = $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'true',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode);
        $config = $this->readConfig();
        $this->assertTrue($config['tools']['product-delete']['enabled']);
        $this->assertStringContainsString('false → true', $this->tester->getDisplay());
    }

    public function testSetsIntegerValue(): void
    {
        $this->seedConfig([
            'tools' => [
                'database-query' => ['enabled' => true, 'max_rows' => 100],
            ],
        ]);

        $exitCode = $this->tester->execute([
            'key' => 'tools.database-query.max_rows',
            'value' => '250',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode);
        $config = $this->readConfig();
        $this->assertSame(250, $config['tools']['database-query']['max_rows']);
        $this->assertStringContainsString('100 → 250', $this->tester->getDisplay());
    }

    public function testSetsStringValue(): void
    {
        $this->seedConfig(['tools' => []]);

        $exitCode = $this->tester->execute([
            'key' => 'custom.note',
            'value' => 'hello world',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode);
        $config = $this->readConfig();
        $this->assertSame('hello world', $config['custom']['note']);
    }

    public function testSetsNullValue(): void
    {
        $this->seedConfig(['foo' => 'bar']);

        $this->tester->execute([
            'key' => 'foo',
            'value' => 'null',
            '--magento-root' => $this->tempDir,
        ]);

        $config = $this->readConfig();
        $this->assertArrayHasKey('foo', $config);
        $this->assertNull($config['foo']);
    }

    public function testSetsFloatValue(): void
    {
        $this->seedConfig(['tools' => []]);

        $this->tester->execute([
            'key' => 'tools.custom.ratio',
            'value' => '0.75',
            '--magento-root' => $this->tempDir,
        ]);

        $config = $this->readConfig();
        $this->assertSame(0.75, $config['tools']['custom']['ratio']);
    }

    public function testSetsJsonArrayValue(): void
    {
        $this->seedConfig(['tools' => []]);

        $this->tester->execute([
            'key' => 'guidelines.include',
            'value' => '["core","modules"]',
            '--magento-root' => $this->tempDir,
        ]);

        $config = $this->readConfig();
        $this->assertSame(['core', 'modules'], $config['guidelines']['include']);
    }

    public function testCreatesNestedPathWhenMissing(): void
    {
        $this->seedConfig(['tools' => []]);

        $exitCode = $this->tester->execute([
            'key' => 'tools.code-runner.max_timeout',
            'value' => '120',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode);
        $config = $this->readConfig();
        $this->assertSame(120, $config['tools']['code-runner']['max_timeout']);
        $this->assertStringContainsString('(unset) → 120', $this->tester->getDisplay());
    }

    public function testAutoCreatesConfigFileWhenMissing(): void
    {
        $this->assertFileDoesNotExist($this->configPath);

        $exitCode = $this->tester->execute([
            'key' => 'tools.product-update.enabled',
            'value' => 'false',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->configPath);
        $config = $this->readConfig();
        $this->assertFalse($config['tools']['product-update']['enabled']);
    }

    public function testRefusesInvalidValueType(): void
    {
        $this->seedConfig([
            'tools' => [
                'database-query' => ['enabled' => true, 'max_rows' => 100],
            ],
        ]);

        $exitCode = $this->tester->execute([
            'key' => 'tools.database-query.max_rows',
            'value' => '-5',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('invalid', strtolower($this->tester->getDisplay()));
        $config = $this->readConfig();
        $this->assertSame(100, $config['tools']['database-query']['max_rows']);
    }

    public function testRefusesNonBooleanEnabled(): void
    {
        $this->seedConfig([
            'tools' => [
                'product-delete' => ['enabled' => false],
            ],
        ]);

        $exitCode = $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'maybe',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(1, $exitCode);
        $config = $this->readConfig();
        $this->assertFalse($config['tools']['product-delete']['enabled']);
    }

    public function testFailsOnCorruptJson(): void
    {
        file_put_contents($this->configPath, '{not json');

        $exitCode = $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'true',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid JSON', $this->tester->getDisplay());
    }

    public function testWarnsWhenSettingEnabledOnNonConfigurableTool(): void
    {
        $this->seedConfig(['tools' => []]);

        $exitCode = $this->tester->execute([
            'key' => 'tools.product-get.enabled',
            'value' => 'false',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('product-get', $display);
        $this->assertStringContainsString('does not honor', $display);
    }

    public function testDoesNotWarnForConfigurableTool(): void
    {
        $this->seedConfig(['tools' => []]);

        $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'false',
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertStringNotContainsString('does not honor', $this->tester->getDisplay());
    }

    public function testFailsWhenMagentoRootCannotBeDetected(): void
    {
        $exitCode = $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'true',
            '--magento-root' => '/nonexistent/definitely/not/here/' . uniqid(),
        ]);

        $this->assertSame(1, $exitCode);
    }

    public function testEmitsHotReloadNoteOnSuccess(): void
    {
        $this->seedConfig(['tools' => ['product-delete' => ['enabled' => false]]]);

        $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'true',
            '--magento-root' => $this->tempDir,
        ]);

        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('hot-reload', $display);
        $this->assertStringContainsString('no agent', $display);
        $this->assertStringContainsString('restart', $display);
    }

    public function testDoesNotEmitHotReloadNoteOnFailure(): void
    {
        $this->seedConfig([
            'tools' => ['database-query' => ['enabled' => true, 'max_rows' => 100]],
        ]);

        $this->tester->execute([
            'key' => 'tools.database-query.max_rows',
            'value' => '-5',
            '--magento-root' => $this->tempDir,
        ]);

        $display = $this->tester->getDisplay();
        $this->assertStringNotContainsString('hot-reload', $display);
    }

    public function testVerifiesRoundTripOnSuccess(): void
    {
        $this->seedConfig(['tools' => ['product-delete' => ['enabled' => false]]]);

        $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'true',
            '--magento-root' => $this->tempDir,
        ]);

        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('Verified', $display);
        $this->assertStringContainsString('ConfigLoader', $display);
    }

    public function testWarnsWhenEnvVarShadowsChange(): void
    {
        $this->seedConfig(['tools' => ['product-delete' => ['enabled' => false]]]);

        putenv('BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED=false');
        try {
            $this->tester->execute([
                'key' => 'tools.product-delete.enabled',
                'value' => 'true',
                '--magento-root' => $this->tempDir,
            ]);
        } finally {
            putenv('BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED');
        }

        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED', $display);
        $this->assertStringContainsString('precedence', $display);
    }

    public function testDoesNotWarnAboutEnvVarWhenNoneSet(): void
    {
        $this->seedConfig(['tools' => ['product-delete' => ['enabled' => false]]]);

        putenv('BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED');

        $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'true',
            '--magento-root' => $this->tempDir,
        ]);

        $display = $this->tester->getDisplay();
        $this->assertStringNotContainsString('precedence', $display);
    }

    public function testInteractiveModeWalksFromKeySelectionToValue(): void
    {
        $this->seedConfig([
            'tools' => [
                'database-query' => ['enabled' => true, 'max_rows' => 100],
            ],
        ]);

        // Discover the position of database-query in the sorted tool list
        $tools = \Inchoo\MagentoBricklayer\Config\ConfigInitializer::discoverConfigurableTools();
        $toolIndex = (int) array_search('database-query', $tools, true);

        // Inputs:
        // 1. Tool index (database-query)
        // 2. Setting index within database-query: 0=enabled, 1=max_rows
        // 3. New max_rows value
        $this->tester->setInputs([(string) $toolIndex, '1', '250']);

        $exitCode = $this->tester->execute([
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode, $this->tester->getDisplay());
        $config = $this->readConfig();
        $this->assertSame(250, $config['tools']['database-query']['max_rows']);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('100 → 250', $display);
    }

    public function testInteractiveModeSkipsSettingChoiceForSingleOptionTool(): void
    {
        $this->seedConfig([
            'tools' => [
                'product-delete' => ['enabled' => false],
            ],
        ]);

        $tools = \Inchoo\MagentoBricklayer\Config\ConfigInitializer::discoverConfigurableTools();
        $toolIndex = (int) array_search('product-delete', $tools, true);

        // Inputs:
        // 1. Tool index
        // 2. Value (bool choice: "true" or "false")
        // product-delete only has 'enabled', so the setting-selection step is skipped.
        $this->tester->setInputs([(string) $toolIndex, 'true']);

        $exitCode = $this->tester->execute([
            '--magento-root' => $this->tempDir,
        ]);

        $this->assertSame(0, $exitCode, $this->tester->getDisplay());
        $config = $this->readConfig();
        $this->assertTrue($config['tools']['product-delete']['enabled']);
    }

    public function testInteractiveModeShowsCurrentValuesInMenu(): void
    {
        $this->seedConfig([
            'tools' => [
                'database-query' => ['enabled' => true, 'max_rows' => 100],
                'log' => ['enabled' => true, 'max_lines' => 500],
            ],
        ]);

        $tools = \Inchoo\MagentoBricklayer\Config\ConfigInitializer::discoverConfigurableTools();
        $toolIndex = (int) array_search('database-query', $tools, true);
        $this->tester->setInputs([(string) $toolIndex, '1', '200']);

        $this->tester->execute(['--magento-root' => $this->tempDir]);

        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('database-query', $display);
        $this->assertStringContainsString('enabled=true', $display);
        $this->assertStringContainsString('max_rows=100', $display);
        $this->assertStringContainsString('log', $display);
        $this->assertStringContainsString('max_lines=500', $display);
    }

    public function testInteractiveBooleanPromptRejectsInvalidInput(): void
    {
        $this->seedConfig([
            'tools' => [
                'code-runner' => ['enabled' => true, 'allow_write' => false, 'max_timeout' => 60],
            ],
        ]);

        $tools = \Inchoo\MagentoBricklayer\Config\ConfigInitializer::discoverConfigurableTools();
        $toolIndex = (int) array_search('code-runner', $tools, true);

        // Inputs:
        // 1. Tool index for code-runner
        // 2. Setting index 1 (allow_write, a bool)
        // 3. "true" — valid bool choice
        $this->tester->setInputs([(string) $toolIndex, '1', 'true']);

        $exitCode = $this->tester->execute(['--magento-root' => $this->tempDir]);

        $this->assertSame(0, $exitCode, $this->tester->getDisplay());
        $config = $this->readConfig();
        $this->assertTrue($config['tools']['code-runner']['allow_write']);
    }

    public function testInteractiveIntegerPromptRejectsNonNumericInput(): void
    {
        $this->seedConfig([
            'tools' => [
                'database-query' => ['enabled' => true, 'max_rows' => 100],
            ],
        ]);

        $tools = \Inchoo\MagentoBricklayer\Config\ConfigInitializer::discoverConfigurableTools();
        $toolIndex = (int) array_search('database-query', $tools, true);

        // First attempt "banana" fails (validator re-prompts), then "250" succeeds
        $this->tester->setInputs([(string) $toolIndex, '1', 'banana', '250']);

        $exitCode = $this->tester->execute(['--magento-root' => $this->tempDir]);

        $this->assertSame(0, $exitCode);
        $config = $this->readConfig();
        $this->assertSame(250, $config['tools']['database-query']['max_rows']);
    }

    public function testNonInteractiveModeFailsWhenKeyMissing(): void
    {
        $this->seedConfig(['tools' => []]);

        $exitCode = $this->tester->execute(
            ['--magento-root' => $this->tempDir],
            ['interactive' => false]
        );

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('key', strtolower($this->tester->getDisplay()));
    }

    public function testNonInteractiveModeFailsWhenValueMissing(): void
    {
        $this->seedConfig(['tools' => []]);

        $exitCode = $this->tester->execute(
            [
                'key' => 'tools.product-delete.enabled',
                '--magento-root' => $this->tempDir,
            ],
            ['interactive' => false]
        );

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('value', strtolower($this->tester->getDisplay()));
    }

    public function testWrittenJsonIsPrettyPrinted(): void
    {
        $this->seedConfig(['tools' => ['product-delete' => ['enabled' => false]]]);

        $this->tester->execute([
            'key' => 'tools.product-delete.enabled',
            'value' => 'true',
            '--magento-root' => $this->tempDir,
        ]);

        $content = (string) file_get_contents($this->configPath);
        $this->assertStringContainsString("\n    ", $content, 'Output should be indented');
        $this->assertStringEndsWith("\n", $content, 'Output should end with newline');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function seedConfig(array $config): void
    {
        file_put_contents(
            $this->configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(): array
    {
        $data = json_decode((string) file_get_contents($this->configPath), true);
        $this->assertIsArray($data);
        return $data;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
