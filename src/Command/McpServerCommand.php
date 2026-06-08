<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MCP Server Command
 *
 * Starts the Magento Bricklayer MCP server for AI agent communication.
 * This command is typically invoked by AI agents, not directly by users.
 */
#[AsCommand(
    name: 'mcp',
    description: 'Start the MCP server for AI agent communication'
)]
class McpServerCommand extends AbstractBricklayerCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mcpServerPath = dirname(__DIR__, 2) . '/bin/bricklayer-mcp';

        if (!$this->mcpBinaryExists($mcpServerPath)) {
            $output->writeln('<error>MCP server binary not found at: ' . $mcpServerPath . '</error>');
            return Command::FAILURE;
        }

        $magentoRoot = $input->getOption('magento-root');
        $env = $_ENV;

        if ($magentoRoot !== null) {
            $env['BRICKLAYER_MAGENTO_ROOT'] = $magentoRoot;
        }

        // Check if pcntl is available for exec replacement
        if ($this->hasPcntl()) {
            // Replace current process with MCP server.
            // The $env array already carries BRICKLAYER_MAGENTO_ROOT when --magento-root is given.
            $this->doPcntlExec(PHP_BINARY, $mcpServerPath, $env);

            // If doPcntlExec returns, pcntl_exec failed
            $output->writeln('<error>Failed to execute MCP server</error>');
            return Command::FAILURE;
        }

        // Fallback: use passthru for systems without pcntl.
        // Export the magento-root override so the child process can read it via getenv().
        if ($magentoRoot !== null) {
            $this->exportEnvironmentOverrides('BRICKLAYER_MAGENTO_ROOT', $magentoRoot);
        }

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($mcpServerPath);
        $exitCode = 0;
        $this->doPassthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Check whether the MCP server binary exists.
     * Extracted for testability.
     *
     * @param string $path
     * @return bool
     */
    protected function mcpBinaryExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Check whether pcntl_exec is available.
     * Extracted for testability.
     *
     * @return bool
     */
    protected function hasPcntl(): bool
    {
        return function_exists('pcntl_exec');
    }

    /**
     * Export an environment variable override so child processes can read it.
     * Sets putenv, $_ENV, and $_SERVER so all three lookup paths are covered.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    protected function exportEnvironmentOverrides(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Invoke pcntl_exec to replace the current process with the MCP server.
     * Returns only if pcntl_exec fails.
     * Extracted for testability.
     *
     * @param string $binary
     * @param string $mcpServerPath
     * @param array<string, string> $env
     * @return void
     */
    protected function doPcntlExec(string $binary, string $mcpServerPath, array $env): void
    {
        pcntl_exec($binary, [$mcpServerPath], $env);
    }

    /**
     * Execute a command via passthru.
     * Extracted for testability.
     *
     * @param string $command
     * @param int $exitCode
     * @return void
     */
    protected function doPassthru(string $command, int &$exitCode): void
    {
        passthru($command, $exitCode);
    }
}
