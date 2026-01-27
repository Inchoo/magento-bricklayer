<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MCP Server Command
 *
 * Starts the Magento Bricklayer MCP server for AI agent communication.
 * This command is typically invoked by AI agents, not directly by users.
 */
class McpServerCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'mcp';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Start the MCP server for AI agent communication';

    /**
     * @inheritdoc
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
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command starts the MCP (Model Context Protocol) server.

This server is typically started automatically by AI coding agents such as
Claude Code, Cursor, or other MCP-compatible tools. You generally don't need
to run this command directly.

The server communicates via stdio (stdin/stdout) using JSON-RPC 2.0 protocol.

Example:
  <info>%command.full_name%</info>

With explicit Magento root:
  <info>%command.full_name% --magento-root=/var/www/magento</info>
HELP
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // The actual MCP server runs via bin/bricklayer-mcp
        // This command provides a wrapper for consistency with other commands
        $mcpServerPath = dirname(__DIR__, 2) . '/bin/bricklayer-mcp';

        if (!file_exists($mcpServerPath)) {
            $output->writeln('<error>MCP server binary not found at: ' . $mcpServerPath . '</error>');
            return Command::FAILURE;
        }

        $magentoRoot = $input->getOption('magento-root');
        $env = $_ENV;

        if ($magentoRoot !== null) {
            $env['BRICKLAYER_MAGENTO_ROOT'] = $magentoRoot;
        }

        // Check if pcntl is available for exec replacement
        if (function_exists('pcntl_exec')) {
            // Replace current process with MCP server
            pcntl_exec(PHP_BINARY, [$mcpServerPath], $env);

            // If pcntl_exec returns, it failed
            $output->writeln('<error>Failed to execute MCP server</error>');
            return Command::FAILURE;
        }

        // Fallback: use passthru for systems without pcntl
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($mcpServerPath);
        passthru($command, $exitCode);

        return $exitCode;
    }
}
