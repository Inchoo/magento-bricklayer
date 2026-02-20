<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

/**
 * Development Tools
 *
 * Provides MCP tools for Magento development operations.
 */
class DevelopmentTools
{
    use RequiresMagento;

    /**
     * Returns cache status for all cache types.
     *
     * @return array<string, mixed> Cache status information
     */
    #[McpTool(
        name: 'cache-status',
        description: 'Returns cache status for all Magento cache types'
    )]
    public function getCacheStatus(): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $cacheTypeList = MagentoBootstrap::get(\Magento\Framework\App\Cache\TypeListInterface::class);
            $cacheTypes = $cacheTypeList->getTypes();

            $types = [];
            foreach ($cacheTypes as $type) {
                $types[] = [
                    'id' => $type->getId(),
                    'label' => $type->getCacheType(),
                    'description' => $type->getDescription(),
                    'status' => $type->getStatus() ? 'enabled' : 'disabled',
                    'tags' => $type->getTags(),
                ];
            }

            $enabledCount = count(array_filter($types, fn($t) => $t['status'] === 'enabled'));

            return [
                'summary' => [
                    'total' => count($types),
                    'enabled' => $enabledCount,
                    'disabled' => count($types) - $enabledCount,
                ],
                'types' => $types,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns indexer status for all indexers.
     *
     * @return array<string, mixed> Indexer status information
     */
    #[McpTool(
        name: 'indexer-status',
        description: 'Returns status of all Magento indexers'
    )]
    public function getIndexerStatus(): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $indexerCollection = MagentoBootstrap::get(\Magento\Indexer\Model\Indexer\CollectionFactory::class);
            $collection = $indexerCollection->create();

            $indexers = [];
            foreach ($collection as $indexer) {
                $state = $indexer->getState();
                $status = $state->getStatus();

                $indexers[] = [
                    'indexer_id' => $indexer->getId(),
                    'title' => $indexer->getTitle(),
                    'description' => $indexer->getDescription(),
                    'status' => $status,
                    'status_label' => $this->getIndexerStatusLabel($status),
                    'is_scheduled' => $indexer->isScheduled(),
                    'mode' => $indexer->isScheduled() ? 'schedule' : 'realtime',
                    'updated' => $state->getUpdated(),
                ];
            }

            $invalidCount = count(array_filter(
                $indexers,
                fn($i) => $i['status'] === \Magento\Framework\Indexer\StateInterface::STATUS_INVALID
            ));

            return [
                'summary' => [
                    'total' => count($indexers),
                    'invalid' => $invalidCount,
                    'valid' => count($indexers) - $invalidCount,
                ],
                'indexers' => $indexers,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists configured cron jobs.
     *
     * @param string $group Filter by cron group (optional)
     * @return array<string, mixed> List of cron jobs
     */
    #[McpTool(
        name: 'cron-list',
        description: 'Lists all configured Magento cron jobs'
    )]
    public function getCronList(string $group = ''): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $cronConfig = MagentoBootstrap::get(\Magento\Cron\Model\ConfigInterface::class);
            $jobs = $cronConfig->getJobs();

            $cronJobs = [];
            foreach ($jobs as $groupCode => $groupJobs) {
                if ($group !== '' && $groupCode !== $group) {
                    continue;
                }

                foreach ($groupJobs as $jobCode => $jobConfig) {
                    $cronJobs[] = [
                        'group' => $groupCode,
                        'job_code' => $jobCode,
                        'instance' => $jobConfig['instance'] ?? null,
                        'method' => $jobConfig['method'] ?? null,
                        'schedule' => $jobConfig['schedule'] ?? ($jobConfig['config_path'] ?? null),
                    ];
                }
            }

            // Sort by group and job code
            usort($cronJobs, fn($a, $b) =>
                strcmp($a['group'], $b['group']) ?: strcmp($a['job_code'], $b['job_code'])
            );

            return [
                'total' => count($cronJobs),
                'filter_group' => $group ?: 'all',
                'jobs' => $cronJobs,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns recent cron job history.
     *
     * @param string $jobCode Filter by job code (optional)
     * @param int $limit Maximum number of records
     * @return array<string, mixed> Cron history
     */
    #[McpTool(
        name: 'cron-history',
        description: 'Returns recent cron job execution history'
    )]
    public function getCronHistory(string $jobCode = '', int $limit = 50): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('cron_schedule');

            $select = $connection->select()
                ->from($tableName)
                ->order('scheduled_at DESC')
                ->limit($limit);

            if ($jobCode !== '') {
                $select->where('job_code = ?', $jobCode);
            }

            $rows = $connection->fetchAll($select);

            $history = [];
            foreach ($rows as $row) {
                $history[] = [
                    'schedule_id' => (int) $row['schedule_id'],
                    'job_code' => $row['job_code'],
                    'status' => $row['status'],
                    'scheduled_at' => $row['scheduled_at'],
                    'executed_at' => $row['executed_at'],
                    'finished_at' => $row['finished_at'],
                    'messages' => $row['messages'] ? substr($row['messages'], 0, 200) : null,
                ];
            }

            return [
                'total' => count($history),
                'filter_job' => $jobCode ?: 'all',
                'history' => $history,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns deploy mode and related configuration.
     *
     * @return array<string, mixed> Deploy mode information
     */
    #[McpTool(
        name: 'deploy-mode',
        description: 'Returns current deploy mode and related configuration'
    )]
    public function getDeployMode(): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $deploymentConfig = MagentoBootstrap::get(\Magento\Framework\App\DeploymentConfig::class);

            $mode = $state->getMode();

            return [
                'mode' => $mode,
                'mode_description' => $this->getDeployModeDescription($mode),
                'static_content_on_demand' => $deploymentConfig->get('static_content_on_demand_in_production', false),
                'config_sync_disabled' => $deploymentConfig->get('config_sync_disabled', false),
                'recommendations' => $this->getDeployModeRecommendations($mode),
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Validates module code structure.
     *
     * @param string $moduleName Module name (Vendor_Module format)
     * @return array<string, mixed> Validation results
     */
    #[McpTool(
        name: 'validate-module',
        description: 'Validates a Magento module code structure and configuration'
    )]
    public function validateModule(string $moduleName): array
    {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        try {
            $moduleDir = MagentoBootstrap::get(\Magento\Framework\Module\Dir::class);

            try {
                $path = $moduleDir->getDir($moduleName);
            } catch (\Throwable $e) {
                return ['error' => true, 'message' => "Module not found: $moduleName"];
            }

            $issues = [];
            $warnings = [];
            $info = [];

            // Check registration.php
            $registrationFile = $path . '/registration.php';
            if (!file_exists($registrationFile)) {
                $issues[] = 'Missing registration.php';
            } else {
                $info[] = 'registration.php exists';
            }

            // Check etc/module.xml
            $moduleXml = $path . '/etc/module.xml';
            if (!file_exists($moduleXml)) {
                $issues[] = 'Missing etc/module.xml';
            } else {
                $info[] = 'etc/module.xml exists';
                $this->validateModuleXml($moduleXml, $moduleName, $issues, $warnings);
            }

            // Check composer.json
            $composerJson = $path . '/composer.json';
            if (!file_exists($composerJson)) {
                $warnings[] = 'Missing composer.json (recommended for distribution)';
            } else {
                $info[] = 'composer.json exists';
            }

            // Check PHP files for strict types
            $phpFiles = array_merge(
                glob($path . '/*.php') ?: [],
                glob($path . '/**/*.php') ?: []
            );
            $missingStrictTypes = 0;
            foreach ($phpFiles as $file) {
                $content = file_get_contents($file);
                if ($content !== false && !str_contains($content, 'declare(strict_types=1)')) {
                    $missingStrictTypes++;
                }
            }
            if ($missingStrictTypes > 0) {
                $warnings[] = "$missingStrictTypes PHP files missing declare(strict_types=1)";
            }

            // Check for etc/di.xml if there are classes
            if (is_dir($path . '/Model') || is_dir($path . '/Api')) {
                if (!file_exists($path . '/etc/di.xml')) {
                    $warnings[] = 'Module has Model/Api but no etc/di.xml';
                }
            }

            $valid = count($issues) === 0;

            return [
                'module' => $moduleName,
                'path' => $path,
                'valid' => $valid,
                'issues' => $issues,
                'warnings' => $warnings,
                'info' => $info,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get indexer status label
     */
    private function getIndexerStatusLabel(string $status): string
    {
        return match ($status) {
            \Magento\Framework\Indexer\StateInterface::STATUS_VALID => 'Ready',
            \Magento\Framework\Indexer\StateInterface::STATUS_INVALID => 'Reindex required',
            \Magento\Framework\Indexer\StateInterface::STATUS_WORKING => 'Processing',
            default => 'Unknown',
        };
    }

    /**
     * Get deploy mode description
     */
    private function getDeployModeDescription(string $mode): string
    {
        return match ($mode) {
            \Magento\Framework\App\State::MODE_DEVELOPER => 'Developer mode - all errors displayed, no caching optimizations',
            \Magento\Framework\App\State::MODE_PRODUCTION => 'Production mode - optimized for performance, errors logged',
            \Magento\Framework\App\State::MODE_DEFAULT => 'Default mode - partial caching, some errors displayed',
            default => 'Unknown mode',
        };
    }

    /**
     * Get deploy mode recommendations
     */
    private function getDeployModeRecommendations(string $mode): array
    {
        return match ($mode) {
            \Magento\Framework\App\State::MODE_DEVELOPER => [
                'Suitable for local development',
                'Do not use in production',
                'Run setup:di:compile before switching to production',
            ],
            \Magento\Framework\App\State::MODE_PRODUCTION => [
                'Optimal for live sites',
                'Run setup:static-content:deploy if templates change',
                'Run setup:di:compile if DI configuration changes',
            ],
            default => [
                'Consider switching to developer or production mode',
                'Default mode is not recommended',
            ],
        };
    }

    /**
     * Validate module.xml content
     *
     * @param string $path
     * @param string $moduleName
     * @param array<string> &$issues
     * @param array<string> &$warnings
     */
    private function validateModuleXml(string $path, string $moduleName, array &$issues, array &$warnings): void
    {
        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            $issues[] = 'Invalid XML in etc/module.xml';
            return;
        }

        $module = $xml->module ?? null;
        if ($module === null) {
            $issues[] = 'Missing <module> element in etc/module.xml';
            return;
        }

        $declaredName = (string) ($module['name'] ?? '');
        if ($declaredName !== $moduleName) {
            $issues[] = "Module name mismatch: declared '$declaredName', expected '$moduleName'";
        }
    }
}
