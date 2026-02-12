<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Guidelines;

use Mcp\Capability\Attribute\McpTool;

/**
 * Scans tool classes for #[McpTool] attributes and groups them for documentation.
 *
 * This is the single source of truth for tool-to-documentation-section mapping.
 * New tool classes not listed in GROUP_CONFIG auto-generate a section from the class name.
 * New tool methods in existing classes automatically appear in their group's table.
 */
class ToolScanner
{
    /**
     * Maps documentation section title → configuration.
     *
     * 'subtitle' — optional text shown below the section heading.
     * 'columns'  — markdown table column headers.
     * 'order'    — sort order for sections in the output.
     * 'classes'  — short class names (without namespace) that belong to this group.
     */
    private const GROUP_CONFIG = [
        'Introspection Tools (Use First)' => [
            'subtitle' => 'Before generating or modifying code, use these tools to understand context:',
            'columns' => ['Tool', 'When to Use'],
            'order' => 1,
            'classes' => [
                'ApplicationTools',
                'ConfigurationTools',
                'ModuleTools',
                'EavTools',
                'RoutingTools',
            ],
        ],
        'Catalog Tools' => [
            'columns' => ['Tool', 'Purpose'],
            'order' => 2,
            'classes' => ['CatalogTools'],
        ],
        'Order Tools' => [
            'columns' => ['Tool', 'Purpose', 'Prerequisite'],
            'order' => 3,
            'classes' => ['OrderTools'],
        ],
        'Customer Tools' => [
            'columns' => ['Tool', 'Purpose'],
            'order' => 4,
            'classes' => ['CustomerTools'],
        ],
        'Database & Log Tools' => [
            'columns' => ['Tool', 'Purpose'],
            'order' => 5,
            'classes' => ['DatabaseTools', 'LogTools'],
        ],
        'GraphQL Tools' => [
            'columns' => ['Tool', 'Purpose'],
            'order' => 6,
            'classes' => ['GraphqlTools'],
        ],
        'System & Development Tools' => [
            'columns' => ['Tool', 'Purpose'],
            'order' => 7,
            'classes' => ['DevelopmentTools', 'CodeRunnerTools', 'DiagnosticTools', 'SearchTools'],
        ],
        'Code Generation Tools' => [
            'columns' => ['Tool', 'Output'],
            'order' => 8,
            'classes' => ['CodeGenerationTools'],
        ],
    ];

    private const TOOL_NAMESPACE = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\';

    /**
     * Scan tool directory, extract #[McpTool] attributes via reflection.
     *
     * @return array{groups: array<string, array{subtitle?: string, columns: string[], tools: array<int, array{name: string, description: string, meta: array}>}>, totalCount: int}
     */
    public function scan(): array
    {
        $toolDir = dirname(__DIR__) . '/Mcp/Tool';
        $classMap = $this->buildClassToGroupMap();
        $groups = $this->initializeGroups();
        $autoGroupOrder = 100;
        $totalCount = 0;

        foreach (glob($toolDir . '/*.php') as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = self::TOOL_NAMESPACE . $className;

            if (!class_exists($fqcn)) {
                require_once $file;
                if (!class_exists($fqcn)) {
                    continue;
                }
            }

            $tools = $this->extractTools($fqcn);
            if (empty($tools)) {
                continue;
            }

            $totalCount += count($tools);

            if (isset($classMap[$className])) {
                $groupTitle = $classMap[$className];
            } else {
                $groupTitle = $this->deriveGroupTitle($className);
                if (!isset($groups[$groupTitle])) {
                    $groups[$groupTitle] = [
                        'columns' => ['Tool', 'Purpose'],
                        'order' => $autoGroupOrder++,
                        'tools' => [],
                    ];
                }
            }

            foreach ($tools as $tool) {
                $groups[$groupTitle]['tools'][] = $tool;
            }
        }

        // Remove empty groups and sort by order
        $groups = array_filter($groups, fn(array $g) => !empty($g['tools']));
        uasort($groups, fn(array $a, array $b) => $a['order'] <=> $b['order']);

        // Remove the 'order' key from output
        foreach ($groups as &$group) {
            unset($group['order']);
        }

        return ['groups' => $groups, 'totalCount' => $totalCount];
    }

    /**
     * Extract #[McpTool] attributes from all public methods of a class.
     *
     * @return array<int, array{name: string, description: string, meta: array}>
     */
    private function extractTools(string $fqcn): array
    {
        $tools = [];
        $ref = new \ReflectionClass($fqcn);

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(McpTool::class);
            if (empty($attrs)) {
                continue;
            }

            $attr = $attrs[0]->newInstance();
            $tools[] = [
                'name' => $attr->name ?? $method->getName(),
                'description' => $attr->description ?? '',
                'meta' => $attr->meta ?? [],
            ];
        }

        return $tools;
    }

    /**
     * Build a reverse map: short class name → group title.
     *
     * @return array<string, string>
     */
    private function buildClassToGroupMap(): array
    {
        $map = [];
        foreach (self::GROUP_CONFIG as $title => $config) {
            foreach ($config['classes'] as $className) {
                $map[$className] = $title;
            }
        }
        return $map;
    }

    /**
     * Initialize groups from GROUP_CONFIG with empty tool arrays.
     *
     * @return array<string, array{subtitle?: string, columns: string[], order: int, tools: array}>
     */
    private function initializeGroups(): array
    {
        $groups = [];
        foreach (self::GROUP_CONFIG as $title => $config) {
            $group = [
                'columns' => $config['columns'],
                'order' => $config['order'],
                'tools' => [],
            ];
            if (isset($config['subtitle'])) {
                $group['subtitle'] = $config['subtitle'];
            }
            $groups[$title] = $group;
        }
        return $groups;
    }

    /**
     * Derive a human-readable group title from a class name.
     *
     * "FooBarTools" → "Foo Bar Tools"
     * "MyCustom" → "My Custom"
     */
    private function deriveGroupTitle(string $className): string
    {
        // Insert space before uppercase letters, then trim
        $spaced = preg_replace('/(?<!^)([A-Z])/', ' $1', $className);
        return trim($spaced);
    }
}
