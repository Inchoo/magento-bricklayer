<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Guidelines\LocalOverrideHelper;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ResolvesPackagePaths;
use Inchoo\MagentoBricklayer\Support\CollectsMarkdownFiles;
use Mcp\Capability\Attribute\McpTool;

class SearchTools
{
    use ResolvesPackagePaths;
    use CollectsMarkdownFiles;

    /**
     * Extra search keywords and tool associations for categories in ContextTools::CATEGORY_MAP.
     * These supplement the auto-generated keywords derived from category name, description,
     * guideline paths, and skill names. Only include terms that cannot be derived automatically.
     *
     * @var array<string, array{keywords?: string[], tools?: string[]}>
     */
    private const CATEGORY_SEARCH_EXTRAS = [
        'module' => [
            'keywords' => ['composer', 'etc/module.xml'],
            'tools' => ['module-list', 'module-structure', 'validate-module', 'generate-module'],
        ],
        'model' => [
            'keywords' => ['resource', 'collection', 'entity'],
            'tools' => ['database-schema', 'generate-model'],
        ],
        'plugin' => [
            'keywords' => ['before', 'after', 'around'],
            'tools' => ['plugin-list', 'di-configuration'],
        ],
        'observer' => [
            'keywords' => ['dispatch', 'events.xml'],
            'tools' => ['event-list'],
        ],
        'eav' => [
            'keywords' => ['catalog_product', 'attribute set', 'source model'],
            'tools' => ['eav-attributes', 'eav-entity-types'],
        ],
        'data-patch' => [
            'keywords' => ['migration', 'setup:upgrade'],
        ],
        'graphql' => [
            'keywords' => ['schema.graphqls'],
            'tools' => ['graphql-inspect'],
        ],
        'cron' => [
            'keywords' => ['job', 'schedule', 'crontab.xml', 'cron group'],
            'tools' => ['system-status'],
        ],
        'indexer' => [
            'keywords' => ['index', 'reindex', 'mview', 'indexer.xml'],
            'tools' => ['system-status'],
        ],
        'testing' => [
            'keywords' => ['test', 'phpunit', 'mftf', 'api functional'],
        ],
        'hyva-checkout' => [
            'keywords' => ['hyvä checkout', 'checkout step', 'payment method', 'shipping method'],
        ],
        'magewire' => [
            'keywords' => ['magewire', 'livewire', 'wire:', 'reactive', 'server-driven', '$wire', 'entangle'],
        ],
        'hyva-theme' => [
            'keywords' => ['alpine', 'alpinejs', 'tailwind', 'csp'],
        ],
        'payment' => [
            'keywords' => ['gateway', 'authorize', 'capture', 'vault', 'refund'],
        ],
        'checkout' => [
            'keywords' => ['layout processor', 'config provider'],
        ],
        'preference' => [
            'keywords' => ['class override', 'class replacement'],
            'tools' => ['preference-list'],
        ],
        'theme' => [
            'keywords' => ['less', 'css', 'phtml', 'requirejs', 'static content', 'theme inheritance'],
        ],
        'shipping' => [
            'keywords' => ['rate', 'tracking', 'delivery', 'shipment method'],
        ],
        'ui-component' => [
            'keywords' => ['grid', 'listing', 'form', 'data provider'],
            'tools' => ['ui-component-inspect'],
        ],
        'message-queue' => [
            'keywords' => ['amqp', 'rabbitmq', 'consumer', 'publisher'],
            'tools' => ['message-queue-inspect'],
        ],
        'frontend' => [
            'keywords' => ['knockout', 'knockoutjs', 'requirejs', 'phtml', 'template'],
        ],
        'adminhtml' => [
            'keywords' => ['backend', 'acl', 'menu', 'system config', 'system.xml'],
        ],
        'coding-standards' => [
            'keywords' => ['psr-12', 'phpcs', 'strict types', 'code quality'],
        ],
        'security' => [
            'keywords' => ['xss', 'csrf', 'form key', 'escaping', 'sanitize', 'vulnerability'],
        ],
        'performance' => [
            'keywords' => ['n+1', 'profiler', 'slow', 'bottleneck'],
            'tools' => ['diagnose-performance'],
        ],
    ];

    /**
     * Search index for categories not in ContextTools::CATEGORY_MAP.
     * Includes aliases (common search terms mapping to CATEGORY_MAP categories)
     * and operational categories (tool-focused, no coding guidelines).
     *
     * @var array<string, array{keywords: string[], topics: string[], tools?: string[], dev_context?: string}>
     */
    private const SUPPLEMENTARY_INDEX = [
        // Aliases: common search terms → existing CATEGORY_MAP categories
        'api' => [
            'keywords' => ['api', 'rest', 'webapi', 'endpoint', 'service contract'],
            'topics' => [
                'REST API endpoint configuration in webapi.xml',
                'Service contract interfaces with @api annotation',
                'Authentication and ACL resources',
                'Data interfaces for API responses',
            ],
            'tools' => ['api-endpoints', 'generate-api'],
            'dev_context' => 'rest-api',
        ],
        'layout' => [
            'keywords' => ['layout', 'xml', 'block', 'template', 'container', 'handle'],
            'topics' => [
                'Layout XML structure and handles',
                'Block classes and templates',
                'Containers and reference containers',
                'Layout update instructions',
            ],
            'tools' => ['layout-inspect'],
            'dev_context' => 'frontend',
        ],
        'database' => [
            'keywords' => ['database', 'schema', 'db_schema', 'table', 'setup', 'whitelist'],
            'topics' => [
                'Declarative schema in db_schema.xml',
                'Data patches for data migration',
                'Schema patches for schema changes',
                'Whitelist generation and management',
            ],
            'tools' => ['database-schema', 'database-query'],
            'dev_context' => 'data-patch',
        ],
        'import-export' => [
            'keywords' => ['import', 'export', 'csv', 'bulk', 'import entity', 'export entity'],
            'topics' => [
                'Custom import entity development',
                'Custom export entity development',
                'CSV data processing and validation',
                'Bulk import/export operations',
            ],
            'dev_context' => 'import',
        ],
        // Standalone: no CATEGORY_MAP equivalent
        'di' => [
            'keywords' => ['di', 'dependency injection', 'type', 'virtualtype'],
            'topics' => [
                'Dependency injection configuration in di.xml',
                'Preferences for interface implementation',
                'Plugin (interceptor) configuration',
                'Virtual types for object customization',
                'Type arguments and constructor injection',
            ],
            'tools' => ['check-class', 'di-configuration', 'plugin-list', 'preference-list'],
        ],
        'controller' => [
            'keywords' => ['controller', 'action', 'route', 'routes.xml'],
            'topics' => [
                'Controller action classes',
                'Route configuration in routes.xml',
                'Frontend vs adminhtml controllers',
                'Result types (Page, Json, Redirect, Forward)',
            ],
            'tools' => ['route-list', 'route-info', 'generate-controller'],
        ],
        'cache' => [
            'keywords' => ['cache', 'flush', 'invalidate', 'tag', 'fpc', 'varnish', 'full page cache'],
            'topics' => [
                'Cache types and configuration',
                'Cache tags for invalidation',
                'Full page cache (FPC)',
                'Block caching with cache keys',
            ],
            'tools' => ['system-status'],
        ],
        // Operational: entity CRUD and tool-focused categories
        'orders' => [
            'keywords' => ['order', 'invoice', 'shipment', 'creditmemo', 'credit memo', 'refund', 'fulfillment'],
            'topics' => [
                'Retrieve and search orders by increment ID or filters',
                'Create invoices, shipments, and credit memos',
                'Add tracking numbers and order comments',
                'Cancel, hold, and unhold orders',
                'View order items and status history',
            ],
            'tools' => [
                'order-get', 'order-list', 'order-items', 'order-comments',
                'order-add-comment', 'order-cancel', 'order-hold', 'order-unhold',
                'invoice-create', 'invoice-list',
                'shipment-create', 'shipment-list', 'shipment-track-add',
                'creditmemo-create', 'creditmemo-list',
            ],
        ],
        'customers' => [
            'keywords' => ['customer', 'customer group', 'customer address', 'account'],
            'topics' => [
                'Retrieve and search customers by email or filters',
                'Create, update, and delete customer accounts',
                'Manage customer addresses',
                'View customer groups and customer orders',
                'Validate customer data before create/update',
            ],
            'tools' => [
                'customer-get', 'customer-list', 'customer-create', 'customer-update', 'customer-delete',
                'customer-validate', 'customer-groups-list', 'customer-orders',
                'customer-addresses', 'customer-address-create', 'customer-address-update', 'customer-address-delete',
            ],
        ],
        'products' => [
            'keywords' => ['product', 'sku', 'stock', 'inventory', 'media', 'gallery', 'product link',
                           'related', 'upsell', 'crosssell'],
            'topics' => [
                'Retrieve and search products by SKU or filters',
                'Create, update, and delete products',
                'Manage product stock and inventory',
                'Manage product media gallery images',
                'Set related, upsell, and crosssell product links',
            ],
            'tools' => [
                'product-get', 'product-list', 'product-create', 'product-update', 'product-delete',
                'product-stock-get', 'product-stock-update',
                'product-media-list', 'product-media-add',
                'product-link-list', 'product-link-set',
            ],
        ],
        'categories' => [
            'keywords' => ['category', 'category tree', 'category hierarchy', 'category product'],
            'topics' => [
                'View category tree and hierarchy',
                'Retrieve, create, update, and delete categories',
                'List products in a category',
                'Assign products to categories',
            ],
            'tools' => [
                'category-tree', 'category-get', 'category-create', 'category-update', 'category-delete',
                'category-products', 'category-assign-products',
            ],
        ],
        'code-generation' => [
            'keywords' => ['generate', 'scaffold', 'code generation', 'boilerplate', 'create module',
                           'create model', 'create controller', 'create api'],
            'topics' => [
                'Generate complete module scaffold',
                'Generate model + resource model + collection',
                'Generate controller + routes.xml + layout',
                'Generate REST API endpoint + webapi.xml',
            ],
            'tools' => ['generate-module', 'generate-model', 'generate-controller', 'generate-api'],
        ],
        'logs' => [
            'keywords' => ['log', 'error', 'exception', 'debug', 'system.log', 'exception.log'],
            'topics' => [
                'Read recent entries from Magento log files',
                'List available log files with sizes',
                'Analyze exception log for error patterns and frequency',
                'Search for patterns across all log files',
                'Diagnose errors with full context and fix suggestions',
            ],
            'tools' => ['log', 'diagnose-error'],
        ],
        'diagnostic' => [
            'keywords' => ['diagnose', 'diagnosis', 'troubleshoot', 'debug error', 'fix error',
                           'error analysis', 'stack trace', 'root cause', 'why error'],
            'topics' => [
                'Diagnose Magento errors with full context gathering',
                'Parse exception logs with chained exception support',
                'Correlate errors with module, DI, and environment context',
                'Get actionable fix suggestions with confidence levels',
                'Identify error patterns (class not found, DI, database, search, memory)',
            ],
            'tools' => ['diagnose-error', 'diagnose-performance', 'log', 'system-status'],
        ],
        'code-runner' => [
            'keywords' => ['code runner', 'execute', 'run code', 'tinker', 'repl', 'eval',
                           'test code', 'php code', 'sandbox', 'object manager'],
            'topics' => [
                'Execute PHP code within the Magento application context',
                'Test repository calls and inspect DI resolution',
                'Helper functions: get(class), create(class, args), repo(class), config(path)',
                'Read-only mode with automatic DB transaction rollback',
                'Area emulation for frontend, adminhtml, webapi, graphql contexts',
                'Execution metrics: time, memory, query count',
            ],
            'tools' => ['code-runner'],
        ],
        'routing' => [
            'keywords' => ['route', 'url', 'rewrite', 'url rewrite', 'api endpoint', 'rest endpoint'],
            'topics' => [
                'List all configured routes and route details',
                'List all REST API endpoints',
                'View and manage URL rewrites',
                'Route conflict detection',
            ],
            'tools' => ['route-list', 'route-info', 'api-endpoints', 'url-rewrites'],
        ],
        'system' => [
            'keywords' => ['system', 'application info', 'store', 'deploy mode', 'magento version',
                           'store view', 'website'],
            'topics' => [
                'View Magento version, edition, and application info',
                'View store/website/store view hierarchy',
                'Check deploy mode (developer/production/default)',
                'Execute PHP code in Magento context',
                'Validate module structure and configuration',
            ],
            'tools' => ['application-info', 'system-status', 'code-runner', 'validate-module'],
        ],
        'configuration' => [
            'keywords' => ['configuration', 'config', 'system configuration', 'config value',
                           'core_config_data', 'scope'],
            'topics' => [
                'Retrieve system configuration values by path',
                'List available configuration paths for a section',
                'View DI configuration for classes',
                'List plugins, events, and preferences',
            ],
            'tools' => ['check-class', 'configuration-get', 'configuration-list', 'di-configuration', 'plugin-list', 'event-list', 'preference-list'],
        ],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $documentationIndex = null;

    public function __construct(?string $magentoRoot = null, ?string $packageRoot = null)
    {
        $this->initPackagePaths($magentoRoot, $packageRoot);
    }

    #[McpTool(
        name: 'search-docs',
        description: 'Search Magento documentation topics.',
        meta: ['hidden' => true]
    )]
    public function searchDocs(string $query, int $limit = 10): array
    {
        if ($query === '') {
            return ['error' => true, 'message' => 'Query is required'];
        }

        $queryLower = strtolower($query);
        $index = $this->getDocumentationIndex();
        $results = [];

        foreach ($index as $category => $data) {
            $score = 0;

            foreach ($data['keywords'] as $keyword) {
                if (str_contains($queryLower, strtolower($keyword))) {
                    $score += 10;
                }
                if (str_contains(strtolower($keyword), $queryLower)) {
                    $score += 5;
                }
            }

            foreach ($data['topics'] as $topic) {
                if (str_contains(strtolower($topic), $queryLower)) {
                    $score += 3;
                }
            }

            if ($score > 0) {
                $label = $category;
                if (($data['source'] ?? null) === 'local') {
                    $label = '[Project] ' . ($data['display_name'] ?? $category);
                }
                $results[] = [
                    'category' => $label,
                    'score' => $score,
                    'keywords' => $data['keywords'],
                    'topics' => $data['topics'],
                ];
                if (($data['source'] ?? null) === 'local') {
                    $results[count($results) - 1]['source'] = 'local';
                }
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, $limit);
        $guidance = $this->generateGuidance($query, $results);

        return [
            'query' => $query,
            'result_count' => count($results),
            'results' => $results,
            'guidance' => $guidance,
        ];
    }

    #[McpTool(
        name: 'search-tools',
        description: 'Discover tools by keyword or group. Use detail (names|summary|full) to control response size.'
    )]
    public function searchTools(
        string $query = '',
        string $group = '',
        string $detail = 'summary'
    ): array {
        if (!in_array($detail, ['names', 'summary', 'full'], true)) {
            return ['error' => true, 'message' => 'detail must be one of: names, summary, full'];
        }

        $toolRegistry = ToolRegistry::getInstance();
        $allTools = $toolRegistry->getMetadata();
        $groups = $toolRegistry->getGroups();

        // Filter by group
        if ($group !== '') {
            if (!isset($groups[$group])) {
                return [
                    'error' => true,
                    'message' => sprintf(
                        'Unknown group "%s". Available: %s',
                        $group,
                        implode(', ', array_keys($groups))
                    ),
                ];
            }
            $allowedClasses = $groups[$group];
            $allTools = array_filter($allTools, fn($t) => in_array($t['class'], $allowedClasses, true));
        }

        // Filter by query
        if ($query !== '') {
            $queryLower = strtolower($query);
            $allTools = array_filter($allTools, function ($tool) use ($queryLower) {
                return str_contains(strtolower($tool['name']), $queryLower)
                    || str_contains(strtolower($tool['description']), $queryLower);
            });
        }

        // Format based on detail level
        $tools = array_values(array_map(function ($tool) use ($detail) {
            return match ($detail) {
                'names' => [
                    'name' => $tool['name'],
                    'group' => $tool['group'],
                ],
                'summary' => [
                    'name' => $tool['name'],
                    'group' => $tool['group'],
                    'description' => $tool['description'],
                ],
                'full' => [
                    'name' => $tool['name'],
                    'group' => $tool['group'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            };
        }, $allTools));

        return [
            'query' => $query ?: null,
            'group' => $group ?: null,
            'detail' => $detail,
            'total' => count($tools),
            'available_groups' => array_keys($groups),
            'tools' => $tools,
        ];
    }

    /**
     * Public entry point used by UpdateCommand to get a count of local
     * entries merged into the documentation index for its summary line.
     */
    public function getLocalDocumentationEntryCount(): int
    {
        $count = 0;
        foreach ($this->getDocumentationIndex() as $entry) {
            if (($entry['source'] ?? null) === 'local') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get the documentation index, building it from CATEGORY_MAP on first access.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getDocumentationIndex(): array
    {
        return $this->documentationIndex ??= $this->buildDocumentationIndex();
    }

    /**
     * Build the documentation index by deriving entries from ContextTools::CATEGORY_MAP,
     * merging in supplementary entries, and overlaying local `.bricklayer/` content.
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildDocumentationIndex(): array
    {
        $index = [];
        $stopwords = ['and', 'the', 'for', 'with'];

        foreach (ContextTools::CATEGORY_MAP as $category => $mapping) {
            // Base keyword from category name (hyphens → spaces)
            $keywords = [str_replace('-', ' ', $category)];

            // Meaningful words from description (>3 chars, not stopwords)
            $descTokens = preg_split('/[\s,()]+/', strtolower($mapping['description']), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($descTokens as $word) {
                if (strlen($word) > 3 && !in_array($word, $stopwords, true)) {
                    $keywords[] = $word;
                }
            }

            // Keywords from guideline paths (leaf segment)
            foreach ($mapping['guidelines'] as $guideline) {
                $leaf = basename($guideline);
                $keywords[] = str_replace('-', ' ', $leaf);
                foreach (explode('-', $leaf) as $part) {
                    if (strlen($part) > 3) {
                        $keywords[] = $part;
                    }
                }
            }

            // Keywords from skill names
            foreach ($mapping['skills'] as $skill) {
                foreach (explode('-', $skill) as $part) {
                    if (strlen($part) > 3) {
                        $keywords[] = $part;
                    }
                }
            }

            // Merge curated extras
            if (isset(self::CATEGORY_SEARCH_EXTRAS[$category]['keywords'])) {
                array_push($keywords, ...self::CATEGORY_SEARCH_EXTRAS[$category]['keywords']);
            }

            $entry = [
                'keywords' => array_values(array_unique($keywords)),
                'topics' => [$mapping['description']],
                'dev_context' => $category,
            ];

            if (isset(self::CATEGORY_SEARCH_EXTRAS[$category]['tools'])) {
                $entry['tools'] = self::CATEGORY_SEARCH_EXTRAS[$category]['tools'];
            }

            $index[$category] = $entry;
        }

        // Supplementary: aliases and operational categories
        foreach (self::SUPPLEMENTARY_INDEX as $category => $data) {
            $index[$category] = $data;
        }

        // Overlay local `.bricklayer/` content — local entries replace bundled
        // ones that share a category/path key (override case) and add new
        // entries otherwise.
        foreach ($this->collectLocalDocumentationEntries() as $key => $entry) {
            $index[$key] = $entry;
        }

        return $index;
    }

    /**
     * Scan `.bricklayer/skills/` and `.bricklayer/guidelines/` and produce
     * documentation index entries. Skill directories become categories keyed
     * by their directory name; guideline files become entries keyed by their
     * relative path under `.bricklayer/guidelines/`.
     *
     * @return array<string, array<string, mixed>>
     */
    private function collectLocalDocumentationEntries(): array
    {
        $magentoRoot = $this->resolveMagentoRoot();
        if ($magentoRoot === null) {
            return [];
        }

        $entries = [];

        // --- Local skills ---
        $skillsDir = $magentoRoot . '/.bricklayer/skills/';
        if (is_dir($skillsDir)) {
            $scan = @scandir($skillsDir);
            if ($scan !== false) {
                foreach ($scan as $entryName) {
                    if ($entryName === '.' || $entryName === '..') {
                        continue;
                    }
                    $categoryPath = $skillsDir . $entryName;
                    $skillFile = $categoryPath . '/SKILL.md';
                    if (!is_dir($categoryPath) || !file_exists($skillFile)) {
                        continue;
                    }

                    $category = $entryName;
                    $meta = LocalOverrideHelper::parseSkillFrontmatter($skillFile);
                    $displayName = $meta['name'] ?? LocalOverrideHelper::defaultDisplayName($category);
                    $description = $meta['description'] ?? $displayName;

                    $keywords = [
                        strtolower(str_replace('-', ' ', $category)),
                        strtolower($displayName),
                    ];
                    foreach (explode('-', $category) as $part) {
                        if (strlen($part) > 3) {
                            $keywords[] = strtolower($part);
                        }
                    }
                    $descTokens = preg_split(
                        '/[\s,()]+/',
                        strtolower($description),
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    ) ?: [];
                    foreach ($descTokens as $word) {
                        if (strlen($word) > 3) {
                            $keywords[] = $word;
                        }
                    }

                    $override = isset(ContextTools::CATEGORY_MAP[$category]);

                    $entries[$category] = [
                        'keywords' => array_values(array_unique($keywords)),
                        'topics' => [$description],
                        'dev_context' => $category,
                        'source' => 'local',
                        'override' => $override,
                        'display_name' => $displayName,
                        'path' => '.bricklayer/skills/' . $category . '/SKILL.md',
                    ];
                }
            }
        }

        // --- Local guidelines ---
        $guidelinesDir = $magentoRoot . '/.bricklayer/guidelines/';
        $guidelineDirPrefix = rtrim($guidelinesDir, '/\\') . '/';
        $bundledGuidelinePaths = $this->getBundledGuidelineRelativePaths();

        foreach ($this->collectMarkdownRelativePaths($guidelinesDir) as $relativePath) {
            $content = file_get_contents($guidelineDirPrefix . $relativePath);
            if ($content === false) {
                continue;
            }
            $content = LocalOverrideHelper::stripFrontmatter($content);

            $leaf = basename($relativePath, '.md');
            $dir = dirname($relativePath);
            $categoryGroup = $dir === '.' ? 'project' : basename($dir);
            $displayName = LocalOverrideHelper::defaultDisplayName($leaf);

            $keywords = [
                strtolower(str_replace('-', ' ', $leaf)),
                strtolower(str_replace('-', ' ', $categoryGroup)),
            ];
            foreach (explode('-', $leaf) as $part) {
                if (strlen($part) > 3) {
                    $keywords[] = strtolower($part);
                }
            }
            // Pull meaningful words from the first 200 chars of content
            $contentHead = strtolower(strip_tags(substr($content, 0, 200)));
            foreach (preg_split('/[\s,()]+/', $contentHead, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                if (strlen($word) > 3) {
                    $keywords[] = $word;
                }
            }

            $override = in_array($relativePath, $bundledGuidelinePaths, true);
            $key = 'local-guideline:' . $relativePath;

            $entries[$key] = [
                'keywords' => array_values(array_unique($keywords)),
                'topics' => [$displayName],
                'source' => 'local',
                'override' => $override,
                'display_name' => $displayName,
                'path' => '.bricklayer/guidelines/' . $relativePath,
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function getBundledGuidelineRelativePaths(): array
    {
        return $this->collectMarkdownRelativePaths($this->packageRoot . '/config/guidelines/');
    }

    /**
     * Count the total number of available `development-context` categories:
     * the bundled CATEGORY_MAP entries plus any local-only skill categories
     * in `.bricklayer/skills/` that have no bundled equivalent.
     */
    private function countAllAvailableCategories(): int
    {
        $baseCount = count(ContextTools::CATEGORY_MAP);
        $magentoRoot = $this->resolveMagentoRoot();

        if ($magentoRoot === null) {
            return $baseCount;
        }

        $skillsDir = $magentoRoot . '/.bricklayer/skills/';
        if (!is_dir($skillsDir)) {
            return $baseCount;
        }

        $entries = @scandir($skillsDir);
        if ($entries === false) {
            return $baseCount;
        }

        $localOnlyCount = 0;
        foreach ($entries as $entryName) {
            if ($entryName === '.' || $entryName === '..') {
                continue;
            }
            if (isset(ContextTools::CATEGORY_MAP[$entryName])) {
                continue;
            }
            $skillFile = $skillsDir . $entryName . '/SKILL.md';
            if (is_dir($skillsDir . $entryName) && file_exists($skillFile)) {
                $localOnlyCount++;
            }
        }

        return $baseCount + $localOnlyCount;
    }

    private function generateGuidance(string $query, array $results): string
    {
        if (empty($results)) {
            $categoryCount = $this->countAllAvailableCategories();

            return "No direct matches found for '$query'. Try searching for:\n" .
                   "- Development: module, plugin, observer, controller, model, eav, graphql, api\n" .
                   "- Hyvä: hyva-checkout, hyva-theme\n" .
                   "- Operations: order, customer, product, category, log, cache, indexer\n" .
                   "- Advanced: payment, shipping, checkout, ui-component, message-queue, import\n" .
                   "- Quality: testing, security, performance, coding-standards\n\n" .
                   "Tip: Use the `development-context` tool with category `list` to see all {$categoryCount} coding guideline categories.";
        }

        $topCategories = array_slice(array_column($results, 'category'), 0, 3);
        $guidance = "Based on your query '$query', the most relevant Magento topics are: " .
                    implode(', ', $topCategories) . ".\n\n";

        $index = $this->getDocumentationIndex();

        foreach (array_slice($results, 0, 3) as $result) {
            $label = $result['category'];
            $lookupKey = preg_replace('/^\[Project\]\s*/', '', $label) ?? $label;

            // The index may have entries keyed by display name (for local guidelines/skills)
            // or by category slug (for bundled). Try direct lookup first, then fall back.
            $indexData = $index[$lookupKey] ?? null;
            if ($indexData === null) {
                foreach ($index as $key => $data) {
                    if (($data['display_name'] ?? null) === $lookupKey) {
                        $indexData = $data;
                        break;
                    }
                }
            }
            if ($indexData === null) {
                continue;
            }

            $guidance .= "**{$label}**: " . implode('; ', array_slice($result['topics'], 0, 2)) . "\n";

            if (!empty($indexData['tools'])) {
                $guidance .= "  Relevant tools: " . implode(', ', $indexData['tools']) . "\n";
            }

            if (!empty($indexData['dev_context'])) {
                $guidance .= "  For coding guidelines: use `development-context` with category `{$indexData['dev_context']}`\n";
            }
        }

        return $guidance;
    }
}
