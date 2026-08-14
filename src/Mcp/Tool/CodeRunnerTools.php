<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Exception\ExecutionTimedOutException;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Inchoo\MagentoBricklayer\Support\ExecutionGuard;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\RequestContext;
use Symfony\Component\Console\Output\BufferedOutput;

class CodeRunnerTools
{
    use ChecksConfig;
    use RequiresMagento;
    use RespondsWithErrors;

    private const MAX_DEFINED_FUNCTIONS = 20;

    /** @var array<int, array{label: string, value: mixed}> */
    private array $logBuffer = [];

    /** @var array<string, string> Named PHP functions stored for the session */
    private static array $definedFunctions = [];

    /** @var bool Whether the bare helper functions have been declared in this process */
    private static bool $helpersRegistered = false;

    private const DANGEROUS_PATTERNS = [
        '/\b(exec|shell_exec|system|passthru|popen|proc_open)\s*\(/i'
            => 'Shell execution functions are not allowed',
        '/\b(unlink|rmdir|rename|copy|file_put_contents|fwrite|fopen)\s*\(/i'
            => 'File write operations are not allowed',
        '/\$_(GET|POST|REQUEST|SERVER|ENV|FILES|COOKIE|SESSION)\s*\[/i'
            => 'Direct superglobal access is not allowed',
        '/\b(curl_exec|curl_multi_exec)\s*\(/i'
            => 'Direct cURL execution is not allowed',
        '/\bexit\s*\(|die\s*\(/i'
            => 'Exit/die statements are not allowed',
        '/\beval\s*\(/i'
            => 'Nested eval is not allowed',
        '/\b(header|setcookie)\s*\(/i'
            => 'HTTP header manipulation is not allowed',
        '/\b(register_shutdown_function|set_error_handler|set_exception_handler)\s*\(/i'
            => 'Global handler registration is not allowed',
        '/\bsleep\s*\(\s*(\d{2,})\s*\)/i'
            => 'Long sleep calls are not allowed (use timeout parameter instead)',
    ];

    #[McpTool(
        name: 'code-runner',
        description: 'Executes PHP in Magento context. '
            . 'Preferred for multi-step operations — one call replaces many tool calls. '
            . 'Helpers: get(class), create(class), repo(class), config(path). '
            . 'Read-only by default. Disabled in production. Call code-runner-help for docs.'
    )]
    public function execute(
        string $code,
        string $area = '',
        bool $allow_write = false,
        int $timeout = 30,
        string $mode = 'execute',
        ?RequestContext $context = null
    ): array {
        if (!in_array($mode, ['execute', 'define'], true)) {
            return [
                'error' => true,
                'message' => sprintf('Invalid mode "%s". Allowed: execute, define.', $mode),
            ];
        }

        if ($mode === 'define') {
            return $this->defineFunction($code);
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        // Hard block in production mode (code-runner is always blocked, no config override)
        if ($this->isProductionMode()) {
            return $this->errorResponse(
                'Code runner is disabled in production mode for security reasons.',
                ['deploy_mode' => 'production']
            );
        }

        if ($error = $this->requireToolEnabled('code-runner')) {
            return $error;
        }

        $writeBlockedByConfig = false;
        try {
            $configAllowWrite = (bool) $this->getConfigLoader()->get('tools.code-runner.allow_write', false);
            if ($allow_write && !$configAllowWrite) {
                $allow_write = false;
                $writeBlockedByConfig = true;
            }
        } catch (\Throwable $e) {
            // proceed with defaults
        }

        $validationError = $this->validateCode($code);
        if ($validationError !== null) {
            return [
                'error' => true,
                'message' => $validationError,
                'code' => $code,
            ];
        }

        if ($area !== '') {
            $areaEmulator = new AreaEmulator();
            if (!$areaEmulator->isValidArea($area)) {
                return [
                    'error' => true,
                    'message' => sprintf(
                        'Invalid area "%s". Available: %s',
                        $area,
                        implode(', ', $areaEmulator->getAvailableAreas())
                    ),
                ];
            }
            $areaEmulator->setArea($area);
        }

        $maxTimeout = $this->getMaxTimeout();
        // Floor at 1s: min(...) with timeout<=0 yields 0, and set_time_limit(0) means
        // UNLIMITED — the opposite of the cap. Clamp to a sane minimum.
        $effectiveTimeout = max(1, min($timeout, $maxTimeout));
        $previousLimit = (int) ini_get('max_execution_time');
        set_time_limit($effectiveTimeout);

        // Primary timeout: SIGALRM throwing ExecutionTimedOutException — wall-clock and
        // catchable, so a timeout rolls back and responds instead of fataling the server.
        // set_time_limit() above stays as a CPU-time backstop (and the only limit where
        // pcntl is unavailable, e.g. Windows).
        $useAlarm = ExecutionGuard::supportsAlarm();
        if ($useAlarm) {
            ExecutionGuard::startTimeout($effectiveTimeout);
        }

        // Let the shutdown hook answer this request with a real error if user code
        // still manages to fatal the process (OOM, redeclare, CPU-backstop timeout).
        ExecutionGuard::beginRequest('code-runner', $this->resolveRequestId($context));

        $this->resetApplicationState();

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startQueries = $this->getQueryCount();
        $this->logBuffer = [];

        try {
            $result = $this->executeWithTransaction($code, $mode, $allow_write, $writeBlockedByConfig);
        } finally {
            if ($useAlarm) {
                ExecutionGuard::stopTimeout();
            }
            ExecutionGuard::endRequest();
            set_time_limit($previousLimit);
        }

        if (
            isset($result['error']['class'])
            && $result['error']['class'] === ExecutionTimedOutException::class
        ) {
            $result['timed_out'] = true;
            $result['effective_timeout_seconds'] = $effectiveTimeout;
        }

        $result['metrics'] = [
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'memory_delta_mb' => round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'queries_executed' => $this->getQueryCount() - $startQueries,
        ];

        if ($area !== '') {
            $result['area'] = $area;
        }

        return $result;
    }

    #[McpTool(
        name: 'code-runner-help',
        description: 'Returns detailed code-runner usage guide with helpers, variables, areas, and examples'
    )]
    public function getHelp(): array
    {
        return [
            'helpers' => [
                'get(string $class)' => 'Get a singleton instance (same as $om->get())',
                'create(string $class, array $args = [])' => 'Create a new instance (same as $om->create())',
                'repo(string $class)' => 'Get a repository instance (alias for get())',
                'config(string $path, string $scope = "default", int $scopeId = 0)' => 'Read system config value',
                'query(string $sql, array $binds = [])' => 'Execute a read-only SELECT query. Returns array of rows.',
                'runLog(mixed $value, string $label = "")'
                    => 'Capture a value to return to the agent in the "log" key of the response.',
            ],
            'variables' => [
                '$om, $di, $objectManager' => 'ObjectManager instance',
                '$get, $create, $repo, $config' => 'Closure versions of the helper functions',
            ],
            'areas' => [
                'frontend' => 'Frontend store context',
                'adminhtml' => 'Admin panel context',
                'webapi_rest' => 'REST API context',
                'webapi_soap' => 'SOAP API context',
                'graphql' => 'GraphQL context',
                'crontab' => 'Cron job context',
                '' => 'Default — no area emulation (default)',
            ],
            'parameters' => [
                'code (string, required)' => 'PHP code to execute. Do not include <?php tags.',
                'area (string, optional)' => 'Magento area to emulate. Empty for default.',
                'allow_write (bool, default false)' => 'When false, DB changes are rolled back automatically.',
                'timeout (int, default 30)' => 'Max execution time in seconds.',
                'mode (string, default execute)'
                    => 'execute = run code, define = save reusable functions for the session.',
            ],
            'examples' => [
                'Batch product lookup' => '$repo = repo(\Magento\Catalog\Api\ProductRepositoryInterface::class);'
                    . "\n" . '$results = []; foreach (["SKU1","SKU2"] as $s) '
                    . '{ $p = $repo->get($s); $results[$s] = $p->getName(); }'
                    . "\n" . 'return $results;',
                'Count orders by status' => '$resource = get(\Magento\Framework\App\ResourceConnection::class);'
                    . "\n" . '$conn = $resource->getConnection();'
                    . "\n" . 'return $conn->fetchAll("SELECT status, COUNT(*) as cnt '
                    . 'FROM sales_order GROUP BY status");',
                'Check config value' => 'return config("general/locale/code");',
                'Inspect DI preference'
                    => 'return get_class(get(\Magento\Catalog\Api\ProductRepositoryInterface::class));',
                'Log multiple results' => 'runLog(config("general/locale/code"), "locale");'
                    . "\n" . 'runLog(query("SELECT COUNT(*) as cnt FROM catalog_product_entity"), "product count");'
                    . "\n" . '// Both values appear in the "log" key of the response',
            ],
            'reusable_functions' => [
                'define' => 'Use mode=define to save functions for the session:'
                    . "\n" . '  code-runner mode=define code="function getProductBySku($sku) '
                    . '{ return get(\Magento\Catalog\Api\ProductRepositoryInterface::class)->get($sku); }"',
                'usage' => 'Defined functions are available in all subsequent code-runner calls:'
                    . "\n" . '  code-runner code="$product = getProductBySku(\'my-sku\'); return $product->getName();"',
                'limit' => 'Maximum 20 defined functions per session.',
                'clearing' => 'Functions are cleared on reinitialize.',
            ],
            'safety' => [
                'read_only_mode' => 'By default, a DB transaction wraps execution and is rolled back. '
                    . 'Set allow_write=true to persist.',
                'blocked_functions' => 'exec, shell_exec, system, passthru, eval, unlink, '
                    . 'file_put_contents, exit, die, header',
                'production' => 'code-runner is completely disabled in production deploy mode.',
                'timeout' => 'Default 30s, max configurable via tools.code-runner.max_timeout in config.',
            ],
        ];
    }

    /**
     * Store a function definition for use in subsequent code-runner calls.
     *
     * @return array<string, mixed>
     */
    private function defineFunction(string $code): array
    {
        $validationError = $this->validateCode($code);
        if ($validationError !== null) {
            return [
                'error' => true,
                'message' => $validationError,
                'code' => $code,
            ];
        }

        // Extract function names from the code
        preg_match_all('/function\s+([a-zA-Z_]\w*)\s*\(/', $code, $matches);
        $functionNames = $matches[1];

        if (empty($functionNames)) {
            return [
                'error' => true,
                'message' => 'No function declarations found in code. '
                    . 'mode=define requires at least one "function name(...)" declaration.',
            ];
        }

        // Check capacity limit
        $newCount = count(self::$definedFunctions) + count($functionNames);
        if ($newCount > self::MAX_DEFINED_FUNCTIONS) {
            return [
                'error' => true,
                'message' => sprintf(
                    'Function limit reached. Maximum %d defined functions allowed (current: %d, requested: %d). '
                    . 'Call reinitialize to clear stored functions.',
                    self::MAX_DEFINED_FUNCTIONS,
                    count(self::$definedFunctions),
                    count($functionNames)
                ),
            ];
        }

        // Names already declared earlier in THIS process cannot be redeclared (PHP fatals on
        // redeclare; reinitialize clears the stored list but cannot undeclare a function).
        // Capture them BEFORE declaring so we can tell the caller their new body won't take effect.
        $alreadyDeclared = array_values(array_filter($functionNames, 'function_exists'));

        // Declare into the global namespace once; surfaces syntax errors at define time
        // instead of as a fatal on the next execute.
        try {
            $this->registerDefinedFunctions($code, $functionNames);
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Failed to declare function(s): ' . $e->getMessage(),
                'code' => $code,
            ];
        }

        // Track names for the capacity limit, listing, and the API response.
        foreach ($functionNames as $name) {
            self::$definedFunctions[$name] = $code;
        }

        $response = [
            'success' => true,
            'message' => sprintf(
                'Defined %d function(s): %s. Available in all subsequent code-runner calls.',
                count($functionNames),
                implode(', ', $functionNames)
            ),
            'defined_functions' => array_keys(self::$definedFunctions),
            'total_defined' => count(self::$definedFunctions),
        ];

        if ($alreadyDeclared !== []) {
            $response['warning'] = sprintf(
                'Already declared earlier in this server process and NOT redeclared: %s. '
                . 'The original definition remains in effect — restart the MCP server to redefine.',
                implode(', ', $alreadyDeclared)
            );
        }

        return $response;
    }

    /**
     * Get the names of all currently defined functions.
     *
     * @return list<string>
     */
    public static function getDefinedFunctions(): array
    {
        return array_keys(self::$definedFunctions);
    }

    /**
     * Clear all stored function definitions.
     */
    public static function clearDefinedFunctions(): void
    {
        self::$definedFunctions = [];
    }

    private function executeWithTransaction(
        string $code,
        string $mode,
        bool $allowWrite,
        bool $writeBlockedByConfig = false
    ): array {
        $connection = null;
        $rolledBack = false;

        if (!$allowWrite) {
            try {
                $resource = MagentoBootstrap::get(
                    \Magento\Framework\App\ResourceConnection::class
                );
                $connection = $resource->getConnection();
                $connection->beginTransaction();
            } catch (\Throwable $e) {
                $connection = null;
            }
        }

        try {
            if (class_exists(\Psy\Shell::class)) {
                $result = $this->executeWithPsySH($code, $mode);
            } else {
                $result = $this->executeSimple($code, $mode);
            }
        } finally {
            if ($connection !== null) {
                try {
                    $connection->rollBack();
                    $rolledBack = true;
                } catch (\Throwable $e) {
                    $result['rollback_warning'] = 'Transaction rollback failed: ' . $e->getMessage();
                }
            }
        }

        if ($rolledBack) {
            $result['read_only'] = true;
            if ($writeBlockedByConfig) {
                $result['write_blocked_by_config'] = true;
                $result['note'] = 'Database changes were rolled back (read-only mode). '
                    . 'allow_write=true was requested but is disabled by config: '
                    . 'set "tools.code-runner.allow_write": true in .bricklayer.json to persist changes.';
            } else {
                $result['note'] = 'Database changes were rolled back (read-only mode). '
                    . 'Use allow_write=true to persist changes.';
            }
        }

        if ($allowWrite) {
            $result['read_only'] = false;
            $result['note'] = 'Write mode — database changes were persisted.';
        }

        return $result;
    }

    private function executeWithPsySH(string $code, string $mode): array
    {
        return $this->runPsysh($code, $mode, $this->buildScopeVariables());
    }

    /**
     * Run code through a PsySH shell with the given scope variables.
     *
     * PsySH's Shell::execute() writes through its $output property, which is a typed
     * property only initialised when the shell runs interactively. For programmatic use
     * we must setOutput() first (otherwise it throws "Typed property Psy\Shell::$output
     * must not be accessed before initialization"). We also pass throwExceptions=true so
     * user-code exceptions propagate here for clean reporting instead of PsySH trying to
     * write them through its output.
     *
     * @param array<string, mixed> $scopeVariables
     * @return array<string, mixed>
     */
    private function runPsysh(string $code, string $mode, array $scopeVariables): array
    {
        try {
            // Expose the bare helper functions (get/create/repo/config/query/runLog) as
            // real global functions so the documented bare form works in PsySH, not only
            // the $get/$create closures from setScopeVariables(). The functions delegate
            // to $GLOBALS, refreshed here each call, so they stay bound to the live OM.
            $this->registerHelperGlobals($scopeVariables);

            $config = new \Psy\Configuration([
                'updateCheck' => 'never',
                'usePcntl' => false,
                'useReadline' => false,
            ]);

            $shell = new \Psy\Shell($config);
            $shell->setOutput(new BufferedOutput());
            $shell->setScopeVariables($scopeVariables);

            ob_start();
            $error = null;
            $returnValue = null;

            try {
                $returnValue = $shell->execute($code, true);
                $returnValue = $this->formatReturnValue($returnValue);
            } catch (\Throwable $e) {
                $error = [
                    'type' => 'exception',
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            $output = ob_get_clean();

            $result = [
                'success' => $error === null,
                'output' => $output ?: null,
                'return' => $returnValue,
                'error' => $error,
                'mode' => $mode,
                'runtime' => 'psysh',
            ];

            if (!empty($this->logBuffer)) {
                $result['log'] = $this->logBuffer;
            }

            return $result;
        } catch (\Throwable $e) {
            return $this->errorResponse('PsySH execution failed: ' . $e->getMessage(), ['mode' => $mode]);
        }
    }

    private function executeSimple(string $code, string $mode): array
    {
        try {
            $vars = $this->buildScopeVariables();
            extract($vars);

            // Declare the bare helper functions in the global namespace (once) and refresh
            // the $GLOBALS delegation map for this call. Shared with the PsySH path.
            $this->registerHelperGlobals($vars);

            ob_start();
            $error = null;
            $returnValue = null;

            try {
                $wrappedCode = 'return (function($di, $om, $objectManager, $get, $create, $repo, '
                    . '$config, $query, $runLog) { '
                    . $code . ' ; return null; })($di, $om, $objectManager, $get, '
                    . '$create, $repo, $config, $query, $runLog);';
                $returnValue = eval($wrappedCode);
                $returnValue = $this->formatReturnValue($returnValue);
            } catch (\Throwable $e) {
                $error = [
                    'type' => 'exception',
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            $output = ob_get_clean();

            $result = [
                'success' => $error === null,
                'output' => $output ?: null,
                'return' => $returnValue,
                'error' => $error,
                'mode' => $mode,
                'runtime' => 'eval',
                'warning' => 'PsySH not available, using basic eval fallback',
            ];

            if (!empty($this->logBuffer)) {
                $result['log'] = $this->logBuffer;
            }

            return $result;
        } catch (\Throwable $e) {
            return $this->errorResponse('Execution failed: ' . $e->getMessage(), ['mode' => $mode]);
        }
    }

    private function buildScopeVariables(): array
    {
        $this->logBuffer = [];

        $objectManager = MagentoBootstrap::getObjectManager();

        $get = function (string $class) use ($objectManager) {
            return $objectManager->get($class);
        };

        $create = function (string $class, array $args = []) use ($objectManager) {
            return $objectManager->create($class, $args);
        };

        $repo = function (string $class) use ($objectManager) {
            return $objectManager->get($class);
        };

        $config = function (string $path, string $scopeType = 'default', int $scopeId = 0)
 use ($objectManager) {
            $scopeConfig = $objectManager->get(
                \Magento\Framework\App\Config\ScopeConfigInterface::class
            );
            $scope = match ($scopeType) {
                'websites' => \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                'stores' => \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                default => \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            };
            return $scopeConfig->getValue($path, $scope, $scopeId);
        };

        $query = function (string $sql, array $binds = []) use ($objectManager): array {
            if (!preg_match('/^\s*SELECT\s/i', $sql)) {
                throw new \RuntimeException('query() helper only supports SELECT statements');
            }
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            return $connection->fetchAll($sql, $binds);
        };

        $runLog = function (mixed $value, string $label = '') {
            $this->logBuffer[] = [
                'label' => $label,
                'value' => $this->formatReturnValue($value),
            ];
        };

        return [
            'di' => $objectManager,
            'om' => $objectManager,
            'objectManager' => $objectManager,
            'get' => $get,
            'create' => $create,
            'repo' => $repo,
            'config' => $config,
            'query' => $query,
            'runLog' => $runLog,
        ];
    }

    /**
     * Make the bare helper functions (get/create/repo/config/query/runLog) callable as
     * real global functions in both the PsySH and eval runtimes.
     *
     * The functions are declared in the GLOBAL namespace exactly once per process (the MCP
     * server is long-lived) via an explicit `namespace { }` block — declaring them directly
     * in this namespaced file would create Inchoo\...\Tool\get, which unqualified user calls
     * would not resolve to in PsySH's global scope. They delegate to $GLOBALS, which is
     * refreshed on every call so the closures stay bound to the current ObjectManager rather
     * than a stale one captured at first declaration.
     *
     * @param array<string, mixed> $scopeVariables
     */
    private function registerHelperGlobals(array $scopeVariables): void
    {
        $keys = ['get', 'create', 'repo', 'config', 'query', 'runLog'];
        $GLOBALS['_bricklayer_helpers'] = array_intersect_key($scopeVariables, array_flip($keys));

        if (self::$helpersRegistered) {
            return;
        }

        eval(
            'namespace {'
            . ' if (!function_exists("get")) { function get(string $class) {'
            . ' return ($GLOBALS["_bricklayer_helpers"]["get"])($class); } }'
            . ' if (!function_exists("create")) { function create(string $class, array $args = []) {'
            . ' return ($GLOBALS["_bricklayer_helpers"]["create"])($class, $args); } }'
            . ' if (!function_exists("repo")) { function repo(string $class) {'
            . ' return ($GLOBALS["_bricklayer_helpers"]["repo"])($class); } }'
            . ' if (!function_exists("config")) {'
            . ' function config(string $path, string $scopeType = "default", int $scopeId = 0) {'
            . ' return ($GLOBALS["_bricklayer_helpers"]["config"])($path, $scopeType, $scopeId); } }'
            . ' if (!function_exists("query")) { function query(string $sql, array $binds = []) {'
            . ' return ($GLOBALS["_bricklayer_helpers"]["query"])($sql, $binds); } }'
            . ' if (!function_exists("runLog")) { function runLog($value, string $label = "") {'
            . ' ($GLOBALS["_bricklayer_helpers"]["runLog"])($value, $label); } }'
            . '}'
        );

        self::$helpersRegistered = true;
    }

    /**
     * Declare the session's defined functions in the GLOBAL namespace, once each.
     *
     * The MCP server process is long-lived, so a function can be declared only once —
     * PHP fatals on redeclare. Each function is therefore eval'd into the global namespace
     * behind a function_exists() guard, instead of prepending raw declarations to every
     * execute() call (which redeclared on the second call and fataled).
     *
     * Global namespace is required so BOTH runtimes resolve a bare call(): PsySH executes in
     * the global namespace, and the eval runtime's namespaced scope falls back to the global
     * function table for unqualified function calls. Mirrors registerHelperGlobals().
     *
     * @param list<string> $functionNames Names declared in $code (already extracted + validated).
     */
    private function registerDefinedFunctions(string $code, array $functionNames): void
    {
        if ($functionNames === []) {
            return;
        }

        $guards = [];
        foreach ($functionNames as $name) {
            $guards[] = '!function_exists(' . var_export($name, true) . ')';
        }

        // Guard on ALL names so a multi-function block is emitted atomically. A redefine
        // that overlaps an already-declared name is skipped whole (see the caller's warning);
        // this is strictly safer than the previous hard redeclare fatal.
        eval('namespace { if (' . implode(' && ', $guards) . ') {' . "\n" . $code . "\n" . '} }');
    }

    private function validateCode(string $code): ?string
    {
        foreach (self::DANGEROUS_PATTERNS as $pattern => $message) {
            if (preg_match($pattern, $code)) {
                return $message;
            }
        }

        return null;
    }

    /**
     * JSON-RPC id of the in-flight request, for the fatal-error shutdown hook.
     * The SDK injects RequestContext into RequestContext-typed parameters
     * (excluded from the tool's input schema); null outside a live MCP request.
     */
    private function resolveRequestId(?RequestContext $context): int|string|null
    {
        try {
            return $context?->getRequest()->getId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getMaxTimeout(): int
    {
        try {
            return (int) $this->getConfigLoader()->get('tools.code-runner.max_timeout', 60);
        } catch (\Throwable $e) {
            return 60;
        }
    }

    /**
     * Reset Magento application state between code-runner calls.
     *
     * The MCP server is a long-lived process, so singletons retain state
     * across invocations. This clears known stateful singletons to prevent
     * errors like "Registry key already exists" or "Layer already created".
     */
    private function resetApplicationState(): void
    {
        if (!MagentoBootstrap::isInitialized()) {
            return;
        }

        $om = MagentoBootstrap::getObjectManager();

        // Clear the Registry (current_category, current_product, etc.)
        try {
            $registry = $om->get(\Magento\Framework\Registry::class);
            foreach (
                [
                'current_category', 'current_product', 'current_order', 'current_customer',
                'current_invoice', 'current_shipment', 'current_creditmemo', 'current_cms_page'
                ] as $key
            ) {
                $registry->unregister($key);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Reset stateful singletons only if they were already instantiated.
        // Using the ObjectManager's shared instance pool avoids eagerly
        // creating singletons that were never used.
        $this->resetSharedInstance($om, \Magento\Catalog\Model\Layer\Resolver::class, 'layer');

        // Clear repository identity maps so out-of-process DB writes (e.g. a
        // separate CLI/DDEV call, or a rolled-back transaction) are not masked
        // by stale in-memory entities cached earlier in this long-lived process.
        //
        // The shared instance pool is keyed by the RESOLVED CONCRETE class, not
        // the interface (ObjectManager::get() resolves the preference before
        // keying _sharedInstances), so we must resolve each interface first.
        try {
            $config = $om->get(\Magento\Framework\ObjectManager\ConfigInterface::class);

            $repositoryMaps = [
                \Magento\Catalog\Api\ProductRepositoryInterface::class  => ['instances', 'instancesById'],
                \Magento\Catalog\Api\CategoryRepositoryInterface::class => ['instances'],
            ];
            foreach ($repositoryMaps as $interface => $properties) {
                $concrete = $config->getPreference(ltrim($interface, '\\'));
                foreach ($properties as $property) {
                    $this->resetSharedInstance($om, $concrete, $property);
                }
            }
        } catch (\Throwable $e) {
            // ignore — class may not exist or property may differ
        }

        // Customer entities are not cached on the repository; they live in the
        // CustomerRegistry singleton's identity maps.
        foreach (['customerRegistryById', 'customerRegistryByEmail'] as $property) {
            $this->resetSharedInstance($om, \Magento\Customer\Model\CustomerRegistry::class, $property);
        }
    }

    /**
     * Reset a property on an already-instantiated singleton.
     *
     * Checks the ObjectManager's shared instance pool first so we never
     * trigger dependency resolution for singletons that weren't used.
     */
    private function resetSharedInstance(object $om, string $className, string $propertyName): void
    {
        try {
            $omRef = new \ReflectionProperty($om, '_sharedInstances');
            $omRef->setAccessible(true);
            $sharedInstances = $omRef->getValue($om);

            if (!isset($sharedInstances[$className])) {
                return;
            }

            $instance = $sharedInstances[$className];
            $propRef = new \ReflectionProperty($instance, $propertyName);
            $propRef->setAccessible(true);
            $propRef->setValue($instance, null);
        } catch (\Throwable $e) {
            // ignore — class may not exist or property may differ
        }
    }

    private function getQueryCount(): int
    {
        try {
            $resource = MagentoBootstrap::get(
                \Magento\Framework\App\ResourceConnection::class
            );
            $connection = $resource->getConnection();

            if (method_exists($connection, 'getQueryCount')) {
                return $connection->getQueryCount();
            }

            $result = $connection->fetchRow("SHOW SESSION STATUS LIKE 'Queries'");
            return $result ? (int) ($result['Value'] ?? 0) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function formatReturnValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->formatArray($value);
        }

        if (is_object($value)) {
            return $this->formatObject($value);
        }

        return (string) $value;
    }

    private function formatArray(array $array, int $depth = 0): array
    {
        if ($depth > 3) {
            return ['__truncated__' => 'Max depth reached'];
        }

        $result = [];
        $count = 0;

        foreach ($array as $key => $value) {
            if ($count++ >= 100) {
                $result['__truncated__'] = 'Array truncated at 100 items';
                break;
            }

            if (is_array($value)) {
                $result[$key] = $this->formatArray($value, $depth + 1);
            } elseif (is_object($value)) {
                $result[$key] = $this->formatObject($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function formatObject(object $object): array
    {
        $className = get_class($object);

        if ($object instanceof \Magento\Framework\DataObject) {
            return [
                '__class__' => $className,
                'data' => $this->formatArray($object->getData()),
            ];
        }

        if ($object instanceof \Magento\Framework\Data\Collection) {
            $items = [];
            $count = 0;
            foreach ($object as $item) {
                if ($count++ >= 20) {
                    $items[] = ['__truncated__' => 'Collection truncated at 20 items'];
                    break;
                }
                $items[] = $this->formatObject($item);
            }
            return [
                '__class__' => $className,
                'count' => $object->count(),
                'items' => $items,
            ];
        }

        if ($object instanceof \Magento\Framework\Api\SearchResultsInterface) {
            return [
                '__class__' => $className,
                'total_count' => $object->getTotalCount(),
                'items_count' => count($object->getItems()),
            ];
        }

        if (str_contains($className, 'ExtensionAttributes')) {
            $methods = [];
            $reflection = new \ReflectionClass($object);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (str_starts_with($method->getName(), 'get')) {
                    $attr = lcfirst(substr($method->getName(), 3));
                    try {
                        $val = $method->invoke($object);
                        $methods[$attr] = $this->formatReturnValue($val);
                    } catch (\Throwable $e) {
                        $methods[$attr] = '__error: ' . $e->getMessage() . '__';
                    }
                }
            }
            return [
                '__class__' => $className,
                'extension_attributes' => $methods,
            ];
        }

        if ($object instanceof \Magento\CatalogInventory\Api\Data\StockItemInterface) {
            return [
                '__class__' => $className,
                'qty' => $object->getQty(),
                'is_in_stock' => $object->getIsInStock(),
                'min_qty' => $object->getMinQty(),
                'manage_stock' => $object->getManageStock(),
            ];
        }

        if (method_exists($object, 'getData') && method_exists($object, 'getExtensionAttributes')) {
            $data = ['__class__' => $className];
            try {
                $data['data'] = $this->formatArray((array) $object->getData());
            } catch (\Throwable $e) {
            }
            try {
                $ext = $object->getExtensionAttributes();
                if ($ext !== null) {
                    $data['extension_attributes'] = $this->formatObject($ext);
                }
            } catch (\Throwable $e) {
            }
            return $data;
        }

        return [
            '__class__' => $className,
        ];
    }
}
