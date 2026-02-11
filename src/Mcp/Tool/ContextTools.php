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
        'hyva-checkout' => [
            'skills' => ['hyva-checkout-development', 'checkout-customization'],
            'guidelines' => ['ecosystem/hyva', 'core/coding-standards', 'modules/structure'],
            'description' => 'Hyvä Checkout & Magewire development',
        ],
        'hyva-theme' => [
            'skills' => ['hyva-theme-development'],
            'guidelines' => ['ecosystem/hyva', 'areas/frontend', 'core/coding-standards'],
            'description' => 'Hyvä theme development (Alpine.js, Tailwind CSS)',
        ],
        'hyva-ui-component' => [
            'skills' => ['hyva-ui-component-development'],
            'guidelines' => ['ecosystem/hyva', 'areas/frontend', 'core/coding-standards'],
            'description' => 'Hyvä UI component development',
        ],
        'plugin' => [
            'skills' => ['plugin'],
            'guidelines' => ['patterns/plugin', 'core/coding-standards', 'modules/structure'],
            'description' => 'Plugin (interceptor) development',
        ],
        'observer' => [
            'skills' => [],
            'guidelines' => ['patterns/observer', 'core/coding-standards', 'modules/structure'],
            'description' => 'Event observer development',
        ],
        'preference' => [
            'skills' => [],
            'guidelines' => ['patterns/preference', 'core/coding-standards', 'modules/structure'],
            'description' => 'Class preference (rewrite) development',
        ],
        'eav' => [
            'skills' => ['eav-development'],
            'guidelines' => ['database/eav', 'database/declarative-schema', 'core/coding-standards'],
            'description' => 'EAV attribute and entity development',
        ],
        'rest-api' => [
            'skills' => ['rest-api-development'],
            'guidelines' => ['areas/webapi', 'patterns/service-contract', 'core/coding-standards'],
            'description' => 'REST API endpoint development',
        ],
        'graphql' => [
            'skills' => ['graphql-development'],
            'guidelines' => ['areas/graphql', 'core/coding-standards'],
            'description' => 'GraphQL schema and resolver development',
        ],
        'cron' => [
            'skills' => ['cron-development'],
            'guidelines' => ['core/coding-standards', 'modules/structure'],
            'description' => 'Cron job development',
        ],
        'indexer' => [
            'skills' => ['indexer-development'],
            'guidelines' => ['database/indexers', 'core/coding-standards'],
            'description' => 'Custom indexer development',
        ],
        'theme' => [
            'skills' => ['theme-development'],
            'guidelines' => ['areas/frontend', 'core/coding-standards'],
            'description' => 'Theme development and customization',
        ],
        'checkout' => [
            'skills' => ['checkout-customization'],
            'guidelines' => ['areas/frontend', 'core/coding-standards'],
            'description' => 'Checkout flow customization',
        ],
        'payment' => [
            'skills' => ['payment-integration', 'hyva-checkout-development'],
            'guidelines' => ['core/coding-standards'],
            'description' => 'Payment method integration',
        ],
        'shipping' => [
            'skills' => ['shipping-integration'],
            'guidelines' => ['core/coding-standards'],
            'description' => 'Shipping carrier integration',
        ],
        'ui-component' => [
            'skills' => ['ui-component-development'],
            'guidelines' => ['areas/adminhtml', 'core/coding-standards'],
            'description' => 'Admin UI component development',
        ],
        'message-queue' => [
            'skills' => ['message-queue'],
            'guidelines' => ['core/coding-standards'],
            'description' => 'Message queue and async processing',
        ],
        'import-export' => [
            'skills' => ['import-export'],
            'guidelines' => ['core/coding-standards'],
            'description' => 'Import/export customization',
        ],
        'testing' => [
            'skills' => ['testing'],
            'guidelines' => ['core/testing', 'core/coding-standards'],
            'description' => 'Unit, integration, and API testing',
        ],
        'model' => [
            'skills' => [],
            'guidelines' => ['patterns/repository', 'patterns/service-contract', 'database/declarative-schema', 'database/data-patches', 'core/coding-standards'],
            'description' => 'Model, repository, and data layer development',
        ],
        'module' => [
            'skills' => [],
            'guidelines' => ['modules/structure', 'modules/registration', 'modules/dependencies', 'modules/versioning', 'core/coding-standards'],
            'description' => 'Module scaffolding and structure',
        ],
        'frontend' => [
            'skills' => [],
            'guidelines' => ['areas/frontend', 'ecosystem/hyva', 'core/coding-standards'],
            'description' => 'Frontend development (layout, templates, JS)',
        ],
        'adminhtml' => [
            'skills' => [],
            'guidelines' => ['areas/adminhtml', 'core/coding-standards', 'modules/structure'],
            'description' => 'Admin panel development',
        ],
        'security' => [
            'skills' => [],
            'guidelines' => ['core/security', 'core/coding-standards'],
            'description' => 'Security best practices and guidelines',
        ],
        'performance' => [
            'skills' => [],
            'guidelines' => ['core/performance', 'core/coding-standards'],
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
