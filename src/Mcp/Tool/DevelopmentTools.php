<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Mcp\Capability\Attribute\McpTool;

/**
 * Development Tools
 *
 * Provides MCP tools for Magento development operations.
 */
class DevelopmentTools
{
    use RequiresMagento;
    use RespondsWithErrors;

    /**
     * Reinitializes Magento with a fresh ObjectManager.
     *
     * The MCP server is a long-lived process that bootstraps Magento once at
     * startup. When external commands change the application state (new modules,
     * recompiled DI, flushed caches), the in-memory ObjectManager becomes stale:
     * new modules aren't recognized, config.xml defaults don't load, and DI
     * preferences are outdated.
     *
     * Call this tool after running any of: setup:upgrade, setup:di:compile,
     * module:enable/disable, cache:flush, or adding new module files.
     *
     * @return array<string, mixed> Reinit result with module count and timing
     */
    #[McpTool(
        name: 'reinitialize',
        description: 'Reinitialize Magento context. Call after setup:upgrade, setup:di:compile, or module changes to pick up new modules and config.',
    )]
    public function reinitialize(): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento was never initialized — nothing to reinitialize.'];
        }

        $startTime = microtime(true);

        try {
            MagentoBootstrap::reinitialize();
        } catch (\Throwable $e) {
            return $this->errorResponse('Reinitialize failed: ' . $e->getMessage());
        }

        CodeRunnerTools::clearDefinedFunctions();

        $elapsed = round((microtime(true) - $startTime) * 1000, 1);

        // Verify the new state
        try {
            $moduleList = MagentoBootstrap::get(\Magento\Framework\Module\ModuleListInterface::class);
            $moduleCount = count($moduleList->getAll());
        } catch (\Throwable $e) {
            $moduleCount = null;
        }

        try {
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $mode = $state->getMode();
        } catch (\Throwable $e) {
            $mode = 'unknown';
        }

        return [
            'success' => true,
            'message' => 'Magento reinitialized with fresh ObjectManager.',
            'modules_loaded' => $moduleCount,
            'mode' => $mode,
            'elapsed_ms' => $elapsed,
        ];
    }

    /**
     * Checks system status for cache, indexers, deploy mode, cron jobs, or cron history.
     *
     * @param string $check One of: cache, indexers, deploy-mode, cron, cron-history
     * @param string $group Filter cron jobs by group (used when check=cron)
     * @param string $jobCode Filter cron history by job code (used when check=cron-history)
     * @param int $limit Maximum number of cron history records (used when check=cron-history)
     * @return array<string, mixed> Status information for the requested check
     */
    #[McpTool(
        name: 'system-status',
        description: 'Check system status: cache, indexers, deploy-mode, cron, or cron-history.',
        meta: ['hidden' => true]
    )]
    public function getSystemStatus(
        string $check,
        string $group = '',
        string $jobCode = '',
        int $limit = 50
    ): array {
        $allowed = ['cache', 'indexers', 'deploy-mode', 'cron', 'cron-history'];

        if (!in_array($check, $allowed, true)) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Invalid check "%s". Allowed values: %s.',
                    $check,
                    implode(', ', $allowed)
                ),
            ];
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        $result = match ($check) {
            'cache'       => $this->getCacheStatus(),
            'indexers'    => $this->getIndexerStatus(),
            'deploy-mode' => $this->getDeployMode(),
            'cron'        => $this->getCronList($group),
            'cron-history' => $this->getCronHistory($jobCode, $limit),
        };

        // Context-aware hints
        if ($check === 'indexers' && ($result['summary']['invalid'] ?? 0) > 0) {
            $result['_hint'] = 'Invalid indexers found. Check log action=read log_type=system for related errors';
        }
        if ($check === 'cache' && ($result['summary']['disabled'] ?? 0) > 0) {
            $result['_hint'] = 'Disabled cache types found. This may affect performance and behavior';
        }

        return $result;
    }

    /**
     * Returns cache status for all cache types.
     *
     * Public so it can be called from DiagnosticTools without being a registered MCP tool.
     *
     * @return array<string, mixed> Cache status information
     */
    public function getCacheStatus(): array
    {
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
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Returns indexer status for all indexers.
     *
     * Public so it can be called from DiagnosticTools without being a registered MCP tool.
     *
     * @return array<string, mixed> Indexer status information
     */
    public function getIndexerStatus(): array
    {
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
            return $this->errorResponse($e->getMessage());
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
        description: 'Validate module structure and configuration.',
        meta: ['hidden' => true]
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
                return $this->errorResponse("Module not found: $moduleName");
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
            $phpFiles = $this->collectPhpFiles($path);
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
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Collects all PHP files under a directory recursively.
     *
     * Replaces the non-recursive glob('/**') approach: PHP's glob() does not
     * recurse more than one level deep, so files in subdirectories such as
     * Model/ResourceModel/ or Block/Adminhtml/ were silently skipped.
     *
     * @param string $path Root directory to scan
     * @return string[]    Absolute paths to every *.php file found
     */
    private function collectPhpFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Returns deploy mode and related configuration.
     *
     * Public so it can be called from DiagnosticTools without being a registered MCP tool.
     *
     * @return array<string, mixed> Deploy mode information
     */
    public function getDeployMode(): array
    {
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
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Lists configured cron jobs.
     *
     * @param string $group Filter by cron group (optional)
     * @return array<string, mixed> List of cron jobs
     */
    private function getCronList(string $group = ''): array
    {
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
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Returns recent cron job history.
     *
     * @param string $jobCode Filter by job code (optional)
     * @param int $limit Maximum number of records
     * @return array<string, mixed> Cron history
     */
    private function getCronHistory(string $jobCode = '', int $limit = 50): array
    {
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
            return $this->errorResponse($e->getMessage());
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
