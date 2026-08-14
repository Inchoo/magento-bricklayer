<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Guidelines\LocalOverrideHelper;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ResolvesPackagePaths;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Mcp\Capability\Attribute\McpTool;

/**
 * Context Tools
 *
 * Provides development context (guidelines and skills) as a single tool call,
 * enabling agents to load relevant coding patterns before writing code.
 */
class ContextTools
{
    use ResolvesPackagePaths;
    use RespondsWithErrors;

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
            'skills' => ['hyva-checkout'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Hyvä Checkout architecture and integration development',
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
        'magewire' => [
            'skills' => ['magewire'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Magewire V1 reactive component development',
            'group' => 'Hyvä Theme',
        ],
        'magewire-three' => [
            'skills' => ['magewire-three'],
            'guidelines' => ['ecosystem/hyva-architecture'],
            'description' => 'Magewire 3 component development and V1 migration',
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

    public function __construct(?string $magentoRoot = null, ?string $packageRoot = null)
    {
        $this->initPackagePaths($magentoRoot, $packageRoot);
    }

    /**
     * Returns development context (guidelines and skills) for a given task category.
     *
     * Use category "list" to see all available categories.
     *
     * @param string $category The development task category (e.g., "hyva-checkout", "plugin", "eav")
     *     or "list" to see available categories
     * @return array<string, mixed> Context with compiled skills and guidelines markdown
     */
    #[McpTool(
        name: 'development-context',
        description: 'Load coding guidelines and development patterns BEFORE writing code. '
            . 'Use category "list" to see available categories. '
            . 'Always load "coding-standards" for any PHP file.'
    )]
    public function getDevelopmentContext(string $category): array
    {
        if ($category === 'list') {
            return $this->listCategories();
        }

        $isKnownCategory = isset(self::CATEGORY_MAP[$category]);
        $localSkillPath = $this->localSkillPath($category);

        if (!$isKnownCategory && $localSkillPath === null) {
            $available = implode(', ', array_keys(self::CATEGORY_MAP));
            $localExtras = $this->listLocalOnlyCategories();
            if ($localExtras !== []) {
                $available .= ', ' . implode(', ', $localExtras);
            }
            return [
                'error' => true,
                'message' => "Unknown category: '$category'. Available categories: $available. "
                    . "Use category 'list' for descriptions.",
            ];
        }

        try {
            if ($isKnownCategory) {
                $mapping = self::CATEGORY_MAP[$category];
                $description = $mapping['description'];
                $skillNames = $mapping['skills'];
                $guidelineNames = $mapping['guidelines'];
            } else {
                // At this point $localSkillPath is non-null (guarded above).
                $meta = LocalOverrideHelper::parseSkillFrontmatter($localSkillPath);
                $description = $meta['description']
                    ?? 'Project-specific skill: ' . LocalOverrideHelper::defaultDisplayName($category);
                $skillNames = [$category];
                $guidelineNames = [];
            }

            $loadedSkills = 0;
            $skillsContent = $this->loadSkillSections($skillNames, $loadedSkills);

            $loadedGuidelines = 0;
            $guidelinesContent = $this->loadGuidelineSections($guidelineNames, $loadedGuidelines);

            $nextSteps = $this->getNextSteps($category);

            return [
                'category' => $category,
                'description' => $description,
                'skills' => $skillsContent,
                'guidelines' => $guidelinesContent,
                '_next_steps' => !empty($nextSteps) ? $nextSteps : null,
                'summary' => sprintf(
                    'Loaded %d skill(s) and %d guideline(s) for "%s" development.',
                    $loadedSkills,
                    $loadedGuidelines,
                    $category,
                ),
            ];
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Resolve the skill file path for a category, preferring a local override
     * at `.bricklayer/skills/{category}/SKILL.md` and falling back to the
     * bundled file at `config/skills/{category}/SKILL.md`. Returns null when
     * neither file exists.
     */
    public function resolveSkillPath(string $category): ?string
    {
        $localPath = $this->localSkillPath($category);
        if ($localPath !== null) {
            return $localPath;
        }

        $bundledPath = $this->packageRoot . '/config/skills/' . $category . '/SKILL.md';
        if (file_exists($bundledPath)) {
            return $bundledPath;
        }

        return null;
    }

    /**
     * Resolve a guideline file path, preferring a local override at
     * `.bricklayer/guidelines/{relativePath}` and falling back to the bundled
     * file at `config/guidelines/{relativePath}`. Returns null when neither
     * file exists. `$relativePath` should include the `.md` extension.
     */
    public function resolveGuidelinePath(string $relativePath): ?string
    {
        $magentoRoot = $this->resolveMagentoRoot();
        if ($magentoRoot !== null) {
            $localPath = $magentoRoot . '/.bricklayer/guidelines/' . $relativePath;
            if (file_exists($localPath)) {
                return $localPath;
            }
        }

        $bundledPath = $this->packageRoot . '/config/guidelines/' . $relativePath;
        if (file_exists($bundledPath)) {
            return $bundledPath;
        }

        return null;
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
            $localOverride = $this->localSkillPath($name) !== null;
            $categories[] = [
                'name' => $name,
                'description' => $mapping['description'],
                'skills_count' => count($mapping['skills']),
                'guidelines_count' => count($mapping['guidelines']),
                'local_override' => $localOverride,
            ];
        }

        foreach ($this->listLocalOnlyCategories() as $category) {
            $skillPath = $this->localSkillPath($category);
            $meta = $skillPath !== null
                ? LocalOverrideHelper::parseSkillFrontmatter($skillPath)
                : ['name' => null, 'description' => null];
            $categories[] = [
                'name' => $category,
                'description' => $meta['description']
                    ?? ('Project-specific skill: ' . LocalOverrideHelper::defaultDisplayName($category)),
                'skills_count' => 1,
                'guidelines_count' => 0,
                'local_only' => true,
            ];
        }

        return [
            'category' => 'list',
            'total' => count($categories),
            'categories' => $categories,
        ];
    }

    /**
     * Returns contextual introspection tool recommendations for a given category.
     *
     * @return string[]
     */
    private function getNextSteps(string $category): array
    {
        return match ($category) {
            'plugin' => [
                'Before writing your plugin: check-class className=TargetClass',
                'Check existing plugins and their sortOrder to avoid conflicts',
            ],
            'observer' => [
                'Check existing observers: event-list eventName=your_event_name',
            ],
            'preference' => [
                'Before overriding: check-class className=TargetClass',
                'Verify no other module already rewrites this class',
            ],
            'eav' => [
                'Check existing attributes: eav-attributes entityType=catalog_product',
                'Check table structure: database-schema table=catalog_product_entity',
            ],
            'model' => [
                'Check table structure: database-schema table=your_table_name',
                'Check existing preferences: preference-list interface=YourInterface',
            ],
            'data-patch' => [
                'Check current schema: database-schema table=target_table',
            ],
            'rest-api' => [
                'Check existing endpoints: api-endpoints',
            ],
            'graphql' => [
                'Check existing schema: graphql-inspect target=types',
            ],
            'cron' => [
                'Check existing jobs: system-status check=cron',
            ],
            'indexer' => [
                'Check indexer state: system-status check=indexers',
            ],
            'module' => [
                'Check installed modules: module-list verbosity=minimal',
            ],
            'frontend', 'adminhtml', 'checkout', 'checkout-advanced' => [
                'Check routes: route-list',
            ],
            'hyva-checkout-config', 'hyva-checkout-api' => [
                'Check installed modules: module-list (verify Hyva_Checkout and Magewirephp_Magewire are present)',
            ],
            'hyva-checkout' => [
                'Before generating code, resolve the Hyva Checkout version from Composer metadata',
                'Use Magewire V1 for checkout 1.0-1.3.*; use native Magewire 3 for new components on 1.4+',
                'For an existing V1 component on 1.4+, load the backwards-compatibility migration resource',
            ],
            'magewire' => [
                'Check modules: module-list (verify Magewirephp_Magewire; confirm V1 in composer.lock)',
            ],
            'magewire-three' => [
                'Check modules: module-list (verify Magewirephp_Magewire and the active V3 adapter)',
                'Load focused resources from magento://skills/magewire-three/{topic}',
                'Topics: architecture, javascript, theming, portman, backwards-compatibility, best-practices',
            ],
            'hyva-theme', 'hyva-theme-advanced', 'hyva-ui-component', 'hyva-ui-component-js' => [
                'Check installed modules: module-list (verify Hyva_Theme is present)',
            ],
            default => [],
        };
    }

    /**
     * Load skill markdown sections, applying local-first path resolution and
     * stripping any YAML frontmatter from the returned content.
     *
     * @param string[] $skillNames
     */
    private function loadSkillSections(array $skillNames, int &$loadedCount): string
    {
        if (empty($skillNames)) {
            return '';
        }

        $sections = [];
        foreach ($skillNames as $name) {
            $skillPath = $this->resolveSkillPath($name);
            if ($skillPath === null) {
                continue;
            }
            $content = file_get_contents($skillPath);
            if ($content === false) {
                continue;
            }
            $content = LocalOverrideHelper::stripFrontmatter($content);

            $isLocal = $this->isLocalPath($skillPath);
            $label = ($isLocal ? '[Project] ' : '') . "skills/$name/SKILL.md";
            $sections[] = "<!-- source: $label -->\n\n" . $content;
            $loadedCount++;
        }

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Load guideline markdown sections, applying local-first path resolution.
     *
     * @param string[] $guidelineNames Category-style paths without extension (e.g. "patterns/plugin")
     */
    private function loadGuidelineSections(array $guidelineNames, int &$loadedCount): string
    {
        if (empty($guidelineNames)) {
            return '';
        }

        $sections = [];
        foreach ($guidelineNames as $name) {
            $guidelinePath = $this->resolveGuidelinePath($name . '.md');
            if ($guidelinePath === null) {
                continue;
            }
            $content = file_get_contents($guidelinePath);
            if ($content === false) {
                continue;
            }

            $isLocal = $this->isLocalPath($guidelinePath);
            $label = ($isLocal ? '[Project] ' : '') . "guidelines/$name.md";
            $sections[] = "<!-- source: $label -->\n\n" . $content;
            $loadedCount++;
        }

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Return the local skill SKILL.md path for a category if it exists, or null.
     */
    private function localSkillPath(string $category): ?string
    {
        $magentoRoot = $this->resolveMagentoRoot();
        if ($magentoRoot === null) {
            return null;
        }
        $path = $magentoRoot . '/.bricklayer/skills/' . $category . '/SKILL.md';
        return file_exists($path) ? $path : null;
    }

    /**
     * Discover local-only skill category names (those without a bundled
     * equivalent in CATEGORY_MAP). Used to surface new project skills in
     * the `list` response and error messages.
     *
     * @return list<string>
     */
    private function listLocalOnlyCategories(): array
    {
        $magentoRoot = $this->resolveMagentoRoot();
        if ($magentoRoot === null) {
            return [];
        }
        $dir = $magentoRoot . '/.bricklayer/skills/';
        if (!is_dir($dir)) {
            return [];
        }

        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }
        sort($entries);

        $result = [];
        foreach ($entries as $entryName) {
            if ($entryName === '.' || $entryName === '..') {
                continue;
            }
            if (isset(self::CATEGORY_MAP[$entryName])) {
                continue;
            }
            $skillFile = $dir . $entryName . '/SKILL.md';
            if (is_dir($dir . $entryName) && file_exists($skillFile)) {
                $result[] = $entryName;
            }
        }
        return $result;
    }

    /**
     * True if the given absolute path lives under the project's `.bricklayer/`
     * directory (i.e. it is a local override or addition).
     */
    private function isLocalPath(string $absolutePath): bool
    {
        $magentoRoot = $this->resolveMagentoRoot();
        if ($magentoRoot === null) {
            return false;
        }
        $prefix = $magentoRoot . '/.bricklayer/';
        return str_starts_with($absolutePath, $prefix);
    }
}
