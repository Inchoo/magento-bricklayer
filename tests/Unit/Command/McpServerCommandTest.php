<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Command;

use Inchoo\MagentoBricklayer\Command\McpServerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class McpServerCommandTest extends TestCase
{
    private string $origMagentoRoot;

    protected function setUp(): void
    {
        $this->origMagentoRoot = (string) getenv('BRICKLAYER_MAGENTO_ROOT');
        putenv('BRICKLAYER_MAGENTO_ROOT');
        unset($_ENV['BRICKLAYER_MAGENTO_ROOT'], $_SERVER['BRICKLAYER_MAGENTO_ROOT']);
    }

    protected function tearDown(): void
    {
        if ($this->origMagentoRoot !== '') {
            putenv('BRICKLAYER_MAGENTO_ROOT=' . $this->origMagentoRoot);
            $_ENV['BRICKLAYER_MAGENTO_ROOT'] = $this->origMagentoRoot;
            $_SERVER['BRICKLAYER_MAGENTO_ROOT'] = $this->origMagentoRoot;
        } else {
            putenv('BRICKLAYER_MAGENTO_ROOT');
            unset($_ENV['BRICKLAYER_MAGENTO_ROOT'], $_SERVER['BRICKLAYER_MAGENTO_ROOT']);
        }
    }

    /**
     * Build a no-side-effects double of McpServerCommand.
     * Binary always "exists"; hasPcntl is configurable; passthru is a no-op.
     *
     * The spy object accumulates observations from the overridden hook methods.
     *
     * @return array{0: McpServerCommand, 1: \stdClass}
     */
    private function buildDouble(bool $simulatePcntl = false): array
    {
        $spy = new \stdClass();
        $spy->exportedEnv = [];
        $spy->pcntlEntered = false;
        $spy->pcntlEnv = null;
        $spy->passthruCommand = null;

        $command = new class ($simulatePcntl, $spy) extends McpServerCommand {
            private bool $simulatePcntl;
            private \stdClass $spy;

            public function __construct(bool $simulatePcntl, \stdClass $spy)
            {
                parent::__construct();
                $this->simulatePcntl = $simulatePcntl;
                $this->spy = $spy;
            }

            protected function mcpBinaryExists(string $path): bool
            {
                return true;
            }

            protected function hasPcntl(): bool
            {
                return $this->simulatePcntl;
            }

            protected function exportEnvironmentOverrides(string $name, string $value): void
            {
                $this->spy->exportedEnv[$name] = $value;
                parent::exportEnvironmentOverrides($name, $value);
            }

            /** @param array<string,string> $env */
            protected function doPcntlExec(string $binary, string $mcpServerPath, array $env): void
            {
                $this->spy->pcntlEntered = true;
                $this->spy->pcntlEnv = $env;
                // Return normally (simulates pcntl_exec failure)
            }

            protected function doPassthru(string $command, int &$exitCode): void
            {
                $this->spy->passthruCommand = $command;
                $exitCode = 0;
            }
        };

        $command->setName('mcp');

        return [$command, $spy];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runCommand(McpServerCommand $command, array $options = []): int
    {
        $definition = $command->getDefinition();
        $input = new ArrayInput($options, $definition);
        $input->setInteractive(false);
        $output = new NullOutput();
        return $command->run($input, $output);
    }

    // -------------------------------------------------------------------------
    // Requirement 1: it exports BRICKLAYER_MAGENTO_ROOT before the passthru fallback
    // -------------------------------------------------------------------------

    public function testItExportsBricklayerMagentoRootBeforeThePassthruFallback(): void
    {
        [$command, $spy] = $this->buildDouble(simulatePcntl: false);
        $exitCode = $this->runCommand($command, ['--magento-root' => '/srv/magento']);

        $this->assertSame(0, $exitCode);

        // The env var must have been exported via exportEnvironmentOverrides
        $this->assertArrayHasKey('BRICKLAYER_MAGENTO_ROOT', $spy->exportedEnv);
        $this->assertSame('/srv/magento', $spy->exportedEnv['BRICKLAYER_MAGENTO_ROOT']);

        // putenv must have made it visible to getenv
        $this->assertSame('/srv/magento', getenv('BRICKLAYER_MAGENTO_ROOT'));

        // $_ENV and $_SERVER must also carry the value
        $this->assertSame('/srv/magento', $_ENV['BRICKLAYER_MAGENTO_ROOT'] ?? null);
        $this->assertSame('/srv/magento', $_SERVER['BRICKLAYER_MAGENTO_ROOT'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Requirement 2: it honours an explicit magento-root on the non-pcntl path
    // -------------------------------------------------------------------------

    public function testItHonoursAnExplicitMagentoRootOnTheNonPcntlPath(): void
    {
        [$command, $spy] = $this->buildDouble(simulatePcntl: false);
        $exitCode = $this->runCommand($command, ['--magento-root' => '/custom/magento']);

        $this->assertSame(0, $exitCode);
        $this->assertSame('/custom/magento', $spy->exportedEnv['BRICKLAYER_MAGENTO_ROOT'] ?? null);
        $this->assertSame('/custom/magento', getenv('BRICKLAYER_MAGENTO_ROOT'));
        $this->assertSame('/custom/magento', $_ENV['BRICKLAYER_MAGENTO_ROOT'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Requirement 3: it leaves the pcntl exec path behaviour unchanged
    // -------------------------------------------------------------------------

    public function testItLeavesThePcntlExecPathBehaviourUnchanged(): void
    {
        [$command, $spy] = $this->buildDouble(simulatePcntl: true);

        // Simulated doPcntlExec() returns normally (as if pcntl_exec failed)
        // so execute() continues to emit the error message and return FAILURE
        $exitCode = $this->runCommand($command, ['--magento-root' => '/pcntl/magento']);

        // The pcntl path was entered
        $this->assertTrue($spy->pcntlEntered);

        // On the pcntl path the env var is passed via the $env array, not via putenv
        $this->assertIsArray($spy->pcntlEnv);
        $this->assertSame('/pcntl/magento', $spy->pcntlEnv['BRICKLAYER_MAGENTO_ROOT'] ?? null);

        // exportEnvironmentOverrides must NOT have been called on the pcntl path
        $this->assertArrayNotHasKey('BRICKLAYER_MAGENTO_ROOT', $spy->exportedEnv);

        // execute() must return FAILURE when doPcntlExec returns (original behaviour preserved)
        $this->assertSame(Command::FAILURE, $exitCode);
    }

    // -------------------------------------------------------------------------
    // Requirement 4: it falls back to auto-detection only when no magento-root is given
    // -------------------------------------------------------------------------

    public function testItFallsBackToAutoDetectionOnlyWhenNoMagentoRootIsGiven(): void
    {
        [$command, $spy] = $this->buildDouble(simulatePcntl: false);
        $exitCode = $this->runCommand($command, []);

        $this->assertSame(0, $exitCode);

        // No override should be exported
        $this->assertArrayNotHasKey('BRICKLAYER_MAGENTO_ROOT', $spy->exportedEnv);
        $this->assertFalse(getenv('BRICKLAYER_MAGENTO_ROOT'));
        $this->assertArrayNotHasKey('BRICKLAYER_MAGENTO_ROOT', $_ENV);
        $this->assertArrayNotHasKey('BRICKLAYER_MAGENTO_ROOT', $_SERVER);
    }
}
