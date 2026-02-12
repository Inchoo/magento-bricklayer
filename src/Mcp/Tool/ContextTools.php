<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;

/**
 * Context Tools
 *
 * Provides development context (guidelines and skills) as a single tool call,
 * enabling agents to load relevant coding patterns before writing code.
 */
class ContextTools
{
    /**
     * Category-to-resource mapping
     *
     * Each category maps to an array with 'skills', 'guidelines', 'description', and 'group' keys.
     * Skills are directory names under config/skills/{name}/SKILL.md.
     * Guidelines are paths under config/guidelines/{path}.md.
     * Group is used by GuidelinesCompiler to group categories under headers.
     *
     * @var array<string, array{skills: string[], guidelines: string[], description: string, group: string}>
     */
    public const CATEGORY_MAP = [
        'hyva-theme' => [
            'skills' => ['hyva-theme-setup'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Hyvä theme setup and Alpine.js CSP components',
            'group' => 'Hyvä Theme',
        ],
        'hyva-theme-advanced' => [
            'skills' => ['hyva-theme-components'],
            'guidelines' => ['ecosystem/hyva-patterns'],
            'description' => 'Hyvä ViewModels, module compatibility, and customization',
            'group' => 'Hyvä Theme',
        ],
        'hyva-ui-component' => [
            'skills' => ['hyva-ui-component-css'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Hyvä UI component CSS and design system',
            'group' => 'Hyvä Theme',
        ],
        'hyva-ui-component-js' => [
            'skills' => ['hyva-ui-component-alpine'],
            'guidelines' => [],
            'description' => 'Hyvä UI component Alpine.js and interactivity',
            'group' => 'Hyvä Theme',
        ],
        'hyva-checkout' => [
            'skills' => ['hyva-checkout-magewire'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Hyvä Checkout Magewire component development',
            'group' => 'Hyvä Theme',
        ],
        'hyva-checkout-config' => [
            'skills' => ['hyva-checkout-configuration'],
            'guidelines' => ['modules/structure'],
            'description' => 'Hyvä Checkout XML configuration and layout',
            'group' => 'Hyvä Theme',
        ],
        'hyva-checkout-api' => [
            'skills' => ['hyva-checkout-apis'],
            'guidelines' => [],
            'description' => 'Hyvä Checkout evaluation, form, and frontend APIs',
            'group' => 'Hyvä Theme',
        ],
        'module' => [
            'skills' => [],
            'guidelines' => ['modules/structure', 'modules/registration', 'modules/dependencies', 'modules/versioning'],
            'description' => 'Module scaffolding and structure',
            'group' => 'Module Development',
        ],
        'model' => [
            'skills' => [],
            'guidelines' => ['patterns/repository', 'patterns/service-contract', 'database/declarative-schema'],
            'description' => 'Model, repository, and data layer development',
            'group' => 'Module Development',
        ],
        'plugin' => [
            'skills' => ['plugin'],
            'guidelines' => ['patterns/plugin', 'modules/structure'],
            'description' => 'Plugin (interceptor) development',
            'group' => 'Module Development',
        ],
        'observer' => [
            'skills' => [],
            'guidelines' => ['patterns/observer', 'modules/structure'],
            'description' => 'Event observer development',
            'group' => 'Module Development',
        ],
        'preference' => [
            'skills' => [],
            'guidelines' => ['patterns/preference', 'modules/structure'],
            'description' => 'Class preference (rewrite) development',
            'group' => 'Module Development',
        ],
        'eav' => [
            'skills' => ['eav-development'],
            'guidelines' => ['database/eav', 'database/declarative-schema'],
            'description' => 'EAV attribute and entity development',
            'group' => 'Module Development',
        ],
        'data-patch' => [
            'skills' => [],
            'guidelines' => ['database/data-patches', 'modules/structure'],
            'description' => 'Data and schema patch development',
            'group' => 'Module Development',
        ],
        'rest-api' => [
            'skills' => ['rest-api-development'],
            'guidelines' => ['areas/webapi', 'patterns/service-contract'],
            'description' => 'REST API endpoint development',
            'group' => 'API & Integration',
        ],
        'graphql' => [
            'skills' => ['graphql-development'],
            'guidelines' => ['areas/graphql-schema', 'areas/graphql-resolvers'],
            'description' => 'GraphQL schema and resolver development',
            'group' => 'API & Integration',
        ],
        'payment' => [
            'skills' => ['payment-integration-core'],
            'guidelines' => ['modules/structure'],
            'description' => 'Payment method module setup and configuration',
            'group' => 'API & Integration',
        ],
        'payment-gateway' => [
            'skills' => ['payment-integration-gateway'],
            'guidelines' => [],
            'description' => 'Payment gateway components (builders, handlers, validators)',
            'group' => 'API & Integration',
        ],
        'payment-checkout' => [
            'skills' => ['payment-integration-checkout'],
            'guidelines' => [],
            'description' => 'Payment checkout integration and frontend',
            'group' => 'API & Integration',
        ],
        'shipping' => [
            'skills' => ['shipping-integration'],
            'guidelines' => [],
            'description' => 'Shipping carrier integration',
            'group' => 'API & Integration',
        ],
        'message-queue' => [
            'skills' => ['message-queue'],
            'guidelines' => [],
            'description' => 'Message queue and async processing',
            'group' => 'API & Integration',
        ],
        'import' => [
            'skills' => ['import-export-import'],
            'guidelines' => [],
            'description' => 'Custom import entity development',
            'group' => 'API & Integration',
        ],
        'export' => [
            'skills' => ['import-export-export'],
            'guidelines' => [],
            'description' => 'Custom export entity development',
            'group' => 'API & Integration',
        ],
        'frontend' => [
            'skills' => [],
            'guidelines' => ['areas/frontend', 'ecosystem/hyva-architecture'],
            'description' => 'Frontend development (layout, templates, JS)',
            'group' => 'Frontend & Admin',
        ],
        'theme' => [
            'skills' => ['theme-development-basics'],
            'guidelines' => ['areas/frontend'],
            'description' => 'Theme structure, layout XML, and templates',
            'group' => 'Frontend & Admin',
        ],
        'theme-styling' => [
            'skills' => ['theme-development-styling'],
            'guidelines' => [],
            'description' => 'Theme LESS/CSS styling and JavaScript',
            'group' => 'Frontend & Admin',
        ],
        'checkout' => [
            'skills' => ['checkout-customization-steps'],
            'guidelines' => ['areas/frontend'],
            'description' => 'Checkout custom steps and layout processors',
            'group' => 'Frontend & Admin',
        ],
        'checkout-advanced' => [
            'skills' => ['checkout-customization-advanced'],
            'guidelines' => [],
            'description' => 'Checkout config providers, mixins, and validation',
            'group' => 'Frontend & Admin',
        ],
        'adminhtml' => [
            'skills' => [],
            'guidelines' => ['areas/adminhtml-routing', 'areas/adminhtml-ui'],
            'description' => 'Admin panel development',
            'group' => 'Frontend & Admin',
        ],
        'ui-component' => [
            'skills' => ['ui-component-grids'],
            'guidelines' => ['areas/adminhtml-routing'],
            'description' => 'Admin UI component grids',
            'group' => 'Frontend & Admin',
        ],
        'ui-component-form' => [
            'skills' => ['ui-component-forms'],
            'guidelines' => ['areas/adminhtml-ui'],
            'description' => 'Admin UI component forms',
            'group' => 'Frontend & Admin',
        ],
        'cron' => [
            'skills' => ['cron-development'],
            'guidelines' => ['modules/structure'],
            'description' => 'Cron job development',
            'group' => 'System & Quality',
        ],
        'indexer' => [
            'skills' => ['indexer-development'],
            'guidelines' => ['database/indexers'],
            'description' => 'Custom indexer development',
            'group' => 'System & Quality',
        ],
        'testing' => [
            'skills' => ['testing'],
            'guidelines' => ['core/testing'],
            'description' => 'Unit, integration, and API testing',
            'group' => 'System & Quality',
        ],
        'coding-standards' => [
            'skills' => [],
            'guidelines' => ['core/coding-standards-syntax', 'core/coding-standards-quality'],
            'description' => 'PHP coding standards, syntax, formatting, and quality rules',
            'group' => 'System & Quality',
        ],
        'security' => [
            'skills' => [],
            'guidelines' => ['core/security'],
            'description' => 'Security best practices and guidelines',
            'group' => 'System & Quality',
        ],
        'performance' => [
            'skills' => [],
            'guidelines' => ['core/performance'],
            'description' => 'Performance optimization guidelines',
            'group' => 'System & Quality',
        ],
    ];

    /**
     * Returns development context (guidelines and skills) for a given task category.
     *
     * Use category "list" to see all available categories.
     *
     * @param string $category The development task category (e.g., "hyva-checkout", "plugin", "eav") or "list" to see available categories
     * @return array<string, mixed> Context with compiled skills and guidelines markdown
     */
    #[McpTool(
        name: 'development-context',
        description: 'Returns coding guidelines and development patterns for a given task category. Use category "list" to see available categories.'
    )]
    public function getDevelopmentContext(string $category): array
    {
        if ($category === 'list') {
            return $this->listCategories();
        }

        if (!isset(self::CATEGORY_MAP[$category])) {
            $available = implode(', ', array_keys(self::CATEGORY_MAP));
            return [
                'error' => true,
                'message' => "Unknown category: '$category'. Available categories: $available. Use category 'list' for descriptions.",
            ];
        }

        try {
            $mapping = self::CATEGORY_MAP[$category];
            $configDir = dirname(__DIR__, 3) . '/config';

            $loadedSkills = 0;
            $skillsContent = $this->loadFiles(
                $configDir . '/skills',
                $mapping['skills'],
                'SKILL.md',
                $loadedSkills
            );

            $loadedGuidelines = 0;
            $guidelinesContent = $this->loadFiles(
                $configDir . '/guidelines',
                $mapping['guidelines'],
                '.md',
                $loadedGuidelines
            );

            return [
                'category' => $category,
                'description' => $mapping['description'],
                'skills' => $skillsContent,
                'guidelines' => $guidelinesContent,
                'summary' => sprintf(
                    'Loaded %d skill(s) and %d guideline(s) for "%s" development.',
                    $loadedSkills,
                    $loadedGuidelines,
                    $category,
                ),
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * List all available categories with descriptions
     *
     * @return array<string, mixed>
     */
    private function listCategories(): array
    {
        $categories = [];
        foreach (self::CATEGORY_MAP as $name => $mapping) {
            $categories[] = [
                'name' => $name,
                'description' => $mapping['description'],
                'skills_count' => count($mapping['skills']),
                'guidelines_count' => count($mapping['guidelines']),
            ];
        }

        return [
            'category' => 'list',
            'total' => count($categories),
            'categories' => $categories,
        ];
    }

    /**
     * Load and compile markdown files from a base directory.
     *
     * For skills, suffix is 'SKILL.md' and names map to {baseDir}/{name}/SKILL.md.
     * For guidelines, suffix is '.md' and names map to {baseDir}/{name}.md.
     *
     * @param string $baseDir Base directory path
     * @param string[] $names File or directory names
     * @param string $suffix File suffix (e.g., 'SKILL.md' or '.md')
     * @param int &$loadedCount Reference counter for successfully loaded files
     * @return string Compiled markdown content
     */
    private function loadFiles(string $baseDir, array $names, string $suffix, int &$loadedCount): string
    {
        if (empty($names)) {
            return '';
        }

        $isSkill = $suffix === 'SKILL.md';
        $sections = [];

        foreach ($names as $name) {
            $filePath = $isSkill
                ? $baseDir . '/' . $name . '/SKILL.md'
                : $baseDir . '/' . $name . '.md';

            $content = file_exists($filePath) ? file_get_contents($filePath) : false;
            if ($content !== false) {
                $relativePath = $isSkill ? "skills/$name/SKILL.md" : "guidelines/$name.md";
                $sections[] = "<!-- source: $relativePath -->\n\n$content";
                $loadedCount++;
            }
        }

        return implode("\n\n---\n\n", $sections);
    }
}
