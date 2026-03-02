<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

class PerformanceTools
{
    use ChecksConfig;
    use RequiresMagento;

    private const ALLOWED_CHECKS = [
        'all',
        'indexes',
        'cache',
        'flat-tables',
        'cron-backlog',
        'config',
        'queries',
    ];

    /**
     * Analyze Magento performance configuration and data for common issues.
     *
     * @param string $check Which check to run (all, indexes, cache, flat-tables, cron-backlog, config, queries)
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'diagnose-performance',
        description: 'Analyze Magento performance. Checks: flat-tables, indexes, cache, queries, cron-backlog, config. '
            . 'Returns issues with severity and fix suggestions.',
        meta: ['hidden' => true]
    )]
    public function diagnosePerformance(string $check = 'all'): array
    {
        if (!in_array($check, self::ALLOWED_CHECKS, true)) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Invalid check "%s". Allowed: %s',
                    $check,
                    implode(', ', self::ALLOWED_CHECKS)
                ),
            ];
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        $results = match ($check) {
            'all' => array_merge(
                $this->checkIndexes(),
                $this->checkCache(),
                $this->checkFlatTables(),
                $this->checkCronBacklog(),
                $this->checkConfig(),
                $this->checkQueries()
            ),
            'indexes' => $this->checkIndexes(),
            'cache' => $this->checkCache(),
            'flat-tables' => $this->checkFlatTables(),
            'cron-backlog' => $this->checkCronBacklog(),
            'config' => $this->checkConfig(),
            'queries' => $this->checkQueries(),
        };

        $summary = $this->buildSummary($results);

        return [
            'check' => $check,
            'summary' => $summary,
            'findings' => $results,
        ];
    }

    /**
     * Check for invalid or stale indexers.
     *
     * @return list<array{severity: string, check: string, issue: string, suggestion: string}>
     */
    private function checkIndexes(): array
    {
        $findings = [];

        try {
            $devTools = new DevelopmentTools();
            $status = $devTools->getIndexerStatus();

            if (isset($status['error'])) {
                $findings[] = [
                    'severity' => 'warning',
                    'check' => 'indexes',
                    'issue' => 'Unable to check indexer status: ' . ($status['message'] ?? 'unknown error'),
                    'suggestion' => 'Verify Magento indexer subsystem is working correctly.',
                ];
                return $findings;
            }

            foreach ($status['indexers'] ?? [] as $indexer) {
                if ($indexer['status'] !== \Magento\Framework\Indexer\StateInterface::STATUS_VALID) {
                    $findings[] = [
                        'severity' => 'warning',
                        'check' => 'indexes',
                        'issue' => sprintf(
                            'Indexer "%s" is %s.',
                            $indexer['title'] ?? $indexer['indexer_id'],
                            $indexer['status_label'] ?? $indexer['status']
                        ),
                        'suggestion' => sprintf(
                            'Run: bin/magento indexer:reindex %s',
                            $indexer['indexer_id']
                        ),
                    ];
                }
            }

            if (empty($findings)) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'indexes',
                    'issue' => sprintf('All %d indexers are valid.', $status['summary']['total'] ?? 0),
                    'suggestion' => 'No action needed.',
                ];
            }
        } catch (\Throwable $e) {
            $findings[] = [
                'severity' => 'warning',
                'check' => 'indexes',
                'issue' => 'Failed to check indexers: ' . $e->getMessage(),
                'suggestion' => 'Check Magento logs for details.',
            ];
        }

        return $findings;
    }

    /**
     * Check for disabled cache types.
     *
     * @return list<array{severity: string, check: string, issue: string, suggestion: string}>
     */
    private function checkCache(): array
    {
        $findings = [];
        $criticalCaches = ['config', 'full_page'];

        try {
            $devTools = new DevelopmentTools();
            $status = $devTools->getCacheStatus();

            if (isset($status['error'])) {
                $findings[] = [
                    'severity' => 'warning',
                    'check' => 'cache',
                    'issue' => 'Unable to check cache status: ' . ($status['message'] ?? 'unknown error'),
                    'suggestion' => 'Verify Magento cache subsystem is working correctly.',
                ];
                return $findings;
            }

            foreach ($status['types'] ?? [] as $type) {
                if ($type['status'] === 'disabled') {
                    $severity = in_array($type['id'], $criticalCaches, true) ? 'critical' : 'warning';
                    $findings[] = [
                        'severity' => $severity,
                        'check' => 'cache',
                        'issue' => sprintf('Cache type "%s" (%s) is disabled.', $type['label'], $type['id']),
                        'suggestion' => sprintf('Enable with: bin/magento cache:enable %s', $type['id']),
                    ];
                }
            }

            if (empty($findings)) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'cache',
                    'issue' => sprintf('All %d cache types are enabled.', $status['summary']['total'] ?? 0),
                    'suggestion' => 'No action needed.',
                ];
            }
        } catch (\Throwable $e) {
            $findings[] = [
                'severity' => 'warning',
                'check' => 'cache',
                'issue' => 'Failed to check cache: ' . $e->getMessage(),
                'suggestion' => 'Check Magento logs for details.',
            ];
        }

        return $findings;
    }

    /**
     * Check if flat catalog is enabled and whether it makes sense for the catalog size.
     *
     * @return list<array{severity: string, check: string, issue: string, suggestion: string}>
     */
    private function checkFlatTables(): array
    {
        $findings = [];

        try {
            $scopeConfig = MagentoBootstrap::get(
                \Magento\Framework\App\Config\ScopeConfigInterface::class
            );

            $flatProduct = $scopeConfig->isSetFlag('catalog/frontend/flat_catalog_product');
            $flatCategory = $scopeConfig->isSetFlag('catalog/frontend/flat_catalog_category');

            if (!$flatProduct && !$flatCategory) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'flat-tables',
                    'issue' => 'Flat catalog is disabled for both products and categories.',
                    'suggestion' => 'No action needed. Flat tables are generally not recommended in modern Magento.',
                ];
                return $findings;
            }

            // Check product count to determine if flat tables are worthwhile
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $productCount = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $resource->getTableName('catalog_product_entity')
            );

            if ($flatProduct) {
                $severity = $productCount > 10000 ? 'warning' : 'info';
                $findings[] = [
                    'severity' => $severity,
                    'check' => 'flat-tables',
                    'issue' => sprintf(
                        'Flat catalog product is enabled (%s products).',
                        number_format($productCount)
                    ),
                    'suggestion' => $productCount > 10000
                        ? 'Consider disabling flat catalog with large catalogs. '
                            . 'It increases indexing time and may not improve performance with Elasticsearch.'
                        : 'Flat catalog product is enabled. '
                            . 'Consider whether it provides a benefit with your search engine.',
                ];
            }

            if ($flatCategory) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'flat-tables',
                    'issue' => 'Flat catalog category is enabled.',
                    'suggestion' => 'Consider whether flat category tables are needed with your current search engine.',
                ];
            }
        } catch (\Throwable $e) {
            $findings[] = [
                'severity' => 'warning',
                'check' => 'flat-tables',
                'issue' => 'Failed to check flat tables: ' . $e->getMessage(),
                'suggestion' => 'Check Magento logs for details.',
            ];
        }

        return $findings;
    }

    /**
     * Check cron_schedule for pending jobs older than 1 hour.
     *
     * @return list<array{severity: string, check: string, issue: string, suggestion: string}>
     */
    private function checkCronBacklog(): array
    {
        $findings = [];

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $tableName = $resource->getTableName('cron_schedule');

            $pendingCount = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$tableName} "
                . "WHERE status = 'pending' AND scheduled_at < NOW() - INTERVAL 1 HOUR"
            );

            if ($pendingCount === 0) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'cron-backlog',
                    'issue' => 'No overdue cron jobs found.',
                    'suggestion' => 'Cron is processing on schedule.',
                ];
            } elseif ($pendingCount > 100) {
                $findings[] = [
                    'severity' => 'critical',
                    'check' => 'cron-backlog',
                    'issue' => sprintf('%d cron jobs are pending and overdue by more than 1 hour.', $pendingCount),
                    'suggestion' => 'Cron may not be running. Check crontab configuration and system cron service. '
                        . 'Run: bin/magento cron:run to process manually.',
                ];
            } else {
                $findings[] = [
                    'severity' => 'warning',
                    'check' => 'cron-backlog',
                    'issue' => sprintf('%d cron jobs are pending and overdue by more than 1 hour.', $pendingCount),
                    'suggestion' => 'Check if cron is running regularly. Review cron_schedule table for stuck jobs.',
                ];
            }
        } catch (\Throwable $e) {
            $findings[] = [
                'severity' => 'warning',
                'check' => 'cron-backlog',
                'issue' => 'Failed to check cron backlog: ' . $e->getMessage(),
                'suggestion' => 'Check Magento logs for details.',
            ];
        }

        return $findings;
    }

    /**
     * Check performance-related configuration values.
     * Only flags issues in production mode.
     *
     * @return list<array{severity: string, check: string, issue: string, suggestion: string}>
     */
    private function checkConfig(): array
    {
        $findings = [];

        try {
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $mode = $state->getMode();

            if ($mode !== \Magento\Framework\App\State::MODE_PRODUCTION) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'config',
                    'issue' => sprintf(
                        'Deploy mode is "%s". Config optimization checks apply to production only.',
                        $mode
                    ),
                    'suggestion' => 'Switch to production mode for optimal performance: '
                        . 'bin/magento deploy:mode:set production',
                ];
                return $findings;
            }

            $scopeConfig = MagentoBootstrap::get(
                \Magento\Framework\App\Config\ScopeConfigInterface::class
            );

            $checks = [
                'dev/js/merge_js_files' => 'JS file merging',
                'dev/css/merge_css_files' => 'CSS file merging',
                'dev/js/minify_files' => 'JS file minification',
                'dev/css/minify_files' => 'CSS file minification',
            ];

            foreach ($checks as $path => $label) {
                $enabled = $scopeConfig->isSetFlag($path);
                if (!$enabled) {
                    $findings[] = [
                        'severity' => 'info',
                        'check' => 'config',
                        'issue' => sprintf('%s is disabled in production.', $label),
                        'suggestion' => sprintf(
                            'Enable in production for better performance: set %s = 1',
                            $path
                        ),
                    ];
                }
            }

            if (empty($findings)) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'config',
                    'issue' => 'All checked performance config values are properly set for production.',
                    'suggestion' => 'No action needed.',
                ];
            }
        } catch (\Throwable $e) {
            $findings[] = [
                'severity' => 'warning',
                'check' => 'config',
                'issue' => 'Failed to check config: ' . $e->getMessage(),
                'suggestion' => 'Check Magento logs for details.',
            ];
        }

        return $findings;
    }

    /**
     * Check MySQL slow query log status.
     *
     * @return list<array{severity: string, check: string, issue: string, suggestion: string}>
     */
    private function checkQueries(): array
    {
        $findings = [];

        try {
            $resource = MagentoBootstrap::get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();

            $slowLogRow = $connection->fetchRow("SHOW VARIABLES LIKE 'slow_query_log'");
            $slowLogEnabled = ($slowLogRow['Value'] ?? 'OFF') === 'ON';

            if (!$slowLogEnabled) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'queries',
                    'issue' => 'MySQL slow query log is disabled.',
                    'suggestion' => 'Consider enabling slow_query_log to identify slow queries. '
                        . 'Set slow_query_log=ON and long_query_time=1 in MySQL config.',
                ];
                return $findings;
            }

            $slowCountRow = $connection->fetchRow("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $slowCount = (int) ($slowCountRow['Value'] ?? 0);

            if ($slowCount > 0) {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'queries',
                    'issue' => sprintf(
                        'MySQL slow query log is enabled. %d slow queries recorded since server start.',
                        $slowCount
                    ),
                    'suggestion' => 'Review the slow query log for optimization opportunities. '
                        . 'Check long_query_time threshold and analyze logged queries.',
                ];
            } else {
                $findings[] = [
                    'severity' => 'info',
                    'check' => 'queries',
                    'issue' => 'MySQL slow query log is enabled. No slow queries recorded.',
                    'suggestion' => 'No action needed.',
                ];
            }
        } catch (\Throwable $e) {
            $findings[] = [
                'severity' => 'warning',
                'check' => 'queries',
                'issue' => 'Failed to check query performance: ' . $e->getMessage(),
                'suggestion' => 'Check database connectivity and permissions.',
            ];
        }

        return $findings;
    }

    /**
     * Build a summary from the list of findings.
     *
     * @param list<array{severity: string, check: string, issue: string, suggestion: string}> $findings
     * @return array{total: int, critical: int, warning: int, info: int}
     */
    private function buildSummary(array $findings): array
    {
        $summary = ['total' => count($findings), 'critical' => 0, 'warning' => 0, 'info' => 0];

        foreach ($findings as $finding) {
            $severity = $finding['severity'];
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        return $summary;
    }
}
