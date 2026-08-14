<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;

/**
 * Central registry for all MCP tools.
 *
 * Scans tool classes once, caches the results, and provides accessors
 * for tool metadata, callables, and counts.
 */
class ToolRegistry
{
    private static ?self $instance = null;

    /** @var array<string, array{object, \ReflectionMethod}> */
    private array $callables = [];

    /** @var array<int, array{name: string, description: string, class: string, group: string, parameters: array}> */
    private array $metadata = [];

    private int $count = 0;

    private const TOOL_GROUPS = [
        'introspection' => [
            'ApplicationTools', 'ConfigurationTools', 'ModuleTools', 'EavTools', 'RoutingTools',
            'ViewTools', 'MessageQueueTools',
        ],
        'catalog' => ['CatalogTools'],
        'orders' => ['OrderTools'],
        'customers' => ['CustomerTools'],
        'database' => ['DatabaseTools'],
        'logs' => ['LogTools'],
        'diagnostic' => ['DiagnosticTools', 'PerformanceTools'],
        'graphql' => ['GraphqlTools'],
        'development' => ['DevelopmentTools', 'CodeRunnerTools', 'SearchTools', 'BatchTools'],
        'code-generation' => ['CodeGenerationTools'],
        'context' => ['ContextTools'],
    ];

    private function __construct()
    {
        $this->scan();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Reset the singleton (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Get a callable for a tool by name.
     *
     * @return array{object, \ReflectionMethod}|null
     */
    public function getCallable(string $toolName): ?array
    {
        return $this->callables[$toolName] ?? null;
    }

    /**
     * Get all callables keyed by tool name.
     *
     * @return array<string, array{object, \ReflectionMethod}>
     */
    public function getAllCallables(): array
    {
        return $this->callables;
    }

    /**
     * Get metadata for all tools (name, description, class, group, parameters).
     *
     * @return array<int, array{name: string, description: string, class: string, group: string, parameters: array}>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the total number of registered tools.
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Get tool group definitions.
     *
     * @return array<string, list<string>>
     */
    public function getGroups(): array
    {
        return self::TOOL_GROUPS;
    }

    private function scan(): void
    {
        $toolDir = __DIR__;
        $namespace = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\';

        $classToGroup = [];
        foreach (self::TOOL_GROUPS as $groupName => $classes) {
            foreach ($classes as $className) {
                $classToGroup[$className] = $groupName;
            }
        }

        foreach (glob($toolDir . '/*.php') as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = $namespace . $className;

            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);

            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            $instance = null;
            $group = $classToGroup[$className] ?? 'other';

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(McpTool::class);
                if (empty($attrs)) {
                    continue;
                }

                $attr = $attrs[0]->newInstance();
                $toolName = $attr->name ?? $method->getName();

                if ($instance === null) {
                    $instance = $ref->newInstance();
                }

                $this->callables[$toolName] = [$instance, $method];

                $parameters = [];
                foreach ($method->getParameters() as $param) {
                    $type = $param->getType();
                    $paramInfo = [
                        'name' => $param->getName(),
                        'type' => $type ? $type->getName() : 'mixed',
                        'required' => !$param->isOptional(),
                    ];
                    if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                        $paramInfo['default'] = $param->getDefaultValue();
                    }
                    $parameters[] = $paramInfo;
                }

                $this->metadata[] = [
                    'name' => $toolName,
                    'description' => $attr->description ?? '',
                    'class' => $className,
                    'group' => $group,
                    'parameters' => $parameters,
                ];

                $this->count++;
            }
        }

        usort($this->metadata, fn($a, $b) => strcmp($a['name'], $b['name']));
    }
}
