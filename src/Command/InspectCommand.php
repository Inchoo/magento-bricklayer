<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Inspect Command
 *
 * Displays information about the current Magento installation.
 */
class InspectCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'inspect';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Display information about the current Magento installation';

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
                'json',
                null,
                InputOption::VALUE_NONE,
                'Output as JSON'
            )
            ->addOption(
                'no-bootstrap',
                null,
                InputOption::VALUE_NONE,
                'Skip Magento bootstrap (faster but less detailed)'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command displays information about the Magento installation:

  <info>%command.full_name%</info>

This includes:
  - Magento version and edition
  - PHP version
  - Database information
  - Module counts
  - Cache and indexer status

Use <comment>--no-bootstrap</comment> for faster execution without full Magento initialization:

  <info>%command.full_name% --no-bootstrap</info>

Output as JSON:

  <info>%command.full_name% --json</info>
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
        $outputJson = $input->getOption('json');
        $noBootstrap = $input->getOption('no-bootstrap');

        $detector = new MagentoDetector();
        $magentoRoot = $input->getOption('magento-root') ?? $detector->detect();

        if ($magentoRoot === null) {
            if ($outputJson) {
                $output->writeln(json_encode(['error' => 'Magento installation not found']));
            } else {
                $io->error('Could not detect Magento installation. Please specify --magento-root option.');
            }
            return Command::FAILURE;
        }

        $info = $this->gatherBasicInfo($detector, $magentoRoot);

        if (!$noBootstrap) {
            try {
                MagentoBootstrap::initialize($magentoRoot);
                $info = array_merge($info, $this->gatherBootstrappedInfo());
            } catch (\Throwable $e) {
                $info['bootstrap_error'] = $e->getMessage();
            }
        }

        if ($outputJson) {
            $output->writeln(json_encode($info, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->displayInfo($io, $info);

        return Command::SUCCESS;
    }

    /**
     * Gather basic info without bootstrapping Magento
     *
     * @param MagentoDetector $detector
     * @param string $magentoRoot
     * @return array<string, mixed>
     */
    private function gatherBasicInfo(MagentoDetector $detector, string $magentoRoot): array
    {
        return [
            'magento_root' => $magentoRoot,
            'magento_version' => $detector->getVersion($magentoRoot) ?? 'unknown',
            'magento_edition' => $detector->getEdition($magentoRoot),
            'environment_type' => $detector->getEnvironmentType($magentoRoot),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
        ];
    }

    /**
     * Gather additional info after bootstrapping Magento
     *
     * @return array<string, mixed>
     */
    private function gatherBootstrappedInfo(): array
    {
        $info = [];

        try {
            // Get product metadata
            $metadata = MagentoBootstrap::get(\Magento\Framework\App\ProductMetadataInterface::class);
            $info['magento_version'] = $metadata->getVersion();
            $info['magento_edition'] = strtolower($metadata->getEdition());

            // Get deploy mode
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $info['deploy_mode'] = $state->getMode();

            // Get module counts
            $moduleList = MagentoBootstrap::get(\Magento\Framework\Module\ModuleListInterface::class);
            $fullModuleList = MagentoBootstrap::get(\Magento\Framework\Module\FullModuleList::class);

            $allModules = $fullModuleList->getNames();
            $enabledModules = array_keys($moduleList->getAll());
            $disabledModules = array_diff($allModules, $enabledModules);

            $customModules = array_filter($enabledModules, fn($name) => !str_starts_with($name, 'Magento_'));

            $info['modules'] = [
                'total' => count($allModules),
                'enabled' => count($enabledModules),
                'disabled' => count($disabledModules),
                'custom' => count($customModules),
            ];

            // Get store information
            $storeManager = MagentoBootstrap::get(\Magento\Store\Model\StoreManagerInterface::class);
            $info['stores'] = [
                'websites' => count($storeManager->getWebsites()),
                'stores' => count($storeManager->getStores()),
            ];

            // Get cache information
            $cacheTypeList = MagentoBootstrap::get(\Magento\Framework\App\Cache\TypeListInterface::class);
            $cacheTypes = $cacheTypeList->getTypes();
            $enabledCaches = array_filter($cacheTypes, fn($type) => $type->getStatus());

            $info['cache'] = [
                'types_total' => count($cacheTypes),
                'types_enabled' => count($enabledCaches),
            ];

            // Get indexer information
            $indexerCollection = MagentoBootstrap::get(\Magento\Indexer\Model\Indexer\CollectionFactory::class);
            $indexers = $indexerCollection->create();

            $validIndexers = 0;
            $invalidIndexers = 0;

            foreach ($indexers as $indexer) {
                if ($indexer->isValid()) {
                    $validIndexers++;
                } else {
                    $invalidIndexers++;
                }
            }

            $info['indexers'] = [
                'total' => count($indexers),
                'valid' => $validIndexers,
                'invalid' => $invalidIndexers,
            ];

        } catch (\Throwable $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    /**
     * Display information in formatted output
     *
     * @param SymfonyStyle $io
     * @param array<string, mixed> $info
     * @return void
     */
    private function displayInfo(SymfonyStyle $io, array $info): void
    {
        $io->title('Magento Installation Information');

        // Basic info
        $io->section('Environment');
        $io->table([], [
            ['Magento Root', $info['magento_root']],
            ['Magento Version', $info['magento_version']],
            ['Magento Edition', ucfirst($info['magento_edition'])],
            ['Deploy Mode', $info['deploy_mode'] ?? 'N/A'],
            ['Environment', ucfirst($info['environment_type'])],
            ['PHP Version', $info['php_version']],
        ]);

        // Modules
        if (isset($info['modules'])) {
            $io->section('Modules');
            $io->table([], [
                ['Total', $info['modules']['total']],
                ['Enabled', $info['modules']['enabled']],
                ['Disabled', $info['modules']['disabled']],
                ['Custom (Non-Magento)', $info['modules']['custom']],
            ]);
        }

        // Stores
        if (isset($info['stores'])) {
            $io->section('Stores');
            $io->table([], [
                ['Websites', $info['stores']['websites']],
                ['Store Views', $info['stores']['stores']],
            ]);
        }

        // Cache
        if (isset($info['cache'])) {
            $io->section('Cache');
            $io->table([], [
                ['Cache Types', $info['cache']['types_total']],
                ['Enabled', $info['cache']['types_enabled']],
            ]);
        }

        // Indexers
        if (isset($info['indexers'])) {
            $io->section('Indexers');
            $io->table([], [
                ['Total', $info['indexers']['total']],
                ['Valid', $info['indexers']['valid']],
                ['Invalid', $info['indexers']['invalid']],
            ]);
        }

        if (isset($info['bootstrap_error'])) {
            $io->warning('Bootstrap error: ' . $info['bootstrap_error']);
        }
    }
}
