<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ReadsLogFiles;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Diagnostic\ExceptionParser;
use Mcp\Capability\Attribute\McpTool;

/**
 * Diagnostic Tools
 *
 * Provides an orchestrated error diagnosis tool that parses Magento exception logs,
 * correlates errors with module context, DI configuration, and environment state,
 * and produces actionable fix suggestions in a single tool call.
 */
class DiagnosticTools
{
    use ReadsLogFiles;
    use RequiresMagento;

    /**
     * Log file sources mapped to their relative paths.
     */
    private const LOG_FILES = [
        'exception' => 'var/log/exception.log',
        'system' => 'var/log/system.log',
        'debug' => 'var/log/debug.log',
        'cron' => 'var/log/cron.log',
    ];

    /**
     * Known Magento error patterns with regex, category, relevant caches, and suggestions.
     *
     * @var array<array<string, mixed>>
     */
    private const ERROR_PATTERNS = [
        [
            'match' => '/Class .+ not found/',
            'category' => 'autoload',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Run composer dump-autoload to regenerate the autoloader',
                    'reason' => 'Class not found usually means the autoloader is stale',
                    'command' => 'composer dump-autoload',
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Run DI compilation to regenerate factories and proxies',
                    'reason' => 'Generated classes require di:compile',
                    'command' => 'bin/magento setup:di:compile',
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Invalid block type/',
            'category' => 'layout',
            'relevant_caches' => ['block_html', 'layout', 'full_page'],
            'suggestions' => [
                [
                    'action' => 'Flush layout and block caches',
                    'reason' => 'Invalid block type often results from stale layout cache',
                    'command' => 'bin/magento cache:clean block_html layout full_page',
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Verify the block class exists and is properly declared',
                    'reason' => 'The block class referenced in layout XML may be missing or misspelled',
                    'command' => null,
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Area code is not set/',
            'category' => 'area',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Set area code before using area-dependent functionality',
                    'reason' => 'CLI scripts and cron jobs must explicitly set the area code',
                    'command' => null,
                    'confidence' => 'high',
                ],
            ],
        ],
        [
            'match' => '/Area code is already set/',
            'category' => 'area',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Remove duplicate area code setting',
                    'reason' => 'Area code can only be set once per request lifecycle',
                    'command' => null,
                    'confidence' => 'high',
                ],
            ],
        ],
        [
            'match' => '/Magento\\\\Framework\\\\Exception\\\\SessionException|session_start/',
            'category' => 'session',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Check session storage configuration and permissions',
                    'reason' => 'Session errors often indicate filesystem permission or Redis connection issues',
                    'command' => null,
                    'confidence' => 'medium',
                ],
                [
                    'action' => 'Clean session files',
                    'reason' => 'Corrupted session files can cause session_start failures',
                    'command' => 'rm -rf var/session/*',
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Compilation error|Cannot instantiate|Invalid type hint|Non-existent class/',
            'category' => 'di',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Clear generated code and recompile DI',
                    'reason' => 'DI compilation errors require clearing generated code first',
                    'command' => 'rm -rf generated/code generated/metadata && bin/magento setup:di:compile',
                    'confidence' => 'high',
                ],
            ],
        ],
        [
            'match' => '/Invalid template file/',
            'category' => 'template',
            'relevant_caches' => ['block_html', 'full_page'],
            'suggestions' => [
                [
                    'action' => 'Verify the template file path and existence',
                    'reason' => 'Template path in layout XML may be incorrect or the file was removed',
                    'command' => null,
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Flush block and full page caches',
                    'reason' => 'Stale cache may reference an old template path',
                    'command' => 'bin/magento cache:clean block_html full_page',
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/SQLSTATE\[HY000\].*Can\'t connect|SQLSTATE\[HY000\].*Connection refused/',
            'category' => 'database',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Check database server connectivity and credentials',
                    'reason' => 'Cannot connect to the database server',
                    'command' => null,
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Verify env.php database configuration',
                    'reason' => 'Database credentials in app/etc/env.php may be incorrect',
                    'command' => null,
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Deadlock found|Lock wait timeout exceeded/',
            'category' => 'database',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Identify and resolve the concurrent queries causing deadlocks',
                    'reason' => 'Deadlocks occur when concurrent transactions lock the same rows in different order',
                    'command' => null,
                    'confidence' => 'medium',
                ],
                [
                    'action' => 'Check for long-running cron jobs or imports',
                    'reason' => 'Bulk operations during peak traffic can cause lock contention',
                    'command' => null,
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/SQLSTATE/',
            'category' => 'database',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Run setup:upgrade to apply pending database schema changes',
                    'reason' => 'SQL errors may indicate missing tables or columns from unapplied patches',
                    'command' => 'bin/magento setup:upgrade',
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Invalid entity type/',
            'category' => 'eav',
            'relevant_caches' => ['eav'],
            'suggestions' => [
                [
                    'action' => 'Flush EAV cache',
                    'reason' => 'Stale EAV cache may reference an outdated entity type configuration',
                    'command' => 'bin/magento cache:clean eav',
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Verify the entity type exists in eav_entity_type table',
                    'reason' => 'The entity type may not be registered in the database',
                    'command' => null,
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Layout XML|layout.*invalid|Invalid XML/',
            'category' => 'layout',
            'relevant_caches' => ['layout', 'full_page'],
            'suggestions' => [
                [
                    'action' => 'Validate layout XML files for syntax errors',
                    'reason' => 'Malformed layout XML will prevent page rendering',
                    'command' => null,
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Flush layout cache',
                    'reason' => 'Stale layout cache may reference invalid XML',
                    'command' => 'bin/magento cache:clean layout full_page',
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Missing required argument|Argument.*is required/',
            'category' => 'di',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Recompile dependency injection',
                    'reason' => 'Missing constructor arguments indicate stale DI generated code',
                    'command' => 'rm -rf generated/code generated/metadata && bin/magento setup:di:compile',
                    'confidence' => 'high',
                ],
            ],
        ],
        [
            'match' => '/Allowed memory size|Out of memory/',
            'category' => 'memory',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Increase PHP memory_limit',
                    'reason' => 'The process exceeded the configured PHP memory limit',
                    'command' => null,
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Profile memory usage to find the leak',
                    'reason' => 'Large collections loaded without pagination or unclosed loops can exhaust memory',
                    'command' => null,
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/Elasticsearch|OpenSearch|search engine|No alive nodes/',
            'category' => 'search',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Check search engine connectivity',
                    'reason' => 'The search engine (Elasticsearch/OpenSearch) is unreachable',
                    'command' => null,
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Verify search engine configuration in env.php',
                    'reason' => 'Host, port, or index prefix may be misconfigured',
                    'command' => null,
                    'confidence' => 'medium',
                ],
                [
                    'action' => 'Reindex the catalog search index',
                    'reason' => 'Search index may be corrupted or out of date',
                    'command' => 'bin/magento indexer:reindex catalogsearch_fulltext',
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/TypeError.*[Rr]eturn value|[Rr]eturn value must be of type/',
            'category' => 'type',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Check the di.xml preference for the return type interface',
                    'reason' => 'A repository or service method returns a concrete class that does not implement the expected interface',
                    'command' => null,
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Clear generated code and recompile DI',
                    'reason' => 'Generated interceptors or proxies may have stale return type information',
                    'command' => 'rm -rf generated/code generated/metadata && bin/magento setup:di:compile',
                    'confidence' => 'medium',
                ],
            ],
        ],
        [
            'match' => '/TypeError|ValueError|ArgumentCountError|ArithmeticError|DivisionByZeroError|UnhandledMatchError/',
            'category' => 'type',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Review the code at the reported file and line for type mismatches',
                    'reason' => 'PHP Error subclasses indicate strict type violations or invalid arguments',
                    'command' => null,
                    'confidence' => 'high',
                ],
                [
                    'action' => 'Clear generated code and recompile DI',
                    'reason' => 'Stale generated code may cause type mismatches',
                    'command' => 'rm -rf generated/code generated/metadata && bin/magento setup:di:compile',
                    'confidence' => 'medium',
                ],
            ],
        ],
    ];

    private LogTools $logTools;
    private DevelopmentTools $devTools;
    private ConfigurationTools $configTools;
    private ModuleTools $moduleTools;
    private ApplicationTools $appTools;
    private ExceptionParser $exceptionParser;

    public function __construct()
    {
        $this->logTools = new LogTools();
        $this->devTools = new DevelopmentTools();
        $this->configTools = new ConfigurationTools();
        $this->moduleTools = new ModuleTools();
        $this->appTools = new ApplicationTools();
        $this->exceptionParser = new ExceptionParser();
    }

    /**
     * Diagnoses the most recent Magento error with full context, DI analysis, and fix suggestions.
     *
     * Parses the exception log, identifies the responsible module, gathers DI and environment
     * context, matches against known error patterns, and returns actionable suggestions.
     *
     * When no errors are found in the primary log source, automatically checks:
     * 1. system.log as an alternate log source (if primary was exception.log)
     * 2. var/report/ files for errors that Magento logged outside of Monolog
     *    (e.g., \Error subclasses like TypeError in developer mode)
     *
     * @param int $index Which error to diagnose (0 = most recent, 1 = second most recent, etc.)
     * @param string $source Log file to read (exception, system, debug, cron)
     * @param string $since Only look at errors from the last N time units (e.g., '5m', '1h', '24h', '7d')
     * @param string $pattern Optional substring filter for error messages
     * @return array<string, mixed> Structured diagnosis with error, context, and suggestions
     */
    #[McpTool(
        name: 'diagnose-error',
        description: 'Diagnoses the most recent Magento error with context and fix suggestions. Use verbosity (minimal/standard/detailed) to control detail level.'
    )]
    public function diagnoseError(
        int $index = 0,
        string $source = 'exception',
        string $since = '1h',
        string $pattern = '',
        string $verbosity = 'standard'
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        if (!in_array($verbosity, ['minimal', 'standard', 'detailed'], true)) {
            return ['error' => true, 'message' => 'verbosity must be one of: minimal, standard, detailed'];
        }

        try {
            // 1. PARSE — read log file and parse exceptions
            $magentoRoot = MagentoBootstrap::getMagentoRoot();
            $logFile = $this->resolveLogFile($source);
            $logPath = $magentoRoot . '/' . $logFile;

            $exceptions = [];
            $searchedSources = [$source];

            if (file_exists($logPath)) {
                $rawLines = $this->readLastLines($logPath, 5000);
                $exceptions = $this->exceptionParser->parse($rawLines, $since, $pattern);
            }

            // FALLBACK — try additional sources when the primary log has no match at $index.
            // system.log is only tried when the primary source is exception.log.
            // var/report catches errors that Magento's Bootstrap::terminate() handles
            // without logging (e.g., TypeError and other \Error subclasses in developer mode).
            $fallbackSources = $source === 'exception'
                ? ['system' => self::LOG_FILES['system'], 'var/report' => null]
                : ['var/report' => null];

            foreach ($fallbackSources as $fallbackName => $fallbackFile) {
                if (!empty($exceptions) && isset($exceptions[$index])) {
                    break;
                }

                if ($fallbackFile !== null) {
                    $fallbackPath = $magentoRoot . '/' . $fallbackFile;
                    if (!file_exists($fallbackPath)) {
                        continue;
                    }
                    $fallbackLines = $this->readLastLines($fallbackPath, 5000);
                    $additional = $this->exceptionParser->parse($fallbackLines, $since, $pattern);
                } else {
                    $additional = $this->scanReportFiles($magentoRoot, $since, $pattern);
                }

                if (empty($additional)) {
                    continue;
                }

                $searchedSources[] = $fallbackName;
                $exceptions = array_merge($exceptions, $additional);
                $this->sortByTimestampDescending($exceptions);
            }

            if (empty($exceptions) || !isset($exceptions[$index])) {
                return [
                    'found' => false,
                    'message' => 'No matching errors found',
                    'searched' => $searchedSources,
                    'since' => $since,
                    'pattern' => $pattern ?: null,
                    'total_found' => count($exceptions),
                    'note' => $source === 'exception'
                        ? 'In developer mode, PHP \Error subclasses (TypeError, ValueError, etc.) '
                          . 'are displayed but not logged. Check the browser response or var/report/ for details.'
                        : null,
                ];
            }

            $error = $exceptions[$index];

            // 2. IDENTIFY MODULE
            $moduleName = $this->resolveModuleName($error);

            // 3. GATHER MODULE CONTEXT
            $moduleContext = null;
            if ($moduleName !== null) {
                $moduleContext = $this->gatherModuleContext($moduleName);
            }

            // 4. GATHER DI CONTEXT
            $diContext = null;
            $errorClass = $this->extractClassFromError($error);
            if ($errorClass !== null) {
                $diResult = $this->configTools->getDiConfiguration($errorClass);
                if (!isset($diResult['error'])) {
                    $diContext = [
                        'class' => $errorClass,
                        'preference' => $diResult['preference'] ?? null,
                        'plugins' => $diResult['plugins'] ?? [],
                        'class_exists' => class_exists($errorClass) || interface_exists($errorClass),
                    ];
                }
            }

            // 5. GATHER ENVIRONMENT
            $cacheResult = $this->devTools->getCacheStatus();
            $indexerResult = $this->devTools->getIndexerStatus();
            $modeResult = $this->devTools->getDeployMode();

            $environment = [
                'deploy_mode' => $modeResult['mode'] ?? 'unknown',
                'cache_disabled' => $this->extractDisabledCaches($cacheResult),
                'indexers_invalid' => $this->extractInvalidIndexers($indexerResult),
                'generated_code_age' => $this->getGeneratedCodeAge($magentoRoot),
            ];

            // 6. GATHER HISTORY
            $analysisHours = $this->sinceToHours($since);
            $analysis = $this->logTools->analyzeExceptionLog($analysisHours);

            $history = [
                'total_errors_in_period' => $analysis['total_errors'] ?? 0,
                'error_types' => array_slice($analysis['error_types'] ?? [], 0, 5, true),
                'this_error_count' => $this->countMatchingErrors($analysis, $error),
            ];

            // 7. MATCH PATTERN + BUILD SUGGESTIONS
            $matched = $this->matchPattern($error);
            $suggestions = $this->buildSuggestions($matched, $environment, $moduleContext, $diContext);

            // 8. ASSEMBLE RESPONSE
            $errorResult = [
                'message' => $error['message'] ?? '',
                'class' => $error['class'] ?? null,
                'code' => $error['code'] ?? null,
                'file' => $error['file'] ?? null,
                'line' => $error['line'] ?? null,
                'timestamp' => $error['timestamp'] ?? null,
                'level' => $error['level'] ?? null,
                'stack_trace' => array_slice($error['stack_trace'] ?? [], 0, 10),
                'previous' => $error['previous'] ?? null,
            ];

            // Include source metadata for report-sourced errors
            foreach (['source', 'report_id', 'url'] as $metaKey) {
                if (isset($error[$metaKey])) {
                    $errorResult[$metaKey] = $error[$metaKey];
                }
            }

            // Truncate raw stack trace if present and over 1000 chars
            if (isset($error['raw']) && strlen($error['raw']) > 1000) {
                $truncated = $this->truncateText($error['raw'], 1000);
                $errorResult['raw'] = $truncated['text'];
                $errorResult['raw_truncated'] = true;
                $errorResult['raw_original_length'] = $truncated['original_length'];
            } elseif (isset($error['raw'])) {
                $errorResult['raw'] = $error['raw'];
            }

            $diagnosis = [
                'found' => true,
                'error' => $errorResult,
                'searched' => $searchedSources,
                'category' => $matched['category'] ?? null,
                'module_context' => $moduleContext,
                'di_context' => $diContext,
                'environment' => $environment,
                'history' => $history,
                'suggestions' => $suggestions,
            ];

            if ($verbosity === 'minimal') {
                return [
                    'found' => true,
                    'verbosity' => 'minimal',
                    'exception' => [
                        'class' => $diagnosis['error']['class'] ?? null,
                        'message' => $diagnosis['error']['message'] ?? null,
                    ],
                    'category' => $diagnosis['category'] ?? null,
                    'suggestions' => array_slice($diagnosis['suggestions'] ?? [], 0, 1),
                ];
            }

            if ($verbosity === 'detailed') {
                $diagnosis['verbosity'] = 'detailed';
                return $diagnosis;
            }

            $diagnosis['verbosity'] = 'standard';
            return $diagnosis;
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Resolve log file source name to a relative path.
     */
    private function resolveLogFile(string $source): string
    {
        return self::LOG_FILES[$source] ?? self::LOG_FILES['exception'];
    }

    /**
     * Extract Vendor_Module from exception namespace or file path.
     *
     * @param array<string, mixed> $error
     */
    private function resolveModuleName(array $error): ?string
    {
        // Try from exception class namespace
        $class = $error['class'] ?? '';
        if (preg_match('/^([A-Z][a-z0-9]+)\\\\([A-Z][A-Za-z0-9]+)\\\\/', $class, $m)) {
            return $m[1] . '_' . $m[2];
        }

        // Try from file path in error or stack trace
        $file = $error['file'] ?? '';
        if (preg_match('#app/code/([^/]+)/([^/]+)/#', $file, $m)) {
            return $m[1] . '_' . $m[2];
        }

        foreach ($error['stack_trace'] ?? [] as $frame) {
            $frameFile = $frame['file'] ?? '';
            if (preg_match('#app/code/([^/]+)/([^/]+)/#', $frameFile, $m)) {
                return $m[1] . '_' . $m[2];
            }
        }

        // Try from vendor path
        if (preg_match('#vendor/([^/]+)/module-([^/]+)/#', $file, $m)) {
            $vendor = ucfirst($m[1]);
            $module = str_replace('-', '', ucwords($m[2], '-'));
            return $vendor . '_' . $module;
        }

        return null;
    }

    /**
     * Extract a class name from the error message.
     *
     * @param array<string, mixed> $error
     */
    private function extractClassFromError(array $error): ?string
    {
        $msg = $error['message'] ?? '';
        if (preg_match('/([A-Z][A-Za-z0-9]+(?:\\\\[A-Z][A-Za-z0-9]+){2,})/', $msg, $m)) {
            return $m[1];
        }
        return $error['class'] ?? null;
    }

    /**
     * Gather module context from existing module tools.
     *
     * @return array<string, mixed>
     */
    private function gatherModuleContext(string $moduleName): array
    {
        $moduleList = $this->moduleTools->listModules(false, '');
        $moduleInfo = $this->findModule($moduleList, $moduleName);

        if ($moduleInfo === null) {
            return ['module_name' => $moduleName, 'enabled' => false, 'found' => false];
        }

        $structure = $this->moduleTools->getModuleStructure($moduleName);
        $validation = $this->devTools->validateModule($moduleName);

        return [
            'module_name' => $moduleName,
            'enabled' => $moduleInfo['enabled'],
            'version' => $moduleInfo['version'],
            'path' => $moduleInfo['path'],
            'is_custom' => !$moduleInfo['is_magento'],
            'dependencies' => $structure['info']['dependencies'] ?? [],
            'validation_issues' => $validation['issues'] ?? [],
            'validation_warnings' => $validation['warnings'] ?? [],
        ];
    }

    /**
     * Find a module in the listModules() result by name.
     *
     * @param array<string, mixed> $moduleListResult
     * @return array<string, mixed>|null
     */
    private function findModule(array $moduleListResult, string $name): ?array
    {
        foreach ($moduleListResult['modules'] ?? [] as $mod) {
            if ($mod['name'] === $name) {
                return $mod;
            }
        }
        return null;
    }

    /**
     * Extract disabled cache type IDs from getCacheStatus() result.
     *
     * @param array<string, mixed> $cacheResult
     * @return array<string>
     */
    private function extractDisabledCaches(array $cacheResult): array
    {
        $disabled = [];
        foreach ($cacheResult['types'] ?? [] as $type) {
            if ($type['status'] === 'disabled') {
                $disabled[] = $type['id'];
            }
        }
        return $disabled;
    }

    /**
     * Extract invalid indexer IDs from getIndexerStatus() result.
     *
     * @param array<string, mixed> $indexerResult
     * @return array<string>
     */
    private function extractInvalidIndexers(array $indexerResult): array
    {
        $invalid = [];
        foreach ($indexerResult['indexers'] ?? [] as $indexer) {
            if ($indexer['status'] === 'invalid') {
                $invalid[] = $indexer['indexer_id'];
            }
        }
        return $invalid;
    }

    /**
     * Check age of generated/metadata/ directory.
     */
    private function getGeneratedCodeAge(string $root): ?string
    {
        $path = $root . '/generated/metadata';
        if (!is_dir($path)) {
            return null;
        }
        $mtime = filemtime($path);
        return $mtime ? date('Y-m-d H:i:s', $mtime) : null;
    }

    /**
     * Convert relative time string to integer hours.
     */
    private function sinceToHours(string $since): int
    {
        if (preg_match('/^(\d+)([mhd])$/', $since, $m)) {
            return match ($m[2]) {
                'm' => max(1, (int) ceil((int) $m[1] / 60)),
                'h' => (int) $m[1],
                'd' => (int) $m[1] * 24,
                default => 1,
            };
        }
        return 1;
    }

    /**
     * Count how many errors in the analysis match the current error.
     *
     * @param array<string, mixed> $analysis
     * @param array<string, mixed> $error
     */
    private function countMatchingErrors(array $analysis, array $error): int
    {
        $errorClass = $error['class'] ?? '';
        if ($errorClass === '') {
            return 0;
        }

        return $analysis['error_types'][$errorClass] ?? 0;
    }

    /**
     * Match the error against known Magento error patterns.
     *
     * @param array<string, mixed> $error
     * @return array<string, mixed>|null The matched pattern or null
     */
    private function matchPattern(array $error): ?array
    {
        $message = ($error['message'] ?? '') . ' ' . ($error['class'] ?? '');
        foreach (self::ERROR_PATTERNS as $pattern) {
            if (preg_match($pattern['match'], $message)) {
                return $pattern;
            }
        }
        return null;
    }

    /**
     * Build enriched suggestions based on the matched pattern and gathered context.
     *
     * @param array<string, mixed>|null $matched
     * @param array<string, mixed> $env
     * @param array<string, mixed>|null $module
     * @param array<string, mixed>|null $di
     * @return array<array<string, mixed>>
     */
    private function buildSuggestions(?array $matched, array $env, ?array $module, ?array $di): array
    {
        $suggestions = $matched['suggestions'] ?? [];
        $enriched = [];

        foreach ($suggestions as $s) {
            // Adjust cache-related suggestions
            if (str_contains($s['action'] ?? '', 'cache') && !empty($matched['relevant_caches'])) {
                $relevant = $matched['relevant_caches'];
                $disabled = $env['cache_disabled'] ?? [];
                $alreadyDisabled = array_intersect($relevant, $disabled);

                if (count($alreadyDisabled) === count($relevant)) {
                    $s['note'] = 'All relevant caches are already disabled — caching is not the cause';
                    $s['confidence'] = 'low';
                }
            }

            // Adjust di:compile suggestions
            if (str_contains($s['command'] ?? '', 'di:compile')) {
                $age = $env['generated_code_age'] ?? null;
                if ($age !== null && (time() - strtotime($age)) < 120) {
                    $s['note'] = 'DI was compiled less than 2 minutes ago';
                    $s['confidence'] = 'low';
                }
            }

            $enriched[] = $s;
        }

        // Add module-specific suggestions
        if ($module !== null) {
            $moduleFound = $module['found'] ?? true;
            $moduleName = $module['module_name'];

            if ($moduleFound === false) {
                array_unshift($enriched, [
                    'action' => "Module {$moduleName} not found in the system",
                    'reason' => 'The module referenced by this error is not installed',
                    'command' => null,
                    'confidence' => 'high',
                ]);
            } elseif (!($module['enabled'] ?? true)) {
                array_unshift($enriched, [
                    'action' => "Enable module: bin/magento module:enable {$moduleName}",
                    'reason' => 'The module is installed but disabled',
                    'command' => "bin/magento module:enable {$moduleName}",
                    'confidence' => 'high',
                ]);
            }
        }

        // Add DI-specific suggestions
        if ($di !== null && !($di['class_exists'] ?? true)) {
            array_unshift($enriched, [
                'action' => "Class {$di['class']} does not exist on disk",
                'reason' => 'The class file is missing or not autoloadable',
                'command' => 'composer dump-autoload && bin/magento setup:di:compile',
                'confidence' => 'high',
            ]);
        }

        // Add indexer suggestion if relevant
        $invalidIndexers = $env['indexers_invalid'] ?? [];
        if (!empty($invalidIndexers) && in_array($matched['category'] ?? '', ['catalog', 'search', 'database', 'eav'])) {
            $enriched[] = [
                'action' => 'Reindex invalid indexers: ' . implode(', ', $invalidIndexers),
                'reason' => count($invalidIndexers) . ' indexer(s) need reindexing, which may be related',
                'command' => 'bin/magento indexer:reindex ' . implode(' ', $invalidIndexers),
                'confidence' => 'medium',
            ];
        }

        // Fallback if no pattern matched and no contextual suggestions generated
        if (empty($enriched)) {
            $enriched[] = [
                'action' => 'Review the stack trace to identify the originating code',
                'reason' => 'No known pattern matched — manual investigation needed',
                'command' => null,
                'confidence' => 'low',
            ];
        }

        return $enriched;
    }

    /**
     * Scan var/report/ directory for recent error report files.
     *
     * Magento writes JSON report files to var/report/ for errors that occur during
     * request processing. These are particularly useful for catching errors that
     * Magento's Bootstrap::terminate() handles without logging (e.g., TypeError
     * and other \Error subclasses in developer mode).
     *
     * @param string $magentoRoot Magento root directory
     * @param string $since Relative time filter
     * @param string $pattern Optional message filter
     * @return array<array<string, mixed>> Parsed error entries from report files
     */
    private function scanReportFiles(string $magentoRoot, string $since, string $pattern): array
    {
        $reportDir = $magentoRoot . '/var/report';
        if (!is_dir($reportDir)) {
            return [];
        }

        $cutoff = $this->calculateTimeCutoff($since);
        $entries = [];

        $files = scandir($reportDir, SCANDIR_SORT_DESCENDING);
        if ($files === false) {
            return [];
        }

        $count = 0;
        foreach ($files as $file) {
            $filePath = $reportDir . '/' . $file;
            if ($file === '.' || $file === '..' || !is_file($filePath)) {
                continue;
            }

            $mtime = filemtime($filePath);
            if ($cutoff !== null && $mtime !== false && $mtime < $cutoff) {
                continue;
            }

            $content = file_get_contents($filePath);
            $reportData = $content !== false ? json_decode($content, true) : null;
            if (!is_array($reportData)) {
                continue;
            }

            $entry = $this->exceptionParser->parseReportFile($reportData, $file);
            if ($entry === null) {
                continue;
            }

            if ($pattern !== '' && stripos($entry['raw'] ?? '', $pattern) === false) {
                continue;
            }

            $entry['timestamp'] ??= ($mtime !== false ? date('Y-m-d\TH:i:sP', $mtime) : null);
            $entries[] = $entry;

            if (++$count >= 50) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Sort exception entries by timestamp descending (newest first), in place.
     *
     * @param array<array<string, mixed>> $exceptions
     */
    private function sortByTimestampDescending(array &$exceptions): void
    {
        usort($exceptions, function (array $a, array $b): int {
            $timeA = strtotime($a['timestamp'] ?? '0');
            $timeB = strtotime($b['timestamp'] ?? '0');
            return $timeB <=> $timeA;
        });
    }
}
