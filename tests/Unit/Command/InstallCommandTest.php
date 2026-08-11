<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Command;

use Inchoo\MagentoBricklayer\Application;
use Inchoo\MagentoBricklayer\Command\VerifyCommand;
use Inchoo\MagentoBricklayer\Integration\McpConfigWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InstallCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/install_cmd_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    /**
     * Creates a synthetic Magento root by touching the 3 marker files
     * that MagentoDetector::isValidMagentoRoot() checks.
     */
    private function makeSyntheticMagentoRoot(): string
    {
        $root = $this->tmpDir . '/magento';
        mkdir($root . '/app/etc', 0755, true);
        mkdir($root . '/bin', 0755, true);
        touch($root . '/app/bootstrap.php');
        touch($root . '/app/etc/env.php');
        touch($root . '/bin/magento');
        return $root;
    }

    public function testItGeneratesAPhpstormMcpConfigDuringInstall(): void
    {
        // Run install command with phpstorm agent via the real Application (which wires
        // install → init → verify and provides getApplication() context).
        $magentoRoot = $this->makeSyntheticMagentoRoot();

        $app = new Application();
        $command = $app->find('install');
        $tester = new CommandTester($command);

        $tester->execute([
            '--magento-root' => $magentoRoot,
            '--agents' => ['phpstorm'],
            '--env' => 'native',
            '--force' => true,
        ]);

        $expectedPath = $magentoRoot . '/.idea/mcp.json';
        $this->assertFileExists(
            $expectedPath,
            'install with --agents=phpstorm must create .idea/mcp.json via writePhpStormConfig()'
        );

        $data = json_decode((string) file_get_contents($expectedPath), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('mcpServers', $data);
        $this->assertArrayHasKey('magento-bricklayer', $data['mcpServers']);
    }

    public function testItPassesTheVerifyCheckForThePhpstormConfigAfterInstall(): void
    {
        // writePhpStormConfig() produces .idea/mcp.json; the pre-existing
        // VerifyCommand::checkAgentConfigs() looks for that path. Verify the
        // check returns 'pass' (not 'warn') once the file exists.
        $writer = new McpConfigWriter($this->tmpDir);
        $writer->writePhpStormConfig('native');

        $command = new VerifyCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--json' => true, '--magento-root' => $this->tmpDir]);

        $data = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($data);

        $agentResult = null;
        foreach ($data['results'] as $result) {
            if ($result['name'] === 'Agent config') {
                $agentResult = $result;
                break;
            }
        }

        $this->assertNotNull($agentResult, 'Agent config check must appear in verify results');
        $this->assertSame('pass', $agentResult['status'], 'Agent config must pass when .idea/mcp.json exists');
        $this->assertStringContainsString('.idea/mcp.json', $agentResult['message']);
    }

    public function testItGeneratesACodexTomlConfigDuringInstall(): void
    {
        $magentoRoot = $this->makeSyntheticMagentoRoot();

        $app = new Application();
        $command = $app->find('install');
        $tester = new CommandTester($command);

        $tester->execute([
            '--magento-root' => $magentoRoot,
            '--agents' => ['codex'],
            '--env' => 'native',
            '--force' => true,
        ]);

        $expectedPath = $magentoRoot . '/.codex/config.toml';
        $this->assertFileExists(
            $expectedPath,
            'install with --agents=codex must create .codex/config.toml via writeCodexConfig()'
        );

        $toml = (string) file_get_contents($expectedPath);
        $this->assertStringContainsString('[mcp_servers.magento-bricklayer]', $toml);
        $this->assertStringContainsString('command = "php"', $toml);
        $this->assertStringContainsString('args = ["vendor/bin/bricklayer-mcp"]', $toml);
        $this->assertStringContainsString('required = true', $toml);
        $this->assertStringContainsString('startup_timeout_sec = 30', $toml);
        $this->assertStringContainsString('tool_timeout_sec = 120', $toml);

        $this->assertFileExists(
            $magentoRoot . '/AGENTS.md',
            'install with --agents=codex must also compile AGENTS.md guidelines'
        );
    }

    public function testItPreservesUserSettingsWhenUpdatingAnExistingCodexConfig(): void
    {
        $existing = <<<TOML
        model = "gpt-5"

        [mcp_servers.magento-bricklayer]
        command = "stale"
        args = ["old"]

        [mcp_servers.other-server]
        command = "node"
        args = ["server.js"]
        TOML;

        mkdir($this->tmpDir . '/.codex', 0755, true);
        file_put_contents($this->tmpDir . '/.codex/config.toml', $existing . "\n");

        $writer = new McpConfigWriter($this->tmpDir);
        $writer->writeCodexConfig('ddev');

        $toml = (string) file_get_contents($this->tmpDir . '/.codex/config.toml');

        // User-managed settings survive
        $this->assertStringContainsString('model = "gpt-5"', $toml);
        $this->assertStringContainsString('[mcp_servers.other-server]', $toml);
        $this->assertStringContainsString('args = ["server.js"]', $toml);

        // Stale bricklayer section fully replaced, not duplicated, no orphan leftovers
        $this->assertSame(1, substr_count($toml, '[mcp_servers.magento-bricklayer]'));
        $this->assertStringNotContainsString('command = "stale"', $toml);
        $this->assertStringNotContainsString('["old"]', $toml);
        $this->assertStringContainsString('command = "ddev"', $toml);
        $this->assertStringContainsString('args = ["exec", "php", "vendor/bin/bricklayer-mcp"]', $toml);

        // Idempotent: a second run must not change the file
        $writer->writeCodexConfig('ddev');
        $this->assertSame($toml, (string) file_get_contents($this->tmpDir . '/.codex/config.toml'));
    }

    public function testItExposesAvailableEnvironmentTypesFromASingleSource(): void
    {
        $types = McpConfigWriter::getAvailableEnvironmentTypes();

        $this->assertIsArray($types);
        $this->assertNotEmpty($types);

        // Must include all known environment types
        $this->assertContains('native', $types);
        $this->assertContains('ddev', $types);
        $this->assertContains('docker', $types);
        $this->assertContains('docker-compose', $types);
        $this->assertContains('hooli', $types);
        $this->assertContains('warden', $types);

        // Must be the authoritative list — all types supported by getMcpServerConfig()
        $this->assertCount(6, $types);
    }

    public function testItNoLongerReferencesTheRemovedToolExceptionClassAnywhere(): void
    {
        // With autoload=true: class_exists returns false when the file doesn't exist.
        // This confirms the ToolException class has been removed from the codebase.
        $this->assertFalse(
            class_exists(\Inchoo\MagentoBricklayer\Exception\ToolException::class, true),
            'ToolException must no longer exist (the class and file must have been removed)'
        );
    }

    public function testItBootstrapsMagentoWithoutTheRemovedAreaEmulatorAccessorAndProperty(): void
    {
        $this->assertFalse(
            method_exists(\Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap::class, 'getAreaEmulator'),
            'getAreaEmulator() must have been removed from MagentoBootstrap'
        );

        $this->assertFalse(
            method_exists(\Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap::class, 'getDetector'),
            'getDetector() must have been removed from MagentoBootstrap'
        );
    }

    public function testItKeepsTheCreateAndEmulateAreaApisIntact(): void
    {
        // MagentoBootstrap::create() must remain as plausible intentional API
        $this->assertTrue(
            method_exists(\Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap::class, 'create'),
            'create() must be kept on MagentoBootstrap'
        );

        // AreaEmulator::emulateArea() must remain as templated callback wrapper
        $this->assertTrue(
            method_exists(\Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator::class, 'emulateArea'),
            'emulateArea() must be kept on AreaEmulator'
        );
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
