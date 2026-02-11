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
     * Each category maps to an array with 'skills' and 'guidelines' keys.
     * Skills are directory names under config/skills/{name}/SKILL.md.
     * Guidelines are paths under config/guidelines/{path}.md.
     *
     * @var array<string, array{skills: string[], guidelines: string[], description: string}>
     */
    private const CATEGORY_MAP = [
        'coding-standards' => [
            'skills' => [],
            'guidelines' => ['core/coding-standards-syntax', 'core/coding-standards-quality'],
            'description' => 'PHP coding standards, syntax, formatting, and quality rules',
        ],
        'hyva-checkout' => [
            'skills' => ['hyva-checkout-magewire'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Hyvä Checkout Magewire component development',
        ],
        'hyva-checkout-config' => [
            'skills' => ['hyva-checkout-configuration'],
            'guidelines' => ['modules/structure'],
            'description' => 'Hyvä Checkout XML configuration and layout',
        ],
        'hyva-checkout-api' => [
            'skills' => ['hyva-checkout-apis'],
            'guidelines' => [],
            'description' => 'Hyvä Checkout evaluation, form, and frontend APIs',
        ],
        'hyva-theme' => [
            'skills' => ['hyva-theme-setup'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Hyvä theme setup and Alpine.js CSP components',
        ],
        'hyva-theme-advanced' => [
            'skills' => ['hyva-theme-components'],
            'guidelines' => ['ecosystem/hyva-patterns'],
            'description' => 'Hyvä ViewModels, module compatibility, and customization',
        ],
        'hyva-ui-component' => [
            'skills' => ['hyva-ui-component-css'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Hyvä UI component CSS and design system',
        ],
        'hyva-ui-component-js' => [
            'skills' => ['hyva-ui-component-alpine'],
            'guidelines' => [],
            'description' => 'Hyvä UI component Alpine.js and interactivity',
        ],
        'payment' => [
            'skills' => ['payment-integration-core'],
            'guidelines' => ['modules/structure'],
            'description' => 'Payment method module setup and configuration',
        ],
        'payment-gateway' => [
            'skills' => ['payment-integration-gateway'],
            'guidelines' => [],
            'description' => 'Payment gateway components (builders, handlers, validators)',
        ],
        'payment-checkout' => [
            'skills' => ['payment-integration-checkout'],
            'guidelines' => [],
            'description' => 'Payment checkout integration and frontend',
        ],
        'checkout' => [
            'skills' => ['checkout-customization-steps'],
            'guidelines' => ['areas/frontend'],
            'description' => 'Checkout custom steps and layout processors',
        ],
        'checkout-advanced' => [
            'skills' => ['checkout-customization-advanced'],
            'guidelines' => [],
            'description' => 'Checkout config providers, mixins, and validation',
        ],
        'plugin' => [
            'skills' => ['plugin'],
            'guidelines' => ['patterns/plugin', 'modules/structure'],
            'description' => 'Plugin (interceptor) development',
        ],
        'observer' => [
            'skills' => [],
            'guidelines' => ['patterns/observer', 'modules/structure'],
            'description' => 'Event observer development',
        ],
        'preference' => [
            'skills' => [],
            'guidelines' => ['patterns/preference', 'modules/structure'],
            'description' => 'Class preference (rewrite) development',
        ],
        'eav' => [
            'skills' => ['eav-development'],
            'guidelines' => ['database/eav', 'database/declarative-schema'],
            'description' => 'EAV attribute and entity development',
        ],
        'rest-api' => [
            'skills' => ['rest-api-development'],
            'guidelines' => ['areas/webapi', 'patterns/service-contract'],
            'description' => 'REST API endpoint development',
        ],
        'graphql' => [
            'skills' => ['graphql-development'],
            'guidelines' => ['areas/graphql-schema', 'areas/graphql-resolvers'],
            'description' => 'GraphQL schema and resolver development',
        ],
        'cron' => [
            'skills' => ['cron-development'],
            'guidelines' => ['modules/structure'],
            'description' => 'Cron job development',
        ],
        'indexer' => [
            'skills' => ['indexer-development'],
            'guidelines' => ['database/indexers'],
            'description' => 'Custom indexer development',
        ],
        'theme' => [
            'skills' => ['theme-development-basics'],
            'guidelines' => ['areas/frontend'],
            'description' => 'Theme structure, layout XML, and templates',
        ],
        'theme-styling' => [
            'skills' => ['theme-development-styling'],
            'guidelines' => [],
            'description' => 'Theme LESS/CSS styling and JavaScript',
        ],
        'shipping' => [
            'skills' => ['shipping-integration'],
            'guidelines' => [],
            'description' => 'Shipping carrier integration',
        ],
        'ui-component' => [
            'skills' => ['ui-component-grids'],
            'guidelines' => ['areas/adminhtml-routing'],
            'description' => 'Admin UI component grids',
        ],
        'ui-component-form' => [
            'skills' => ['ui-component-forms'],
            'guidelines' => ['areas/adminhtml-ui'],
            'description' => 'Admin UI component forms',
        ],
        'message-queue' => [
            'skills' => ['message-queue'],
            'guidelines' => [],
            'description' => 'Message queue and async processing',
        ],
        'import' => [
            'skills' => ['import-export-import'],
            'guidelines' => [],
            'description' => 'Custom import entity development',
        ],
        'export' => [
            'skills' => ['import-export-export'],
            'guidelines' => [],
            'description' => 'Custom export entity development',
        ],
        'testing' => [
            'skills' => ['testing'],
            'guidelines' => ['core/testing'],
            'description' => 'Unit, integration, and API testing',
        ],
        'model' => [
            'skills' => [],
            'guidelines' => ['patterns/repository', 'patterns/service-contract', 'database/declarative-schema'],
            'description' => 'Model, repository, and data layer development',
        ],
        'data-patch' => [
            'skills' => [],
            'guidelines' => ['database/data-patches', 'modules/structure'],
            'description' => 'Data and schema patch development',
        ],
        'module' => [
            'skills' => [],
            'guidelines' => ['modules/structure', 'modules/registration', 'modules/dependencies', 'modules/versioning'],
            'description' => 'Module scaffolding and structure',
        ],
        'frontend' => [
            'skills' => [],
            'guidelines' => ['areas/frontend', 'ecosystem/hyva-architecture'],
            'description' => 'Frontend development (layout, templates, JS)',
        ],
        'adminhtml' => [
            'skills' => [],
            'guidelines' => ['areas/adminhtml-routing', 'areas/adminhtml-ui'],
            'description' => 'Admin panel development',
        ],
        'security' => [
            'skills' => [],
            'guidelines' => ['core/security'],
            'description' => 'Security best practices and guidelines',
        ],
        'performance' => [
            'skills' => [],
            'guidelines' => ['core/performance'],
            'description' => 'Performance optimization guidelines',
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

            $skillsContent = $this->loadSkills($configDir, $mapping['skills']);
            $guidelinesContent = $this->loadGuidelines($configDir, $mapping['guidelines']);

            $loadedSkills = count(array_filter($mapping['skills'], fn(string $name) =>
                file_exists($configDir . '/skills/' . $name . '/SKILL.md')
            ));
            $loadedGuidelines = count(array_filter($mapping['guidelines'], fn(string $name) =>
                file_exists($configDir . '/guidelines/' . $name . '.md')
            ));

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
     * Load and compile skill files into markdown
     *
     * @param string $configDir Base config directory path
     * @param string[] $skillNames Skill directory names
     * @return string Compiled markdown content
     */
    private function loadSkills(string $configDir, array $skillNames): string
    {
        if (empty($skillNames)) {
            return '';
        }

        $sections = [];
        foreach ($skillNames as $skillName) {
            $filePath = $configDir . '/skills/' . $skillName . '/SKILL.md';
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                if ($content !== false) {
                    $sections[] = "<!-- source: skills/$skillName/SKILL.md -->\n\n$content";
                }
            }
        }

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Load and compile guideline files into markdown
     *
     * @param string $configDir Base config directory path
     * @param string[] $guidelineNames Guideline paths (relative, without .md extension)
     * @return string Compiled markdown content
     */
    private function loadGuidelines(string $configDir, array $guidelineNames): string
    {
        if (empty($guidelineNames)) {
            return '';
        }

        $sections = [];
        foreach ($guidelineNames as $guidelineName) {
            $filePath = $configDir . '/guidelines/' . $guidelineName . '.md';
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                if ($content !== false) {
                    $sections[] = "<!-- source: guidelines/$guidelineName.md -->\n\n$content";
                }
            }
        }

        return implode("\n\n---\n\n", $sections);
    }
}
