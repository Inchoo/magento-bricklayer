<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;

class SearchTools
{
    private const TOOL_GROUPS = [
        'introspection' => ['ApplicationTools', 'ConfigurationTools', 'ModuleTools', 'EavTools', 'RoutingTools'],
        'catalog' => ['CatalogTools'],
        'orders' => ['OrderTools'],
        'customers' => ['CustomerTools'],
        'database' => ['DatabaseTools'],
        'logs' => ['LogTools'],
        'diagnostic' => ['DiagnosticTools'],
        'graphql' => ['GraphqlTools'],
        'development' => ['DevelopmentTools', 'CodeRunnerTools', 'SearchTools', 'BatchTools'],
        'code-generation' => ['CodeGenerationTools'],
        'context' => ['ContextTools'],
    ];

    /** @var array<int, array{name: string, description: string, class: string, group: string, parameters: array}>|null */
    private static ?array $toolCache = null;

    private const DOCUMENTATION_INDEX = [

        'module' => [
            'keywords' => ['module', 'registration', 'composer', 'etc/module.xml', 'scaffold'],
            'topics' => [
                'Module structure and file organization',
                'Module registration with ComponentRegistrar',
                'Module dependencies in module.xml',
                'Composer package configuration',
            ],
            'tools' => ['module-list', 'module-structure', 'validate-module', 'generate-module'],
            'dev_context' => 'module',
        ],
        'di' => [
            'keywords' => ['di', 'dependency injection', 'type', 'virtualtype'],
            'topics' => [
                'Dependency injection configuration in di.xml',
                'Preferences for interface implementation',
                'Plugin (interceptor) configuration',
                'Virtual types for object customization',
                'Type arguments and constructor injection',
            ],
            'tools' => ['di-configuration', 'plugin-list', 'preference-list'],
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
        'model' => [
            'keywords' => ['model', 'resource', 'collection', 'repository', 'entity', 'service contract'],
            'topics' => [
                'Model classes extending AbstractModel',
                'Resource models for database operations',
                'Collection classes for data retrieval',
                'Repository pattern implementation',
                'Service contracts and interfaces',
            ],
            'tools' => ['database-schema', 'generate-model'],
            'dev_context' => 'model',
        ],
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
        'graphql' => [
            'keywords' => ['graphql', 'resolver', 'schema', 'schema.graphqls'],
            'topics' => [
                'GraphQL schema definition in schema.graphqls',
                'Resolver implementation for queries and mutations',
                'DataProvider for GraphQL data sources',
                'Type and input type definitions',
            ],
            'tools' => ['graphql-types', 'graphql-type-info', 'graphql-queries', 'graphql-mutations', 'graphql-resolvers'],
            'dev_context' => 'graphql',
        ],
        'eav' => [
            'keywords' => ['eav', 'attribute', 'entity', 'catalog_product', 'attribute set', 'source model'],
            'topics' => [
                'EAV (Entity-Attribute-Value) system overview',
                'Custom attribute creation',
                'Attribute sets and groups',
                'Backend and frontend models',
                'Source models for attribute options',
            ],
            'tools' => ['eav-attributes', 'eav-entity-types'],
            'dev_context' => 'eav',
        ],
        'plugin' => [
            'keywords' => ['plugin', 'interceptor', 'before', 'after', 'around'],
            'topics' => [
                'Before plugins for modifying method arguments',
                'After plugins for modifying return values',
                'Around plugins for full method control',
                'Plugin sort order and naming conventions',
            ],
            'tools' => ['plugin-list', 'di-configuration'],
            'dev_context' => 'plugin',
        ],
        'observer' => [
            'keywords' => ['observer', 'event', 'dispatch', 'events.xml'],
            'topics' => [
                'Event observer pattern in Magento',
                'Observer configuration in events.xml',
                'Event dispatching with EventManager',
                'Available events and their parameters',
            ],
            'tools' => ['event-list'],
            'dev_context' => 'observer',
        ],
        'layout' => [
            'keywords' => ['layout', 'xml', 'block', 'template', 'container', 'handle'],
            'topics' => [
                'Layout XML structure and handles',
                'Block classes and templates',
                'Containers and reference containers',
                'Layout update instructions',
            ],
            'dev_context' => 'frontend',
        ],
        'cron' => [
            'keywords' => ['cron', 'schedule', 'job', 'crontab.xml', 'cron group'],
            'topics' => [
                'Cron job configuration in crontab.xml',
                'Cron groups and scheduling',
                'Cron job implementation classes',
            ],
            'tools' => ['cron-list', 'cron-history'],
            'dev_context' => 'cron',
        ],
        'indexer' => [
            'keywords' => ['indexer', 'index', 'reindex', 'mview', 'indexer.xml'],
            'topics' => [
                'Custom indexer implementation',
                'Indexer configuration in indexer.xml',
                'Materialized views (mview)',
                'Index management and scheduling',
            ],
            'tools' => ['indexer-status'],
            'dev_context' => 'indexer',
        ],
        'cache' => [
            'keywords' => ['cache', 'flush', 'invalidate', 'tag', 'fpc', 'varnish', 'full page cache'],
            'topics' => [
                'Cache types and configuration',
                'Cache tags for invalidation',
                'Full page cache (FPC)',
                'Block caching with cache keys',
            ],
            'tools' => ['cache-status'],
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
        'testing' => [
            'keywords' => ['test', 'phpunit', 'integration', 'unit', 'mftf', 'api functional'],
            'topics' => [
                'Unit testing with PHPUnit',
                'Integration testing framework',
                'API functional tests',
                'Magento Functional Testing Framework (MFTF)',
            ],
            'dev_context' => 'testing',
        ],

        'hyva-checkout' => [
            'keywords' => ['hyva checkout', 'hyvä checkout', 'magewire', 'livewire', 'hyva'],
            'topics' => [
                'Hyvä Checkout Magewire component development',
                'Hyvä Checkout XML configuration and layout',
                'Hyvä Checkout evaluation, form, and frontend APIs',
            ],
            'dev_context' => 'hyva-checkout',
        ],
        'hyva-theme' => [
            'keywords' => ['hyva theme', 'hyvä theme', 'alpine', 'alpinejs', 'tailwind', 'csp', 'hyva'],
            'topics' => [
                'Hyvä theme setup and Alpine.js CSP components',
                'Hyvä ViewModels, module compatibility, and customization',
                'Hyvä UI component CSS and design system',
                'Hyvä UI component Alpine.js and interactivity',
            ],
            'dev_context' => 'hyva-theme',
        ],
        'payment' => [
            'keywords' => ['payment', 'gateway', 'authorize', 'capture', 'vault', 'refund', 'payment method'],
            'topics' => [
                'Payment method module setup and configuration',
                'Payment gateway components (builders, handlers, validators)',
                'Payment checkout integration and frontend',
            ],
            'dev_context' => 'payment',
        ],
        'checkout' => [
            'keywords' => ['checkout', 'step', 'layout processor', 'config provider', 'checkout customization'],
            'topics' => [
                'Checkout custom steps and layout processors',
                'Checkout config providers, mixins, and validation',
                'Checkout frontend integration and JavaScript',
            ],
            'dev_context' => 'checkout',
        ],
        'preference' => [
            'keywords' => ['preference', 'rewrite', 'class override', 'class replacement'],
            'topics' => [
                'Class preference (rewrite) configuration in di.xml',
                'When to use preferences vs plugins',
                'Preference best practices and limitations',
            ],
            'tools' => ['preference-list'],
            'dev_context' => 'preference',
        ],
        'theme' => [
            'keywords' => ['theme', 'less', 'css', 'phtml', 'requirejs', 'static content', 'theme inheritance'],
            'topics' => [
                'Theme structure, layout XML, and templates',
                'Theme LESS/CSS styling and JavaScript',
                'Theme inheritance and fallback mechanism',
                'Static content deployment',
            ],
            'dev_context' => 'theme',
        ],
        'shipping' => [
            'keywords' => ['shipping', 'carrier', 'rate', 'tracking', 'delivery', 'shipment method'],
            'topics' => [
                'Shipping carrier integration and development',
                'Rate calculation and request handling',
                'Tracking number implementation',
            ],
            'dev_context' => 'shipping',
        ],
        'ui-component' => [
            'keywords' => ['ui component', 'grid', 'listing', 'form', 'data provider', 'admin grid', 'admin form'],
            'topics' => [
                'Admin UI component grid and listing configuration',
                'Admin UI component form configuration',
                'Data providers for UI components',
                'UI component XML configuration',
            ],
            'dev_context' => 'ui-component',
        ],
        'message-queue' => [
            'keywords' => ['message queue', 'amqp', 'rabbitmq', 'consumer', 'publisher', 'queue', 'async'],
            'topics' => [
                'Message queue and async processing',
                'AMQP/RabbitMQ configuration',
                'Consumer and publisher implementation',
                'Queue topology and communication.xml',
            ],
            'dev_context' => 'message-queue',
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
        'frontend' => [
            'keywords' => ['frontend', 'knockout', 'knockoutjs', 'requirejs', 'phtml', 'template'],
            'topics' => [
                'Frontend development (layout, templates, JS)',
                'KnockoutJS templates and bindings',
                'RequireJS module configuration',
                'Frontend template and block rendering',
            ],
            'dev_context' => 'frontend',
        ],
        'adminhtml' => [
            'keywords' => ['admin', 'adminhtml', 'backend', 'acl', 'menu', 'system config', 'system.xml'],
            'topics' => [
                'Admin panel development and routing',
                'ACL resource configuration',
                'Admin menu configuration in menu.xml',
                'System configuration in system.xml',
            ],
            'dev_context' => 'adminhtml',
        ],
        'coding-standards' => [
            'keywords' => ['coding standard', 'psr-12', 'phpcs', 'strict types', 'code quality', 'formatting'],
            'topics' => [
                'PHP coding standards and PSR-12 compliance',
                'Code quality rules and best practices',
                'strict_types declaration requirements',
            ],
            'dev_context' => 'coding-standards',
        ],
        'security' => [
            'keywords' => ['security', 'xss', 'csrf', 'form key', 'escaping', 'sanitize', 'vulnerability'],
            'topics' => [
                'Security best practices and guidelines',
                'XSS prevention and output escaping',
                'CSRF protection and form keys',
                'Input validation and sanitization',
            ],
            'dev_context' => 'security',
        ],
        'performance' => [
            'keywords' => ['performance', 'optimization', 'n+1', 'profiler', 'slow', 'bottleneck'],
            'topics' => [
                'Performance optimization guidelines',
                'N+1 query detection and prevention',
                'Profiling and bottleneck analysis',
                'Caching strategies for performance',
            ],
            'dev_context' => 'performance',
        ],
        'data-patch' => [
            'keywords' => ['data patch', 'schema patch', 'migration', 'setup:upgrade', 'patch'],
            'topics' => [
                'Data patch development and best practices',
                'Schema patch for database changes',
                'Patch dependencies and ordering',
                'Migration from legacy install/upgrade scripts',
            ],
            'dev_context' => 'data-patch',
        ],

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
            'tools' => ['log-read', 'log-list', 'log-analyze', 'log-search', 'diagnose-error'],
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
            'tools' => ['diagnose-error', 'log-analyze', 'log-read', 'cache-status', 'indexer-status'],
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
            'tools' => ['application-info', 'store-configuration', 'deploy-mode', 'code-runner', 'validate-module'],
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
            'tools' => ['configuration-get', 'configuration-list', 'di-configuration', 'plugin-list', 'event-list', 'preference-list'],
        ],
    ];

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
                $results[] = [
                    'category' => $category,
                    'score' => $score,
                    'keywords' => $data['keywords'],
                    'topics' => $data['topics'],
                ];
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
        description: 'Search available MCP tools by keyword or group. Use detail parameter to control response size: names, summary, or full.'
    )]
    public function searchTools(
        string $query = '',
        string $group = '',
        string $detail = 'summary'
    ): array {
        if (!in_array($detail, ['names', 'summary', 'full'], true)) {
            return ['error' => true, 'message' => 'detail must be one of: names, summary, full'];
        }

        $allTools = $this->scanAllTools();

        // Filter by group
        if ($group !== '') {
            if (!isset(self::TOOL_GROUPS[$group])) {
                return [
                    'error' => true,
                    'message' => sprintf(
                        'Unknown group "%s". Available: %s',
                        $group,
                        implode(', ', array_keys(self::TOOL_GROUPS))
                    ),
                ];
            }
            $allowedClasses = self::TOOL_GROUPS[$group];
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
            'available_groups' => array_keys(self::TOOL_GROUPS),
            'tools' => $tools,
        ];
    }

    private function scanAllTools(): array
    {
        if (self::$toolCache !== null) {
            return self::$toolCache;
        }

        $toolDir = dirname(__DIR__) . '/Tool';
        $namespace = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\';

        // Build reverse map: className → group
        $classToGroup = [];
        foreach (self::TOOL_GROUPS as $groupName => $classes) {
            foreach ($classes as $className) {
                $classToGroup[$className] = $groupName;
            }
        }

        $tools = [];

        foreach (glob($toolDir . '/*.php') as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = $namespace . $className;

            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            $group = $classToGroup[$className] ?? 'other';

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attrs = $method->getAttributes(\Mcp\Capability\Attribute\McpTool::class);
                if (empty($attrs)) {
                    continue;
                }

                $attr = $attrs[0]->newInstance();
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

                $tools[] = [
                    'name' => $attr->name ?? $method->getName(),
                    'description' => $attr->description ?? '',
                    'class' => $className,
                    'group' => $group,
                    'parameters' => $parameters,
                ];
            }
        }

        usort($tools, fn($a, $b) => strcmp($a['name'], $b['name']));
        self::$toolCache = $tools;

        return $tools;
    }

    private function generateGuidance(string $query, array $results): string
    {
        if (empty($results)) {
            return "No direct matches found for '$query'. Try searching for:\n" .
                   "- Development: module, plugin, observer, controller, model, eav, graphql, api\n" .
                   "- Hyvä: hyva-checkout, hyva-theme\n" .
                   "- Operations: order, customer, product, category, log, cache, indexer\n" .
                   "- Advanced: payment, shipping, checkout, ui-component, message-queue, import\n" .
                   "- Quality: testing, security, performance, coding-standards\n\n" .
                   "Tip: Use the `development-context` tool with category `list` to see all 37 coding guideline categories.";
        }

        $topCategories = array_slice(array_column($results, 'category'), 0, 3);
        $guidance = "Based on your query '$query', the most relevant Magento topics are: " .
                    implode(', ', $topCategories) . ".\n\n";

        foreach (array_slice($results, 0, 3) as $result) {
            $category = $result['category'];
            $indexData = self::DOCUMENTATION_INDEX[$category];

            $guidance .= "**{$category}**: " . implode('; ', array_slice($result['topics'], 0, 2)) . "\n";

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
