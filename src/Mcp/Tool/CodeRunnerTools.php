<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Mcp\Capability\Attribute\McpTool;

class CodeRunnerTools
{
    use ChecksConfig;
    use RequiresMagento;

    /** @var array<int, array{label: string, value: mixed}> */
    private array $logBuffer = [];

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
        description: 'Executes PHP in Magento context. Preferred for multi-step operations — one call replaces many tool calls. '
            . 'Helpers: get(class), create(class), repo(class), config(path). '
            . 'Read-only by default. Disabled in production. Call code-runner-help for docs.'
    )]
    public function execute(
        string $code,
        string $area = '',
        bool $allow_write = false,
        int $timeout = 30
    ): array {
        if ($error = $this->requireMagento()) {
            return $error;
        }

        // Hard block in production mode (code-runner is always blocked, no config override)
        try {
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $mode = $state->getMode();

            if ($mode === \Magento\Framework\App\State::MODE_PRODUCTION) {
                return [
                    'error' => true,
                    'message' => 'Code runner is disabled in production mode for security reasons.',
                    'mode' => $mode,
                ];
            }
        } catch (\Throwable $e) {
            $mode = 'unknown';
        }

        if ($error = $this->requireToolEnabled('code-runner')) {
            return $error;
        }

        try {
            $configAllowWrite = (bool) $this->getConfigLoader()->get('tools.code-runner.allow_write', false);
            if ($allow_write && !$configAllowWrite) {
                $allow_write = false;
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
        $effectiveTimeout = min($timeout, $maxTimeout);
        $previousLimit = (int) ini_get('max_execution_time');
        set_time_limit($effectiveTimeout);

        $this->resetApplicationState();

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startQueries = $this->getQueryCount();
        $this->logBuffer = [];

        try {
            $result = $this->executeWithTransaction($code, $mode, $allow_write);
        } finally {
            set_time_limit($previousLimit);
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
                'runLog(mixed $value, string $label = "")' => 'Capture a value to return to the agent in the "log" key of the response.',
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
            ],
            'examples' => [
                'Batch product lookup' => '$repo = repo(\Magento\Catalog\Api\ProductRepositoryInterface::class);'
                    . "\n" . '$results = []; foreach (["SKU1","SKU2"] as $s) { $p = $repo->get($s); $results[$s] = $p->getName(); }'
                    . "\n" . 'return $results;',
                'Count orders by status' => '$resource = get(\Magento\Framework\App\ResourceConnection::class);'
                    . "\n" . '$conn = $resource->getConnection();'
                    . "\n" . 'return $conn->fetchAll("SELECT status, COUNT(*) as cnt FROM sales_order GROUP BY status");',
                'Check config value' => 'return config("general/locale/code");',
                'Inspect DI preference' => 'return get_class(get(\Magento\Catalog\Api\ProductRepositoryInterface::class));',
                'Log multiple results' => 'runLog(config("general/locale/code"), "locale");'
                    . "\n" . 'runLog(query("SELECT COUNT(*) as cnt FROM catalog_product_entity"), "product count");'
                    . "\n" . '// Both values appear in the "log" key of the response',
            ],
            'safety' => [
                'read_only_mode' => 'By default, a DB transaction wraps execution and is rolled back. Set allow_write=true to persist.',
                'blocked_functions' => 'exec, shell_exec, system, passthru, eval, unlink, file_put_contents, exit, die, header',
                'production' => 'code-runner is completely disabled in production deploy mode.',
                'timeout' => 'Default 30s, max configurable via tools.code-runner.max_timeout in config.',
            ],
        ];
    }

    private function executeWithTransaction(string $code, string $mode, bool $allowWrite): array
    {
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
                // If PsySH failed with an internal error, try simple fallback
                if (!$result['success'] && isset($result['error']['class']) && str_contains($result['error']['class'], 'Error')) {
                    $result = $this->executeSimple($code, $mode);
                }
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
            $result['note'] = 'Database changes were rolled back (read-only mode). '
                . 'Use allow_write=true to persist changes.';
        }

        if ($allowWrite) {
            $result['read_only'] = false;
            $result['note'] = 'Write mode — database changes were persisted.';
        }

        return $result;
    }

    private function executeWithPsySH(string $code, string $mode): array
    {
        try {
            $config = new \Psy\Configuration([
                'updateCheck' => 'never',
                'usePcntl' => false,
                'useReadline' => false,
            ]);

            $shell = new \Psy\Shell($config);

            $shell->setScopeVariables($this->buildScopeVariables());

            ob_start();
            $error = null;
            $returnValue = null;

            try {
                $returnValue = $shell->execute($code);
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
            return [
                'error' => true,
                'message' => 'PsySH execution failed: ' . $e->getMessage(),
                'mode' => $mode,
            ];
        }
    }

    private function executeSimple(string $code, string $mode): array
    {
        try {
            $vars = $this->buildScopeVariables();
            extract($vars);

            ob_start();
            $error = null;
            $returnValue = null;

            try {
                $preamble = '$GLOBALS["_bricklayer_helpers"] = compact("get", "create", "repo", "config", "query", "runLog");'
                    . 'if (!function_exists("get")) {'
                    . '  function get(string $class) { return ($GLOBALS["_bricklayer_helpers"]["get"])($class); }'
                    . '  function create(string $class, array $args = []) { return ($GLOBALS["_bricklayer_helpers"]["create"])($class, $args); }'
                    . '  function repo(string $class) { return ($GLOBALS["_bricklayer_helpers"]["repo"])($class); }'
                    . '  function config(string $path, string $scopeType = "default", int $scopeId = 0) { return ($GLOBALS["_bricklayer_helpers"]["config"])($path, $scopeType, $scopeId); }'
                    . '  function query(string $sql, array $binds = []) { return ($GLOBALS["_bricklayer_helpers"]["query"])($sql, $binds); }'
                    . '  function runLog($value, string $label = \'\') { ($GLOBALS["_bricklayer_helpers"]["runLog"])($value, $label); }'
                    . '}';

                $wrappedCode = 'return (function($di, $om, $objectManager, $get, $create, $repo, $config, $query, $runLog) { '
                    . $preamble . ' ' . $code . ' ; return null; })($di, $om, $objectManager, $get, $create, $repo, $config, $query, $runLog);';
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
            return [
                'error' => true,
                'message' => 'Execution failed: ' . $e->getMessage(),
                'mode' => $mode,
            ];
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
            use ($objectManager)
        {
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

    private function validateCode(string $code): ?string
    {
        foreach (self::DANGEROUS_PATTERNS as $pattern => $message) {
            if (preg_match($pattern, $code)) {
                return $message;
            }
        }

        return null;
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
            foreach (['current_category', 'current_product', 'current_order', 'current_customer', 'current_invoice', 'current_shipment', 'current_creditmemo', 'current_cms_page'] as $key) {
                $registry->unregister($key);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Reset stateful singletons only if they were already instantiated.
        // Using the ObjectManager's shared instance pool avoids eagerly
        // creating singletons that were never used.
        $this->resetSharedInstance($om, \Magento\Catalog\Model\Layer\Resolver::class, 'layer');
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
            if ($count++ > 100) {
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
