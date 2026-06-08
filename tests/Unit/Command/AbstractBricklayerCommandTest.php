<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Command;

use Inchoo\MagentoBricklayer\Command\AbstractBricklayerCommand;
use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use Inchoo\MagentoBricklayer\Integration\McpConfigWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AbstractBricklayerCommandTest extends TestCase
{
    /**
     * Concrete no-op subclass used for testing the abstract base.
     */
    private function buildConcreteCommand(): AbstractBricklayerCommand
    {
        return new class extends AbstractBricklayerCommand {
            protected function configure(): void
            {
                $this->setName('test:concrete');
                parent::configure();
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        };
    }

    // -------------------------------------------------------------------------
    // Requirement 1: it defines the magento-root option once in the base command
    // -------------------------------------------------------------------------

    public function testItDefinesTheMagentoRootOptionOnceInTheBaseCommand(): void
    {
        $command = $this->buildConcreteCommand();
        $definition = $command->getDefinition();

        $this->assertTrue(
            $definition->hasOption('magento-root'),
            'AbstractBricklayerCommand must define the magento-root option'
        );

        $option = $definition->getOption('magento-root');
        $this->assertSame('m', $option->getShortcut(), 'magento-root option must use -m shortcut');
        $this->assertTrue($option->isValueOptional(), 'magento-root must be VALUE_OPTIONAL');
        $this->assertFalse($option->isValueRequired(), 'magento-root must not be VALUE_REQUIRED');
        $this->assertStringContainsString('auto-detected', (string) $option->getDescription());
    }

    // -------------------------------------------------------------------------
    // Requirement 2: it resolves the magento root with rtrim and is_dir validation
    // -------------------------------------------------------------------------

    public function testItResolvesTheMagentoRootWithRtrimAndIsDirValidation(): void
    {
        $tmpDir = sys_get_temp_dir() . '/abs_cmd_test_' . uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            // A command that captures what resolveMagentoRoot() returns
            $command = new class extends AbstractBricklayerCommand {
                public ?string $resolvedRoot = null;

                protected function configure(): void
                {
                    $this->setName('test:rtrim');
                    parent::configure();
                }

                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    $io = new SymfonyStyle($input, $output);
                    $this->resolvedRoot = $this->resolveMagentoRoot($input, $io);
                    return Command::SUCCESS;
                }
            };

            $definition = $command->getDefinition();
            $input = new ArrayInput(['--magento-root' => $tmpDir . '/'], $definition);
            $input->setInteractive(false);
            $command->run($input, new NullOutput());

            $this->assertNotNull(
                $command->resolvedRoot,
                'resolveMagentoRoot must return a non-null string for a valid dir'
            );
            $this->assertStringEndsNotWith(
                '/',
                $command->resolvedRoot,
                'resolveMagentoRoot must rtrim trailing slashes'
            );
            $this->assertSame($tmpDir, $command->resolvedRoot);
        } finally {
            rmdir($tmpDir);
        }
    }

    // -------------------------------------------------------------------------
    // Requirement 3: it fails with the standard error when no root can be resolved
    // -------------------------------------------------------------------------

    public function testItFailsWithTheStandardErrorWhenNoRootCanBeResolved(): void
    {
        // A command that uses resolveMagentoRoot() and returns FAILURE when null
        $command = new class extends AbstractBricklayerCommand {
            protected function configure(): void
            {
                $this->setName('test:no-root');
                parent::configure();
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $io = new SymfonyStyle($input, $output);
                $root = $this->resolveMagentoRoot($input, $io);
                return $root === null ? Command::FAILURE : Command::SUCCESS;
            }
        };

        // Provide a non-existent path — resolveMagentoRoot must detect is_dir failure
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $exitCode = $tester->execute(['--magento-root' => '/this/path/does/not/exist/ever']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('not exist', $output);
    }

    // -------------------------------------------------------------------------
    // Requirement 4: it derives agent filenames from a single GuidelinesCompiler map
    // -------------------------------------------------------------------------

    public function testItDerivesAgentFilenamesFromASingleGuidelinesCompilerMap(): void
    {
        $compiler = new GuidelinesCompiler();

        // These are the canonical mappings — test that GuidelinesCompiler is the single source
        $this->assertSame('CLAUDE.md', $compiler->getFilename('claude-code'));
        $this->assertSame('.cursorrules', $compiler->getFilename('cursor'));
        $this->assertSame('.github/copilot-instructions.md', $compiler->getFilename('copilot'));
        $this->assertSame('.junie/guidelines.md', $compiler->getFilename('phpstorm'));
        $this->assertSame('AGENTS.md', $compiler->getFilename('gemini'));

        // UpdateCommand must not contain a hardcoded filename map — verify by checking
        // that UpdateCommand drives its loop via getFilename() rather than a local array
        $reflection = new \ReflectionClass(\Inchoo\MagentoBricklayer\Command\UpdateCommand::class);
        $source = file_get_contents((string) $reflection->getFileName());
        $this->assertIsString($source);

        // The old hardcoded map in UpdateCommand looked like: 'CLAUDE.md' => 'claude-code'
        // After refactoring, the filename strings should come from getFilename(), not be hardcoded
        $this->assertStringNotContainsString(
            "'CLAUDE.md' => 'claude-code'",
            $source,
            'UpdateCommand must not contain a hardcoded agent→filename map; use GuidelinesCompiler::getFilename()'
        );
    }

    // -------------------------------------------------------------------------
    // Requirement 5: it derives environment types from a single source
    // -------------------------------------------------------------------------

    public function testItDerivesEnvironmentTypesFromASingleSource(): void
    {
        $types = McpConfigWriter::getAvailableEnvironmentTypes();

        // McpConfigWriter::getAvailableEnvironmentTypes() is the single authoritative source
        $this->assertContains('native', $types);
        $this->assertContains('ddev', $types);
        $this->assertContains('docker', $types);
        $this->assertContains('docker-compose', $types);
        $this->assertContains('hooli', $types);
        $this->assertContains('warden', $types);

        // InstallCommand must not contain a hardcoded env-type array
        $reflection = new \ReflectionClass(\Inchoo\MagentoBricklayer\Command\InstallCommand::class);
        $source = file_get_contents((string) $reflection->getFileName());
        $this->assertIsString($source);

        // The old duplicated inline list in InstallCommand::execute() assigned a local
        // $availableEnvTypes variable directly in execute() without calling getAvailableEnvironmentTypes().
        // After the refactor the env type list+labels must be driven by McpConfigWriter.
        $this->assertStringContainsString(
            'McpConfigWriter::getEnvironmentTypeLabels()',
            $source,
            'InstallCommand must delegate env-type labels to McpConfigWriter::getEnvironmentTypeLabels()'
        );

        // InstallCommand must NOT define its own key→label mapping for env types
        $this->assertStringNotContainsString(
            "'native' => 'Native (no containers)'",
            $source,
            'InstallCommand must not have an inline env-type label map; labels belong in McpConfigWriter'
        );
    }

    // -------------------------------------------------------------------------
    // Requirement 6: it keeps each command's existing option and resolution behaviour
    // -------------------------------------------------------------------------

    public function testItKeepsEachCommandsExistingOptionAndResolutionBehaviour(): void
    {
        // Each of the 7 commands must extend AbstractBricklayerCommand
        $commandClasses = [
            \Inchoo\MagentoBricklayer\Command\ConfigSetCommand::class,
            \Inchoo\MagentoBricklayer\Command\InitCommand::class,
            \Inchoo\MagentoBricklayer\Command\InspectCommand::class,
            \Inchoo\MagentoBricklayer\Command\InstallCommand::class,
            \Inchoo\MagentoBricklayer\Command\McpServerCommand::class,
            \Inchoo\MagentoBricklayer\Command\UpdateCommand::class,
            \Inchoo\MagentoBricklayer\Command\VerifyCommand::class,
        ];

        foreach ($commandClasses as $class) {
            $this->assertTrue(
                is_subclass_of($class, AbstractBricklayerCommand::class),
                "$class must extend AbstractBricklayerCommand"
            );
        }
    }
}
