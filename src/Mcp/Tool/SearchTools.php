<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;

/**
 * Search Tools
 *
 * Provides semantic search capabilities for Magento documentation.
 */
class SearchTools
{
    /**
     * Documentation index organized by topic
     */
    private const DOCUMENTATION_INDEX = [
        'module' => [
            'keywords' => ['module', 'registration', 'composer', 'etc/module.xml'],
            'topics' => [
                'Module structure and file organization',
                'Module registration with ComponentRegistrar',
                'Module dependencies in module.xml',
                'Composer package configuration',
            ],
        ],
        'di' => [
            'keywords' => ['di', 'dependency injection', 'preference', 'plugin', 'type', 'virtualtype'],
            'topics' => [
                'Dependency injection configuration in di.xml',
                'Preferences for interface implementation',
                'Plugin (interceptor) configuration',
                'Virtual types for object customization',
                'Type arguments and constructor injection',
            ],
        ],
        'controller' => [
            'keywords' => ['controller', 'action', 'route', 'frontend', 'adminhtml'],
            'topics' => [
                'Controller action classes',
                'Route configuration in routes.xml',
                'Frontend vs adminhtml controllers',
                'Result types (Page, Json, Redirect, Forward)',
            ],
        ],
        'model' => [
            'keywords' => ['model', 'resource', 'collection', 'repository', 'entity'],
            'topics' => [
                'Model classes extending AbstractModel',
                'Resource models for database operations',
                'Collection classes for data retrieval',
                'Repository pattern implementation',
                'Service contracts and interfaces',
            ],
        ],
        'api' => [
            'keywords' => ['api', 'rest', 'webapi', 'endpoint', 'service contract'],
            'topics' => [
                'REST API endpoint configuration in webapi.xml',
                'Service contract interfaces with @api annotation',
                'Authentication and ACL resources',
                'Data interfaces for API responses',
            ],
        ],
        'graphql' => [
            'keywords' => ['graphql', 'resolver', 'schema', 'query', 'mutation'],
            'topics' => [
                'GraphQL schema definition in schema.graphqls',
                'Resolver implementation for queries and mutations',
                'DataProvider for GraphQL data sources',
                'Type and input type definitions',
            ],
        ],
        'eav' => [
            'keywords' => ['eav', 'attribute', 'entity', 'catalog_product', 'customer'],
            'topics' => [
                'EAV (Entity-Attribute-Value) system overview',
                'Custom attribute creation',
                'Attribute sets and groups',
                'Backend and frontend models',
                'Source models for attribute options',
            ],
        ],
        'plugin' => [
            'keywords' => ['plugin', 'interceptor', 'before', 'after', 'around'],
            'topics' => [
                'Before plugins for modifying method arguments',
                'After plugins for modifying return values',
                'Around plugins for full method control',
                'Plugin sort order and naming conventions',
            ],
        ],
        'observer' => [
            'keywords' => ['observer', 'event', 'dispatch', 'events.xml'],
            'topics' => [
                'Event observer pattern in Magento',
                'Observer configuration in events.xml',
                'Event dispatching with EventManager',
                'Available events and their parameters',
            ],
        ],
        'layout' => [
            'keywords' => ['layout', 'xml', 'block', 'template', 'container'],
            'topics' => [
                'Layout XML structure and handles',
                'Block classes and templates',
                'Containers and reference containers',
                'Layout update instructions',
            ],
        ],
        'cron' => [
            'keywords' => ['cron', 'schedule', 'job', 'crontab.xml'],
            'topics' => [
                'Cron job configuration in crontab.xml',
                'Cron groups and scheduling',
                'Cron job implementation classes',
            ],
        ],
        'indexer' => [
            'keywords' => ['indexer', 'index', 'reindex', 'mview'],
            'topics' => [
                'Custom indexer implementation',
                'Indexer configuration in indexer.xml',
                'Materialized views (mview)',
                'Index management and scheduling',
            ],
        ],
        'cache' => [
            'keywords' => ['cache', 'flush', 'invalidate', 'tag'],
            'topics' => [
                'Cache types and configuration',
                'Cache tags for invalidation',
                'Full page cache (FPC)',
                'Block caching with cache keys',
            ],
        ],
        'database' => [
            'keywords' => ['database', 'schema', 'db_schema', 'patch', 'setup'],
            'topics' => [
                'Declarative schema in db_schema.xml',
                'Data patches for data migration',
                'Schema patches for schema changes',
                'Whitelist generation and management',
            ],
        ],
        'testing' => [
            'keywords' => ['test', 'phpunit', 'integration', 'unit', 'mftf'],
            'topics' => [
                'Unit testing with PHPUnit',
                'Integration testing framework',
                'API functional tests',
                'Magento Functional Testing Framework (MFTF)',
            ],
        ],
    ];

    /**
     * Searches Magento documentation based on query.
     *
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return array<string, mixed> Search results
     */
    #[McpTool(
        name: 'search-docs',
        description: 'Searches Magento documentation for relevant topics and guidance'
    )]
    public function searchDocs(string $query, int $limit = 10): array
    {
        if ($query === '') {
            return ['error' => true, 'message' => 'Query is required'];
        }

        $queryLower = strtolower($query);
        $results = [];

        foreach (self::DOCUMENTATION_INDEX as $category => $data) {
            $score = 0;

            // Check keyword matches
            foreach ($data['keywords'] as $keyword) {
                if (str_contains($queryLower, strtolower($keyword))) {
                    $score += 10;
                }
                if (str_contains(strtolower($keyword), $queryLower)) {
                    $score += 5;
                }
            }

            // Check topic matches
            foreach ($data['topics'] as $topic) {
                if (str_contains(strtolower($topic), $queryLower)) {
                    $score += 3;
                }
            }

            if ($score > 0) {
                $results[] = [
                    'category' => $category,
                    'score' => $score,
                    'keywords' => $data['keywords'],
                    'topics' => $data['topics'],
                ];
            }
        }

        // Sort by score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        // Limit results
        $results = array_slice($results, 0, $limit);

        // Generate guidance based on top results
        $guidance = $this->generateGuidance($query, $results);

        return [
            'query' => $query,
            'result_count' => count($results),
            'results' => $results,
            'guidance' => $guidance,
        ];
    }

    /**
     * Generate guidance based on search results
     *
     * @param string $query
     * @param array<array<string, mixed>> $results
     * @return string
     */
    private function generateGuidance(string $query, array $results): string
    {
        if (empty($results)) {
            return "No direct matches found. Try using more specific Magento terminology " .
                   "like 'plugin', 'observer', 'di', 'controller', 'model', 'api', 'graphql', etc.";
        }

        $topCategories = array_slice(array_column($results, 'category'), 0, 3);
        $guidance = "Based on your query '$query', the most relevant Magento topics are: " .
                    implode(', ', $topCategories) . ".\n\n";

        foreach (array_slice($results, 0, 2) as $result) {
            $guidance .= "**{$result['category']}**: " . implode('; ', array_slice($result['topics'], 0, 2)) . "\n";
        }

        return $guidance;
    }
}
