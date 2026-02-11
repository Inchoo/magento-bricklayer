<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Mcp\Capability\Attribute\McpTool;

/**
 * Code Runner Tools
 *
 * Provides PHP code execution capabilities within Magento context.
 * Uses PsySH for safe, sandboxed code execution with helper functions,
 * area emulation, transaction rollback, and execution metrics.
 */
class CodeRunnerTools
{
    /**
     * Dangerous patterns that should not be allowed
     */
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

    /**
     * Executes PHP code within the Magento application context.
     *
     * Use this to test repository calls, inspect DI resolution, debug data,
     * query EAV attributes, or verify fix hypotheses.
     *
     * Available helpers: get(class), create(class, args), repo(class), config(path).
     * Default mode is read-only (DB changes are rolled back).
     * Disabled in production.
     *
     * @param string $code PHP code to execute (without <?php tags)
     * @param string $area Magento area for DI resolution (frontend, adminhtml, webapi_rest, graphql, crontab, global). Empty = use current.
     * @param bool $allow_write When false (default), DB changes are rolled back after execution
     * @param int $timeout Maximum execution time in seconds
     * @return array<string, mixed> Execution result with output, return value, metrics, and any errors
     */
    #[McpTool(
        name: 'code-runner',
        description: 'Executes PHP code within the Magento application context. '
            . 'Use this to test repository calls, inspect DI resolution, debug data, '
            . 'query EAV attributes, or verify fix hypotheses. '
            . 'Available helpers: get(class), create(class, args), repo(class), config(path). '
            . 'Default mode is read-only (DB changes are rolled back). '
            . 'Disabled in production.'
    )]
    public function execute(
        string $code,
        string $area = '',
        bool $allow_write = false,
        int $timeout = 30
    ): array {
        // 1. Bootstrap check
        if (!MagentoBootstrap::isInitialized()) {
            return [
                'success' => false,
                'error' => 'Magento not initialized. Code runner requires a bootstrapped Magento environment.',
            ];
        }

        // 2. Production mode guard
        try {
            $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
            $mode = $state->getMode();

            if ($mode === \Magento\Framework\App\State::MODE_PRODUCTION) {
                return [
                    'success' => false,
                    'error' => 'Code runner is disabled in production mode for security reasons.',
                    'mode' => $mode,
                ];
            }
        } catch (\Throwable $e) {
            $mode = 'unknown';
        }

        // 3. Config-level kill switch and write policy enforcement
        try {
            $configLoader = new ConfigLoader();
            if (!$configLoader->isToolEnabled('code-runner')) {
                return ['success' => false, 'error' => 'Code runner is disabled in configuration.'];
            }
            $configAllowWrite = (bool) $configLoader->get('tools.code-runner.allow_write', false);
            if ($allow_write && !$configAllowWrite) {
                $allow_write = false;
            }
        } catch (\Throwable $e) {
            // Config loading failed — proceed with defaults
        }

        // 4. Code validation
        $validationError = $this->validateCode($code);
        if ($validationError !== null) {
            return [
                'success' => false,
                'error' => $validationError,
                'code' => $code,
            ];
        }

        // 5. Area emulation
        if ($area !== '') {
            $areaEmulator = new AreaEmulator();
            if (!$areaEmulator->isValidArea($area)) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        'Invalid area "%s". Available: %s',
                        $area,
                        implode(', ', $areaEmulator->getAvailableAreas())
                    ),
                ];
            }
            $areaEmulator->setArea($area);
        }

        // 6. Timeout enforcement
        $maxTimeout = $this->getMaxTimeout();
        $effectiveTimeout = min($timeout, $maxTimeout);
        $previousLimit = (int) ini_get('max_execution_time');
        set_time_limit($effectiveTimeout);

        // 7. Metrics start
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startQueries = $this->getQueryCount();

        // 8. Execute with transaction wrapping
        try {
            $result = $this->executeWithTransaction($code, $mode, $allow_write);
        } finally {
            set_time_limit($previousLimit);
        }

        // 9. Metrics end
        $result['metrics'] = [
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'memory_delta_mb' => round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'queries_executed' => $this->getQueryCount() - $startQueries,
        ];

        // 10. Add area info to response
        if ($area !== '') {
            $result['area'] = $area;
        }

        return $result;
    }

    /**
     * Execute code with optional transaction wrapping for read-only mode.
     *
     * @param string $code
     * @param string $mode
     * @param bool $allowWrite
     * @return array<string, mixed>
     */
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

    /**
     * Execute code using PsySH
     *
     * @param string $code
     * @param string $mode
     * @return array<string, mixed>
     */
    private function executeWithPsySH(string $code, string $mode): array
    {
        try {
            // Configure PsySH for non-interactive execution
            $config = new \Psy\Configuration([
                'updateCheck' => 'never',
                'usePcntl' => false,
                'useReadline' => false,
            ]);

            $shell = new \Psy\Shell($config);

            // Set up scope variables with helper functions
            $shell->setScopeVariables($this->buildScopeVariables());

            // Capture output
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

            return [
                'success' => $error === null,
                'output' => $output ?: null,
                'return' => $returnValue,
                'error' => $error,
                'mode' => $mode,
                'runtime' => 'psysh',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'PsySH execution failed: ' . $e->getMessage(),
                'mode' => $mode,
            ];
        }
    }

    /**
     * Simple execution fallback without PsySH
     *
     * @param string $code
     * @param string $mode
     * @return array<string, mixed>
     */
    private function executeSimple(string $code, string $mode): array
    {
        try {
            $vars = $this->buildScopeVariables();
            extract($vars);

            // Capture output
            ob_start();
            $error = null;
            $returnValue = null;

            try {
                // Wrap code to capture the return value — inject all helpers
                $wrappedCode = 'return (function($di, $om, $objectManager, $get, $create, $repo, $config) { '
                    . $code . ' ; return null; })($di, $om, $objectManager, $get, $create, $repo, $config);';
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

            return [
                'success' => $error === null,
                'output' => $output ?: null,
                'return' => $returnValue,
                'error' => $error,
                'mode' => $mode,
                'runtime' => 'eval',
                'warning' => 'PsySH not available, using basic eval fallback',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Execution failed: ' . $e->getMessage(),
                'mode' => $mode,
            ];
        }
    }

    /**
     * Build scope variables with helper functions for code execution.
     *
     * @return array<string, mixed>
     */
    private function buildScopeVariables(): array
    {
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

        return [
            'di' => $objectManager,
            'om' => $objectManager,
            'objectManager' => $objectManager,
            'get' => $get,
            'create' => $create,
            'repo' => $repo,
            'config' => $config,
        ];
    }

    /**
     * Validate code for dangerous operations
     *
     * @param string $code
     * @return string|null Error message if validation fails, null if valid
     */
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
     * Get maximum timeout from configuration.
     */
    private function getMaxTimeout(): int
    {
        try {
            $configLoader = new ConfigLoader();
            return (int) $configLoader->get('tools.code-runner.max_timeout', 60);
        } catch (\Throwable $e) {
            return 60;
        }
    }

    /**
     * Get current DB query count for metrics.
     */
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

            $result = $connection->fetchOne("SHOW SESSION STATUS LIKE 'Queries'");
            return $result ? (int) $result : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Format return value for JSON serialization
     *
     * @param mixed $value
     * @return mixed
     */
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

    /**
     * Format array for output
     *
     * @param array<mixed> $array
     * @param int $depth
     * @return array<mixed>
     */
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

    /**
     * Format object for output
     *
     * @param object $object
     * @return array<string, mixed>
     */
    private function formatObject(object $object): array
    {
        $className = get_class($object);

        // Handle Magento DataObject and models
        if ($object instanceof \Magento\Framework\DataObject) {
            return [
                '__class__' => $className,
                'data' => $this->formatArray($object->getData()),
            ];
        }

        // Handle collections
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

        // Handle search results
        if ($object instanceof \Magento\Framework\Api\SearchResultsInterface) {
            return [
                '__class__' => $className,
                'total_count' => $object->getTotalCount(),
                'items_count' => count($object->getItems()),
            ];
        }

        // Handle ExtensionAttributesInterface — show available getter methods
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

        // Handle StockItemInterface
        if ($object instanceof \Magento\CatalogInventory\Api\Data\StockItemInterface) {
            return [
                '__class__' => $className,
                'qty' => $object->getQty(),
                'is_in_stock' => $object->getIsInStock(),
                'min_qty' => $object->getMinQty(),
                'manage_stock' => $object->getManageStock(),
            ];
        }

        // Handle AbstractExtensibleObject — getData() + extension attributes
        if (method_exists($object, 'getData') && method_exists($object, 'getExtensionAttributes')) {
            $data = ['__class__' => $className];
            try {
                $data['data'] = $this->formatArray((array) $object->getData());
            } catch (\Throwable $e) {
                // getData() may fail
            }
            try {
                $ext = $object->getExtensionAttributes();
                if ($ext !== null) {
                    $data['extension_attributes'] = $this->formatObject($ext);
                }
            } catch (\Throwable $e) {
                // Extension attributes may fail
            }
            return $data;
        }

        // Generic object
        return [
            '__class__' => $className,
        ];
    }
}
