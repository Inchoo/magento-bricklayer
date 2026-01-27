<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Mcp\Capability\Attribute\McpTool;

/**
 * Code Runner Tools
 *
 * Provides PHP code execution capabilities within Magento context.
 * Uses PsySH for safe, sandboxed code execution.
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
    ];

    /**
     * Executes PHP code within the Magento application context.
     * Returns the result of the last expression or captured output.
     *
     * The code has access to:
     * - $di (ObjectManager) for dependency injection
     * - $om (alias for ObjectManager)
     * - All Magento classes via fully qualified names
     *
     * This tool is disabled in production mode for security.
     *
     * @param string $code PHP code to execute (without <?php tags)
     * @return array<string, mixed> Execution result with output, return value, and any errors
     */
    #[McpTool(
        name: 'code-runner',
        description: 'Executes PHP code in Magento context (disabled in production)'
    )]
    public function execute(string $code): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return [
                'success' => false,
                'error' => 'Magento not initialized. Code runner requires a bootstrapped Magento environment.',
            ];
        }

        // Security check: disable in production mode
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
            // Cannot determine mode, proceed with caution
            $mode = 'unknown';
        }

        // Validate code for dangerous operations
        $validationError = $this->validateCode($code);
        if ($validationError !== null) {
            return [
                'success' => false,
                'error' => $validationError,
                'code' => $code,
            ];
        }

        // Check if PsySH is available
        if (!class_exists(\Psy\Shell::class)) {
            // Fallback to simple eval if PsySH is not available
            return $this->executeSimple($code, $mode);
        }

        // Try PsySH, fallback to simple eval if it fails
        $result = $this->executeWithPsySH($code, $mode);
        if (!$result['success'] && isset($result['error']['class']) && str_contains($result['error']['class'], 'Error')) {
            // PsySH failed with an internal error, try simple fallback
            return $this->executeSimple($code, $mode);
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
            $objectManager = MagentoBootstrap::getObjectManager();

            // Configure PsySH for non-interactive execution
            $config = new \Psy\Configuration([
                'updateCheck' => 'never',
                'usePcntl' => false,
                'useReadline' => false,
            ]);

            $shell = new \Psy\Shell($config);

            // Set up scope variables
            $shell->setScopeVariables([
                'di' => $objectManager,
                'om' => $objectManager,
                'objectManager' => $objectManager,
            ]);

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
            $objectManager = MagentoBootstrap::getObjectManager();

            // Set up variables available to the code
            $di = $objectManager;
            $om = $objectManager;

            // Capture output
            ob_start();
            $error = null;
            $returnValue = null;

            try {
                // Wrap code to capture the return value
                $wrappedCode = 'return (function($di, $om) { ' . $code . ' ; return null; })($di, $om);';
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

        // Generic object
        return [
            '__class__' => $className,
        ];
    }
}
