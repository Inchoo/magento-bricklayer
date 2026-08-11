<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use Inchoo\MagentoBricklayer\Integration\McpConfigWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Install Command
 *
 * Generates agent configuration files for AI tools integration.
 */
#[AsCommand(
    name: 'install',
    description: 'Generate agent configuration files for AI tools integration'
)]
class InstallCommand extends AbstractBricklayerCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption(
                'agents',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Agents to configure (claude-code, cursor, copilot, phpstorm, gemini, codex, mistral-vibe)'
            )
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Environment type (native, docker, docker-compose, ddev, hooli, warden)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing configuration files'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command generates configuration files for AI coding agents:

  <info>%command.full_name%</info>

When run without options, you will be prompted to select the environment type and
which agents to configure.

Available agents:
  - <comment>claude-code</comment> - Creates CLAUDE.md
  - <comment>cursor</comment> - Creates .cursorrules
  - <comment>copilot</comment> - Creates .github/copilot-instructions.md
  - <comment>phpstorm</comment> - Creates .junie/guidelines.md
  - <comment>gemini</comment> - Creates AGENTS.md
  - <comment>codex</comment> - Creates AGENTS.md and .codex/config.toml
  - <comment>mistral-vibe</comment> - Creates AGENTS.md and .vibe/config.toml

You can also specify options directly via command line:

  <info>%command.full_name% --env=hooli --agents=claude-code</info>
  <info>%command.full_name% --env=ddev --agents=claude-code --agents=cursor</info>

Use the <comment>--force</comment> option to overwrite existing files:

  <info>%command.full_name% --force</info>
HELP
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Magento Bricklayer Installation');

        $magentoRoot = $this->resolveMagentoRoot($input, $io);
        if ($magentoRoot === null) {
            return Command::FAILURE;
        }

        $detector = new MagentoDetector();
        if (!$detector->isValidMagentoRoot($magentoRoot)) {
            $io->error("Invalid Magento root: $magentoRoot");
            return Command::FAILURE;
        }

        // Display detected Magento info
        $version = $detector->getVersion($magentoRoot) ?? 'unknown';
        $edition = ucfirst($detector->getEdition($magentoRoot));
        $detectedEnvType = $detector->getEnvironmentType($magentoRoot);

        $io->text([
            sprintf('Detected Magento: <info>%s</info> (%s Edition)', $version, $edition),
            sprintf('Project root: <info>%s</info>', $magentoRoot),
            '',
        ]);

        // Determine environment type
        $envType = $input->getOption('env');

        if ($envType === null) {
            $availableEnvTypes = $this->buildEnvTypeLabels();

            $choices = array_values($availableEnvTypes);
            $defaultLabel = $availableEnvTypes[$detectedEnvType] ?? $availableEnvTypes['native'];
            $defaultIndex = array_search($defaultLabel, $choices, true);

            $selectedLabel = $io->choice(
                'Select your environment type',
                $choices,
                $defaultIndex !== false ? $defaultIndex : 0
            );

            $labelToKey = array_flip($availableEnvTypes);
            $envType = $labelToKey[$selectedLabel];
            $io->newLine();
        }

        $io->text(sprintf('Environment: <info>%s</info>', ucfirst($envType)));
        $io->newLine();

        // Get agents to configure
        $agents = $input->getOption('agents');
        $force = $input->getOption('force');

        // If no agents specified, ask the user which ones to install
        if (empty($agents)) {
            $compiler = new GuidelinesCompiler($magentoRoot);
            $availableAgents = $this->buildAgentLabels($compiler);

            $choices = array_values($availableAgents);
            $question = new ChoiceQuestion(
                'Which AI agents do you want to configure? (comma-separated numbers for multiple)',
                $choices
            );
            $question->setMultiselect(true);
            $selected = $io->askQuestion($question);

            // Map selected labels back to agent keys
            $labelToKey = array_flip($availableAgents);
            $agents = array_map(fn($label) => $labelToKey[$label], $selected);

            if (empty($agents)) {
                $io->warning('No agents selected. Exiting.');
                return Command::SUCCESS;
            }

            $io->newLine();
        }

        // Check for existing files and ask about overwriting if --force not specified
        if (!$force) {
            $compiler = new GuidelinesCompiler($magentoRoot);
            $existingFiles = [];

            // Check MCP config
            if (file_exists($magentoRoot . '/.mcp.json')) {
                $existingFiles[] = '.mcp.json';
            }

            // Check agent config files (gemini, codex and mistral-vibe share AGENTS.md)
            foreach ($agents as $agent) {
                $filename = $compiler->getFilename($agent);
                if (file_exists($magentoRoot . '/' . $filename) && !in_array($filename, $existingFiles, true)) {
                    $existingFiles[] = $filename;
                }
            }

            // Check Codex MCP config
            if (in_array('codex', $agents, true) && file_exists($magentoRoot . '/.codex/config.toml')) {
                $existingFiles[] = '.codex/config.toml';
            }

            // Check Mistral Vibe MCP config
            if (in_array('mistral-vibe', $agents, true) && file_exists($magentoRoot . '/.vibe/config.toml')) {
                $existingFiles[] = '.vibe/config.toml';
            }

            if (!empty($existingFiles)) {
                $io->warning('The following files already exist:');
                foreach ($existingFiles as $file) {
                    $io->text("  - $file");
                }
                $io->newLine();

                $force = $io->confirm('Do you want to overwrite these files?', false);

                if (!$force) {
                    $io->text('<comment>Existing files will be skipped.</comment>');
                }
                $io->newLine();
            }
        }

        $io->section('Generating agent configurations...');

        $configWriter = new McpConfigWriter($magentoRoot);

        $createdFiles = [];
        $skippedFiles = [];

        // Generate MCP configuration
        $this->writeMcpConfigFile(
            $magentoRoot,
            '.mcp.json',
            fn() => $configWriter->writeMcpConfig($envType),
            $force,
            $envType,
            $createdFiles,
            $skippedFiles
        );

        // Generate .bricklayer.json configuration
        $initCommand = $this->getApplication()->find('init');
        $initArgs = ['--magento-root' => $magentoRoot];
        if ($force) {
            $initArgs['--force'] = true;
        }
        $initCommand->run(new ArrayInput($initArgs), $output);

        // Generate PhpStorm MCP config when phpstorm agent is selected
        if (in_array('phpstorm', $agents, true)) {
            $this->writeMcpConfigFile(
                $magentoRoot,
                '.idea/mcp.json',
                fn() => $configWriter->writePhpStormConfig($envType),
                $force,
                $envType,
                $createdFiles,
                $skippedFiles
            );
        }

        // Generate Codex MCP config when codex agent is selected
        if (in_array('codex', $agents, true)) {
            $this->writeMcpConfigFile(
                $magentoRoot,
                '.codex/config.toml',
                fn() => $configWriter->writeCodexConfig($envType),
                $force,
                $envType,
                $createdFiles,
                $skippedFiles
            );
        }

        // Generate Mistral Vibe MCP config when mistral-vibe agent is selected
        if (in_array('mistral-vibe', $agents, true)) {
            $this->writeMcpConfigFile(
                $magentoRoot,
                '.vibe/config.toml',
                fn() => $configWriter->writeVibeConfig($envType),
                $force,
                $envType,
                $createdFiles,
                $skippedFiles
            );
        }

        // Generate agent-specific files; gemini, codex and mistral-vibe share
        // AGENTS.md, so write each distinct file only once
        $generatedAgentFiles = [];
        foreach ($agents as $agent) {
            $filename = (new GuidelinesCompiler($magentoRoot))->getFilename($agent);
            if (in_array($filename, $generatedAgentFiles, true)) {
                continue;
            }
            $generatedAgentFiles[] = $filename;

            $result = $this->generateAgentConfig($magentoRoot, $agent, $force, $envType);
            if ($result['created']) {
                $createdFiles[] = $result['file'];
            } else {
                $skippedFiles[] = $result['file'] . ' (exists, use --force to overwrite)';
            }
        }

        // Display results
        foreach ($createdFiles as $file) {
            $io->text("  <info>\u{2713}</info> Created $file");
        }

        foreach ($skippedFiles as $file) {
            $io->text("  <comment>\u{2717}</comment> Skipped $file");
        }

        $io->newLine();

        // Display setup instructions
        if (!empty($createdFiles)) {
            $io->section('Setup instructions');

            $instructions = [];
            if (in_array('claude-code', $agents, true)) {
                $instructions[] = 'Claude Code: Configuration applied automatically';
            }
            if (in_array('cursor', $agents, true)) {
                $instructions[] = 'Cursor: Open Settings → MCP Servers → Enable "magento-bricklayer"';
            }
            if (in_array('phpstorm', $agents, true)) {
                $instructions[] = 'PhpStorm: Settings → Tools → AI Assistant → MCP Servers → Enable';
            }
            if (in_array('codex', $agents, true)) {
                $instructions[] = 'Codex: Mark the project as trusted so .codex/config.toml is loaded';
            }
            if (in_array('mistral-vibe', $agents, true)) {
                $instructions[] = 'Mistral Vibe: Configuration applied automatically (.vibe/config.toml is picked up on next start)';
            }

            foreach ($instructions as $instruction) {
                $io->text("  $instruction");
            }
        }

        $io->newLine();
        $io->success('Installation complete!');

        // Run verification
        $verifyCommand = $this->getApplication()->find('verify');
        $verifyInput = new ArrayInput(['--magento-root' => $magentoRoot]);
        $verifyCommand->run($verifyInput, $output);

        return Command::SUCCESS;
    }

    /**
     * Build env-type key→label map from the single authoritative source.
     *
     * @return array<string, string>
     */
    private function buildEnvTypeLabels(): array
    {
        return McpConfigWriter::getEnvironmentTypeLabels();
    }

    /**
     * Build agent key→label map from GuidelinesCompiler as the single source for filenames.
     *
     * @return array<string, string>
     */
    private function buildAgentLabels(GuidelinesCompiler $compiler): array
    {
        $agents = ['claude-code', 'cursor', 'copilot', 'phpstorm', 'gemini', 'codex', 'mistral-vibe'];
        $map = [];
        foreach ($agents as $agent) {
            $filename = $compiler->getFilename($agent);
            $map[$agent] = match ($agent) {
                'claude-code' => "Claude Code ($filename)",
                'cursor' => "Cursor ($filename)",
                'copilot' => "GitHub Copilot ($filename)",
                'phpstorm' => "PhpStorm/JetBrains ($filename)",
                'gemini' => "Google Gemini ($filename)",
                'codex' => "OpenAI Codex ($filename + .codex/config.toml)",
                'mistral-vibe' => "Mistral Vibe ($filename + .vibe/config.toml)",
            };
        }
        return $map;
    }

    private function generateAgentConfig(string $projectRoot, string $agent, bool $force, string $envType = 'native'): array
    {
        $compiler = new GuidelinesCompiler($projectRoot);
        $filename = $compiler->getFilename($agent);
        $filepath = $projectRoot . '/' . $filename;

        // Create directory if needed
        $dir = dirname($filepath);
        if (!is_dir($dir) && $dir !== $projectRoot) {
            mkdir($dir, 0755, true);
        }

        if (!$force && file_exists($filepath)) {
            return ['created' => false, 'file' => $filename];
        }

        $content = $compiler->compile($agent, $envType);
        file_put_contents($filepath, $content);

        return ['created' => true, 'file' => $filename];
    }

    /**
     * Write an MCP config file unless it already exists and --force is not
     * set, recording the outcome in the created/skipped lists.
     *
     * @param callable(): void $writer
     * @param list<string> $createdFiles
     * @param list<string> $skippedFiles
     */
    private function writeMcpConfigFile(
        string $magentoRoot,
        string $relativePath,
        callable $writer,
        bool $force,
        string $envType,
        array &$createdFiles,
        array &$skippedFiles
    ): void {
        if (!$force && file_exists($magentoRoot . '/' . $relativePath)) {
            $skippedFiles[] = "$relativePath (exists, use --force to overwrite)";
            return;
        }

        $writer();
        $createdFiles[] = $relativePath . ($envType !== 'native' ? " (configured for $envType)" : '');
    }
}
