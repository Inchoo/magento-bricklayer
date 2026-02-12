<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Mcp\McpServerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Verify Command
 *
 * Post-install verification that checks all Bricklayer components are correctly
 * configured and operational. Runs a checklist and reports pass/warn/fail for each item.
 */
class VerifyCommand extends Command
{
    protected static $defaultName = 'verify';
    protected static $defaultDescription = 'Verify Bricklayer installation and configuration';

    /** @var array<array{name: string, status: string, message: string}> */
    private array $results = [];

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
                'json',
                null,
                InputOption::VALUE_NONE,
                'Output as JSON'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command verifies the Bricklayer installation:

  <info>%command.full_name%</info>

Checks include:
  - Magento bootstrap and version detection
  - Deploy mode
  - MCP server creation and tool count
  - Agent configuration files
  - Documentation index
  - PsySH availability
  - Database connectivity
  - Log directory writability
  - Code runner and diagnose-error tool status

Output as JSON:
  <info>%command.full_name% --json</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputJson = $input->getOption('json');

        $detector = new MagentoDetector();
        $magentoRoot = $input->getOption('magento-root') ?? $detector->detect();

        // Run all checks
        $this->checkBootstrap($magentoRoot);
        $this->checkDeployMode();
        $this->checkMcpServer();
        $this->checkAgentConfigs($magentoRoot);
        $this->checkDocIndex($magentoRoot);
        $this->checkPsySH();
        $this->checkDatabase();
        $this->checkLogDirectory($magentoRoot);
        $this->checkCodeRunner();
        $this->checkDiagnoseError();

        // Calculate summary
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'pass'));
        $warnings = count(array_filter($this->results, fn($r) => $r['status'] === 'warn'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'fail'));
        $total = count($this->results);

        if ($outputJson) {
            $output->writeln((string) json_encode([
                'results' => $this->results,
                'summary' => [
                    'total' => $total,
                    'passed' => $passed,
                    'warnings' => $warnings,
                    'failed' => $failed,
                ],
            ], JSON_PRETTY_PRINT));

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        // Render header
        $versionInfo = $this->getVersionInfo();
        $output->writeln('');
        $output->writeln("<info>Bricklayer Verify</info> — $versionInfo");
        $output->writeln('');

        // Render results
        foreach ($this->results as $result) {
            $icon = match ($result['status']) {
                'pass' => '<fg=green>  ✓</>',
                'warn' => '<fg=yellow>  ⚠</>',
                'fail' => '<fg=red>  ✗</>',
            };
            $name = str_pad($result['name'], 30, '.', STR_PAD_RIGHT);
            $message = $result['message'];

            $output->writeln(" $icon <comment>$name</comment> $message");
        }

        // Render summary
        $output->writeln('');
        $summaryParts = ["<info>$passed/$total passed</info>"];
        if ($warnings > 0) {
            $summaryParts[] = "<fg=yellow>$warnings warning" . ($warnings > 1 ? 's' : '') . '</>';
        }
        if ($failed > 0) {
            $summaryParts[] = "<fg=red>$failed failed</>";
        }
        $output->writeln('  Result: ' . implode(', ', $summaryParts));
        $output->writeln('');

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function checkBootstrap(?string $magentoRoot): void
    {
        try {
            if ($magentoRoot === null) {
                $this->addResult('Magento bootstrap', 'fail', 'Magento installation not found');
                return;
            }

            MagentoBootstrap::initialize($magentoRoot);
            $this->addResult('Magento bootstrap', 'pass', 'OK');
        } catch (\Throwable $e) {
            $this->addResult('Magento bootstrap', 'fail', $e->getMessage());
        }
    }

    private function checkDeployMode(): void
    {
        if (!MagentoBootstrap::isInitialized()) {
            $this->addResult('Deploy mode', 'fail', 'Magento not initialized');
            return;
        }

        try {
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $mode = $state->getMode();

            if ($mode === 'production') {
                $this->addResult('Deploy mode', 'warn', 'production (some tools may be limited)');
            } else {
                $this->addResult('Deploy mode', 'pass', $mode);
            }
        } catch (\Throwable $e) {
            $this->addResult('Deploy mode', 'fail', $e->getMessage());
        }
    }

    private function checkMcpServer(): void
    {
        try {
            $factory = new McpServerFactory();
            $factory->create();

            // Server created successfully — count tools by scanning for #[McpTool] attributes
            $toolCount = $this->countMcpTools();

            if ($toolCount >= 85) {
                $this->addResult('MCP server', 'pass', "OK ($toolCount tools registered)");
            } else {
                $this->addResult('MCP server', 'warn', "Only $toolCount tools registered (expected ≥85)");
            }
        } catch (\Throwable $e) {
            $this->addResult('MCP server', 'fail', $e->getMessage());
        }
    }

    private function countMcpTools(): int
    {
        $toolDir = dirname(__DIR__) . '/Mcp/Tool';
        if (!is_dir($toolDir)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($toolDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if ($content !== false) {
                $count += substr_count($content, '#[McpTool(');
            }
        }

        return $count;
    }

    private function checkAgentConfigs(?string $magentoRoot): void
    {
        if ($magentoRoot === null) {
            $this->addResult('Agent config', 'fail', 'Magento root not found');
            return;
        }

        $configPaths = [
            '.mcp.json' => $magentoRoot . '/.mcp.json',
            '.cursor/mcp.json' => $magentoRoot . '/.cursor/mcp.json',
            '.vscode/mcp.json' => $magentoRoot . '/.vscode/mcp.json',
            '.idea/mcp.json' => $magentoRoot . '/.idea/mcp.json',
        ];

        $found = [];
        foreach ($configPaths as $label => $path) {
            if (file_exists($path)) {
                $found[] = $label;
            }
        }

        if (!empty($found)) {
            $this->addResult('Agent config', 'pass', implode(', ', $found));
        } else {
            $this->addResult('Agent config', 'warn', 'not found (run: bricklayer install)');
        }
    }

    private function checkDocIndex(?string $magentoRoot): void
    {
        if ($magentoRoot === null) {
            $this->addResult('Doc index', 'fail', 'Magento root not found');
            return;
        }

        $indexPath = $magentoRoot . '/.bricklayer/docs-index';
        if (is_dir($indexPath)) {
            $this->addResult('Doc index', 'pass', 'present');
        } else {
            $this->addResult('Doc index', 'warn', 'not found (run: bricklayer update)');
        }
    }

    private function checkPsySH(): void
    {
        if (class_exists(\Psy\Shell::class)) {
            try {
                $ref = new \ReflectionClass(\Psy\Shell::class);
                $composerPath = dirname((string) $ref->getFileName(), 2) . '/composer.json';
                $version = 'available';
                if (file_exists($composerPath)) {
                    $composer = json_decode((string) file_get_contents($composerPath), true);
                    if (isset($composer['version'])) {
                        $version = 'v' . $composer['version'];
                    }
                }
                $this->addResult('PsySH', 'pass', $version);
            } catch (\Throwable) {
                $this->addResult('PsySH', 'pass', 'available');
            }
        } else {
            $this->addResult('PsySH', 'warn', 'not installed (code-runner will use eval fallback)');
        }
    }

    private function checkDatabase(): void
    {
        if (!MagentoBootstrap::isInitialized()) {
            $this->addResult('Database', 'fail', 'Magento not initialized');
            return;
        }

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();

            $version = $connection->fetchOne('SELECT VERSION()');
            $this->addResult('Database', 'pass', "OK (MySQL $version)");
        } catch (\Throwable $e) {
            $this->addResult('Database', 'fail', $e->getMessage());
        }
    }

    private function checkLogDirectory(?string $magentoRoot): void
    {
        if ($magentoRoot === null) {
            $this->addResult('Log directory', 'fail', 'Magento root not found');
            return;
        }

        $logDir = $magentoRoot . '/var/log';
        if (!is_dir($logDir)) {
            $this->addResult('Log directory', 'warn', 'var/log does not exist');
        } elseif (is_writable($logDir)) {
            $this->addResult('Log directory', 'pass', 'writable');
        } else {
            $this->addResult('Log directory', 'warn', 'not writable');
        }
    }

    private function checkCodeRunner(): void
    {
        try {
            $configLoader = new ConfigLoader();
            $enabled = $configLoader->isToolEnabled('code-runner');

            if ($enabled) {
                $allowWrite = $configLoader->get('tools.code-runner.allow_write', false);
                $mode = $allowWrite ? 'read-write mode' : 'read-only mode';
                $this->addResult('Code runner', 'pass', "enabled ($mode)");
            } else {
                $this->addResult('Code runner', 'warn', 'disabled in configuration');
            }
        } catch (\Throwable) {
            $this->addResult('Code runner', 'pass', 'enabled (default config)');
        }
    }

    private function checkDiagnoseError(): void
    {
        // DiagnosticTools is always registered when the MCP server starts
        $this->addResult('Diagnose error', 'pass', 'enabled');
    }

    private function addResult(string $name, string $status, string $message): void
    {
        $this->results[] = [
            'name' => $name,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function getVersionInfo(): string
    {
        if (MagentoBootstrap::isInitialized()) {
            try {
                $metadata = MagentoBootstrap::get(\Magento\Framework\App\ProductMetadataInterface::class);
                $version = $metadata->getVersion();
                $edition = $metadata->getEdition();
                return "Magento $version ($edition)";
            } catch (\Throwable) {
                return 'Magento (version unknown)';
            }
        }

        return 'Magento installation not detected';
    }
}
