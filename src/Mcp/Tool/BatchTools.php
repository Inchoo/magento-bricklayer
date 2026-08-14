<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;

class BatchTools
{
    private const MAX_OPERATIONS = 20;

    /**
     * Tools that cannot be called from batch-execute (prevent recursion, code execution).
     */
    private const BLOCKED_TOOLS = [
        'batch-execute',
        'code-runner',
        'code-runner-help',
    ];

    #[McpTool(
        name: 'batch-execute',
        description: 'Run multiple tools in one call. Input: JSON array of {tool, params} objects. Max 20 per call. Cannot nest batch-execute or code-runner.'
    )]
    public function batchExecute(string $operations_json): array
    {
        // Parse JSON input
        $operations = json_decode($operations_json, true);
        if (!is_array($operations) || json_last_error() !== JSON_ERROR_NONE) {
            return [
                'error' => true,
                'message' => 'Invalid JSON. Expected array of {tool, params} objects.',
            ];
        }

        if (empty($operations)) {
            return ['error' => true, 'message' => 'No operations provided.'];
        }

        if (count($operations) > self::MAX_OPERATIONS) {
            return [
                'error' => true,
                'message' => sprintf('Too many operations. Maximum is %d per call.', self::MAX_OPERATIONS),
            ];
        }

        // Validate all operations before executing any
        $registry = ToolRegistry::getInstance()->getAllCallables();
        $validationErrors = [];

        foreach ($operations as $i => $op) {
            if (!isset($op['tool']) || !is_string($op['tool'])) {
                $validationErrors[] = "Operation {$i}: missing or invalid 'tool' key";
                continue;
            }
            if (!isset($op['params'])) {
                $op['params'] = [];
            }
            if (!is_array($op['params'])) {
                $validationErrors[] = "Operation {$i}: 'params' must be an object";
                continue;
            }
            if (in_array($op['tool'], self::BLOCKED_TOOLS, true)) {
                $validationErrors[] = "Operation {$i}: tool '{$op['tool']}' is not allowed in batch";
                continue;
            }
            if (!isset($registry[$op['tool']])) {
                $validationErrors[] = "Operation {$i}: unknown tool '{$op['tool']}'";
            }
        }

        if (!empty($validationErrors)) {
            return [
                'error' => true,
                'message' => 'Validation failed',
                'validation_errors' => $validationErrors,
            ];
        }

        // Execute operations
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($operations as $i => $op) {
            $toolName = $op['tool'];
            $params = $op['params'] ?? [];

            try {
                [$instance, $method] = $registry[$toolName];
                $args = $this->resolveArguments($method, $params);
                $result = $method->invokeArgs($instance, $args);

                $isError = isset($result['error']) && $result['error'] === true;

                if ($isError) {
                    $failCount++;
                    $results[] = [
                        'index' => $i,
                        'tool' => $toolName,
                        'status' => 'error',
                        'message' => $result['message'] ?? 'Unknown error',
                    ];
                } else {
                    $successCount++;
                    $results[] = [
                        'index' => $i,
                        'tool' => $toolName,
                        'status' => 'ok',
                        'summary' => $this->summarizeResult($toolName, $params, $result),
                    ];
                }
            } catch (\Throwable $e) {
                $failCount++;
                $results[] = [
                    'index' => $i,
                    'tool' => $toolName,
                    'status' => 'exception',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'total' => count($operations),
            'succeeded' => $successCount,
            'failed' => $failCount,
            'results' => $results,
        ];
    }

    /**
     * Resolve named params to positional args based on method reflection.
     */
    private function resolveArguments(\ReflectionMethod $method, array $params): array
    {
        $args = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Missing required parameter: {$name}");
            }
        }

        return $args;
    }

    /**
     * Create a short summary of a tool result to minimize context usage.
     */
    private function summarizeResult(string $toolName, array $params, array $result): string
    {
        return match ($toolName) {
            'product-stock-update' => sprintf('Updated stock for %s', $params['sku'] ?? 'unknown'),
            'product-update' => sprintf('Updated product %s', $params['sku'] ?? 'unknown'),
            'category-update' => sprintf('Updated category %d', $params['categoryId'] ?? 0),
            'customer-update' => sprintf('Updated customer %d', $params['customerId'] ?? 0),
            'order-add-comment' => sprintf('Added comment to order %d', $params['orderId'] ?? 0),
            default => $this->summarizeByPattern($toolName, $result),
        };
    }

    private function summarizeByPattern(string $toolName, array $result): string
    {
        if (str_contains($toolName, 'create')) {
            $id = $result['id'] ?? $result['entity_id'] ?? $result['increment_id'] ?? null;
            return $id ? "Created (ID: {$id})" : 'Created successfully';
        }

        if (str_contains($toolName, 'delete')) {
            return $result['message'] ?? 'Deleted successfully';
        }

        if (isset($result['success'])) {
            return $result['success'] ? 'OK' : 'Failed';
        }

        return 'Completed';
    }
}
