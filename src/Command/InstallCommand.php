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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Install Command
 *
 * Generates agent configuration files for AI tools integration.
 */
class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'install';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Generate agent configuration files for AI tools integration';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'magento-root',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Path to Magento root directory (auto-detected if not specified)'
            )
            ->addOption(
                'agents',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Agents to configure (claude-code, cursor, copilot, phpstorm, gemini)'
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

When run without options, you will be prompted to select which agents to configure.

Available agents:
  - <comment>claude-code</comment> - Creates CLAUDE.md
  - <comment>cursor</comment> - Creates .cursorrules
  - <comment>copilot</comment> - Creates .github/copilot-instructions.md
  - <comment>phpstorm</comment> - Creates .junie/guidelines.md
  - <comment>gemini</comment> - Creates AGENTS.md

You can also specify agents directly via command line:

  <info>%command.full_name% --agents=claude-code</info>
  <info>%command.full_name% --agents=claude-code --agents=cursor</info>

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

        // Detect Magento root
        $detector = new MagentoDetector();
        $magentoRoot = $input->getOption('magento-root');

        if ($magentoRoot === null) {
            $magentoRoot = $detector->detect();
        }

        if ($magentoRoot === null) {
            $io->error('Could not detect Magento installation. Please specify --magento-root option.');
            return Command::FAILURE;
        }

        if (!$detector->isValidMagentoRoot($magentoRoot)) {
            $io->error("Invalid Magento root: $magentoRoot");
            return Command::FAILURE;
        }

        // Display detected environment
        $version = $detector->getVersion($magentoRoot) ?? 'unknown';
        $edition = ucfirst($detector->getEdition($magentoRoot));
        $envType = $detector->getEnvironmentType($magentoRoot);

        $io->text([
            sprintf('Detected environment: <info>%s</info>', ucfirst($envType)),
            sprintf('Detected Magento: <info>%s</info> (%s Edition)', $version, $edition),
            sprintf('Project root: <info>%s</info>', $magentoRoot),
            '',
        ]);

        // Get agents to configure
        $agents = $input->getOption('agents');
        $force = $input->getOption('force');

        // If no agents specified, ask the user which ones to install
        if (empty($agents)) {
            $availableAgents = [
                'claude-code' => 'Claude Code (CLAUDE.md)',
                'cursor' => 'Cursor (.cursorrules)',
                'copilot' => 'GitHub Copilot (.github/copilot-instructions.md)',
                'phpstorm' => 'PhpStorm/JetBrains (.junie/guidelines.md)',
                'gemini' => 'Google Gemini (AGENTS.md)',
            ];

            $choices = array_values($availableAgents);
            $selected = $io->choice(
                'Which AI agents do you want to configure? (comma-separated numbers for multiple)',
                $choices,
                null,
                true  // multiSelect
            );

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
            $compiler = new GuidelinesCompiler();
            $existingFiles = [];

            // Check MCP config
            if (file_exists($magentoRoot . '/.mcp.json')) {
                $existingFiles[] = '.mcp.json';
            }

            // Check agent config files
            foreach ($agents as $agent) {
                $filename = $compiler->getFilename($agent);
                if (file_exists($magentoRoot . '/' . $filename)) {
                    $existingFiles[] = $filename;
                }
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
        $mcpConfigPath = $magentoRoot . '/.mcp.json';
        if ($force || !file_exists($mcpConfigPath)) {
            $configWriter->writeMcpConfig($envType);
            $createdFiles[] = '.mcp.json' . ($envType !== 'native' ? " (configured for $envType)" : '');
        } else {
            $skippedFiles[] = '.mcp.json (exists, use --force to overwrite)';
        }

        // Generate agent-specific files
        foreach ($agents as $agent) {
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

            foreach ($instructions as $instruction) {
                $io->text("  $instruction");
            }
        }

        $io->newLine();
        $io->success('Installation complete!');

        return Command::SUCCESS;
    }

    /**
     * Generate agent-specific configuration file
     *
     * @param string $projectRoot The project root directory
     * @param string $agent The agent name
     * @param bool $force Whether to overwrite existing files
     * @param string $envType The environment type (native, docker-compose, ddev, hooli, warden)
     * @return array{created: bool, file: string}
     */
    private function generateAgentConfig(string $projectRoot, string $agent, bool $force, string $envType = 'native'): array
    {
        $compiler = new GuidelinesCompiler();
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
}
