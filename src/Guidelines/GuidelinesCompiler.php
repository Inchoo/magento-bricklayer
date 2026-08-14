<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Guidelines;

use Inchoo\MagentoBricklayer\Mcp\Tool\ContextTools;
use Inchoo\MagentoBricklayer\Support\CollectsMarkdownFiles;

class GuidelinesCompiler
{
    use CollectsMarkdownFiles;

    private const AGENT_FILE_MAP = [
        'claude-code' => 'CLAUDE.md',
        'cursor' => '.cursorrules',
        'copilot' => '.github/copilot-instructions.md',
        'phpstorm' => '.junie/guidelines.md',
        'gemini' => 'AGENTS.md',
        'codex' => 'AGENTS.md',
        'mistral-vibe' => 'AGENTS.md',
    ];

    private readonly string $packageRoot;
    private readonly ?string $magentoRoot;

    /** @var list<string> */
    private array $appliedLocalOverrides = [];

    /** @var list<string> */
    private array $appliedLocalAdditions = [];

    public function __construct(
        ?string $magentoRoot = null,
        ?string $packageRoot = null,
        private readonly ToolScanner $toolScanner = new ToolScanner(),
    ) {
        $this->magentoRoot = $magentoRoot !== null ? rtrim($magentoRoot, '/\\') : null;
        $this->packageRoot = $packageRoot !== null
            ? rtrim($packageRoot, '/\\')
            : dirname(__DIR__, 2);
    }

    public function compile(string $agent, string $envType = 'native'): string
    {
        $this->appliedLocalOverrides = [];
        $this->appliedLocalAdditions = [];

        $sections = [];
        $sections[] = $this->buildHeader();
        $sections[] = $this->buildIntrospectionSection();
        $sections[] = $this->buildContextCategoriesSection();
        $sections[] = $this->buildEfficiencySection();
        $sections[] = $this->getShellCommandsSection($envType);

        $localGuidelineSection = $this->compileLocalGuidelineAdditions();
        if ($localGuidelineSection !== '') {
            $sections[] = $localGuidelineSection;
        }

        $projectContext = $this->getProjectContext();
        if ($projectContext !== null) {
            $sections[] = "## Project-Specific Context\n\n" . $projectContext;
        }

        $sections[] = $this->buildFooter($agent);

        return implode("\n\n", array_filter($sections));
    }

    public function getFilename(string $agent): string
    {
        return self::AGENT_FILE_MAP[$agent] ?? 'AGENTS.md';
    }

    /**
     * List of local files (relative to magentoRoot) that were applied as
     * overrides or additions during the most recent compile() call.
     *
     * @return list<string>
     */
    public function getAppliedOverrides(): array
    {
        return array_values(array_unique(
            array_merge($this->appliedLocalOverrides, $this->appliedLocalAdditions)
        ));
    }

    private function buildHeader(): string
    {
        $toolData = $this->toolScanner->scan();
        $count = $toolData['totalCount'];

        return <<<MARKDOWN
# Magento 2 Development Guidelines

You are assisting with Magento 2 development. This project uses Magento Bricklayer
for AI-assisted development tooling.

## MCP Server: magento-bricklayer

You have access to an MCP server with {$count} tools for Magento development.
Use `search-tools` to discover relevant tools and `code-runner` for multi-step operations.
Always prefer using these tools over assumptions about the codebase.
MARKDOWN;
    }

    private function buildIntrospectionSection(): string
    {
        $base = <<<'MARKDOWN'
### Before Modifying Magento Code

Magento resolves DI, plugins, preferences, and events at runtime across many modules.
Reading source files alone misses overrides from other modules. **Always check runtime
state before writing code that touches existing classes.**

| Task | Check runtime state first | Then load guidelines |
|------|---------------------------|----------------------|
| Writing or modifying a plugin | `check-class className=Target\Class` | `development-context category=plugin` |
| Overriding or extending a class | `check-class className=Target\Class` | (based on what you find) |
| Injecting or changing DI config | `di-configuration className=Target\Class` | (based on what you find) |
| Working with product data | `eav-attributes entityType=catalog_product` | `development-context category=eav` |
| Working with customer data | `eav-attributes entityType=customer` | `development-context category=eav` |
| Creating/modifying a DB table | `database-schema table=table_name` | `development-context category=model` |
| Subscribing to an event | `event-list eventName=event_name` | `development-context category=observer` |
| Adding a REST API endpoint | `api-endpoints` | `development-context category=rest-api` |
| Writing a GraphQL resolver | `graphql-inspect target=types` | `development-context category=graphql` |
| Customizing a layout / moving a block | `layout-inspect handle=<handle>` | `development-context category=frontend` |
| Modifying an admin grid or form | `ui-component-inspect name=<name>` | `development-context category=ui-component` |
| Adding or debugging a queue consumer | `message-queue-inspect` | `development-context category=message-queue` |
| Creating a cron job | `system-status check=cron` | `development-context category=cron` |
| Debugging an error | `diagnose-error` | (based on diagnosis) |
| Investigating performance | `diagnose-performance` | `development-context category=performance` |
| Writing **any** PHP file | — | `development-context category=coding-standards` (always) |
MARKDOWN;

        $extraRows = $this->getExtraDecisionMatrixRows();
        if ($extraRows !== '') {
            $base .= "\n" . rtrim($extraRows, "\n");
        }

        return $base;
    }

    private function buildContextCategoriesSection(): string
    {
        $lines = [];
        $lines[] = '### Development Context Tool';
        $lines[] = '';
        $lines[] = 'Before writing or generating code, call the `development-context` tool with the relevant';
        $lines[] = 'task category to load coding guidelines and development patterns.';
        $lines[] = '';
        $lines[] = '| Category | Description |';
        $lines[] = '|----------|-------------|';

        // Group categories by their 'group' key, preserving insertion order
        $grouped = [];
        foreach (ContextTools::CATEGORY_MAP as $name => $mapping) {
            $group = $mapping['group'];
            $grouped[$group][$name] = $mapping['description'];
        }

        foreach ($grouped as $groupTitle => $categories) {
            $lines[] = "| **{$groupTitle}** | |";
            foreach ($categories as $name => $description) {
                $lines[] = "| `{$name}` | {$description} |";
            }
        }

        $localRows = $this->buildLocalSkillsTableRows();
        if ($localRows !== '') {
            $lines[] = '| **Project-specific** | |';
            foreach (explode("\n", rtrim($localRows, "\n")) as $row) {
                if ($row !== '') {
                    $lines[] = $row;
                }
            }
        }

        $lines[] = '| `list` | See all categories with skill/guideline counts |';
        $lines[] = '';
        $lines[] = '### Reminder';
        $lines[] = '';
        $lines[] = 'Always call `development-context category=coding-standards` before writing any PHP file.';
        $lines[] = 'See the "Before Modifying Magento Code" table above for task-specific guidelines.';

        return implode("\n", $lines);
    }

    private function buildEfficiencySection(): string
    {
        return <<<'MARKDOWN'
### Token Efficiency

Follow these patterns to minimize context usage when working with Bricklayer tools:

**Prefer `code-runner` for multi-step operations:**
Instead of chaining multiple individual tool calls, write PHP code in `code-runner` to execute
them in a single call. Example: to get data for 5 products, use one `code-runner` call with a
`foreach` loop over `repo()`, not 5 separate `product-get` calls. Call `code-runner-help` for
available helpers and example patterns.

**Discover tools before using them:**
Call `search-tools` with a keyword to find relevant tools. Use `detail=names` first for a
lightweight overview, then `detail=full` only for the tools you need.

**Minimize list tool payloads:**
- `count_only=true` — check result size before fetching full data
- `fields=sku,name,price` — request only the columns you need
- `verbosity=minimal` — get just identifiers from `module-list` and `eav-attributes`

**Use `search-docs` before `development-context`:**
`search-docs` returns a lightweight pointer to the right category. Only call
`development-context` once you know which category you need.

**Use `batch-execute` for repetitive operations:**
When performing the same tool call with different parameters (e.g., updating stock for
10 products), use `batch-execute` with a JSON array instead of 10 separate calls.

**Truncate log output:**
Use `max_entry_length` on `log` (action=read or action=search) to limit long entries.
Start with `max_entry_length=500` and increase only if you need full stack traces.
MARKDOWN;
    }

    private function buildFooter(string $agent): string
    {
        $timestamp = date('Y-m-d H:i:s T');

        // Agents can share one guidelines file (gemini and codex both use
        // AGENTS.md), so label the footer with every agent the file serves.
        $label = array_key_exists($agent, self::AGENT_FILE_MAP)
            ? implode(', ', array_keys(self::AGENT_FILE_MAP, $this->getFilename($agent), true))
            : $agent;

        return <<<MARKDOWN
---

*Generated by magento-bricklayer*
*Timestamp: {$timestamp}*
*Agent: {$label}*
MARKDOWN;
    }

    private function getShellCommandsSection(string $envType): string
    {
        if ($envType === 'native') {
            return <<<'SECTION'
## Shell Commands

When running Magento CLI commands:

```bash
bin/magento <command>
```

Common commands after code changes:
- `bin/magento setup:upgrade` - Run database migrations after adding/updating modules
- `bin/magento setup:di:compile` - Compile dependency injection (required after DI changes)
- `bin/magento cache:clean` - Clear cache
- `bin/magento cache:flush` - Flush cache storage
- `bin/magento indexer:reindex` - Rebuild indexes
SECTION;
        }

        $commandTemplate = match ($envType) {
            'ddev' => 'ddev exec %s',
            'hooli' => '../hooli console --run "%s"',
            'warden' => 'warden shell -c %s',
            'docker-compose' => 'docker compose exec php %s',
            'docker' => 'docker exec -it <container> %s',
            default => '%s',
        };

        $envLabel = match ($envType) {
            'ddev' => 'DDEV',
            'hooli' => 'Hooli',
            'warden' => 'Warden',
            'docker-compose' => 'Docker Compose',
            'docker' => 'Docker',
            default => ucfirst($envType),
        };

        $fmt = fn(string $command): string => sprintf($commandTemplate, $command);

        $example = $fmt('bin/magento <command>');
        $upgrade = $fmt('bin/magento setup:upgrade');
        $compile = $fmt('bin/magento setup:di:compile');
        $clean = $fmt('bin/magento cache:clean');
        $flush = $fmt('bin/magento cache:flush');
        $reindex = $fmt('bin/magento indexer:reindex');
        $composerInstall = $fmt('composer install');
        $composerRequire = $fmt('composer require <package>');

        return <<<SECTION
## Shell Commands

This project runs in a **{$envLabel}** environment. When running Magento CLI commands:

```bash
{$example}
```

Common commands after code changes:
- `{$upgrade}` - Run database migrations after adding/updating modules
- `{$compile}` - Compile dependency injection (required after DI changes)
- `{$clean}` - Clear cache
- `{$flush}` - Flush cache storage
- `{$reindex}` - Rebuild indexes
- `{$composerInstall}` - Install dependencies
- `{$composerRequire}` - Add new dependencies
SECTION;
    }

    /**
     * Read `.bricklayer/project-context.md` and return its trimmed content,
     * or null when the file is missing or empty.
     */
    private function getProjectContext(): ?string
    {
        if ($this->magentoRoot === null) {
            return null;
        }

        $path = $this->magentoRoot . '/.bricklayer/project-context.md';
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $content = trim($content);
        if ($content === '') {
            return null;
        }

        $this->appliedLocalAdditions[] = '.bricklayer/project-context.md';
        return $content;
    }

    /**
     * Read `.bricklayer/decision-matrix.md` and return additional Markdown
     * table rows. Only lines that look like table rows are accepted.
     */
    private function getExtraDecisionMatrixRows(): string
    {
        if ($this->magentoRoot === null) {
            return '';
        }

        $path = $this->magentoRoot . '/.bricklayer/decision-matrix.md';
        if (!file_exists($path)) {
            return '';
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return '';
        }

        $rows = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '|') && str_ends_with($line, '|')) {
                // Skip the header separator row if it was accidentally included.
                if (preg_match('/^\|[-:\s|]+\|$/', $line)) {
                    continue;
                }
                $rows .= $line . "\n";
            }
        }

        if ($rows !== '') {
            $this->appliedLocalAdditions[] = '.bricklayer/decision-matrix.md';
        }

        return $rows;
    }

    /**
     * Scan `.bricklayer/guidelines/` for local guideline files. Files whose
     * relative path matches a bundled guideline are recorded as overrides and
     * skipped (the override takes effect in ContextTools at load time). Files
     * with no bundled counterpart are compiled into additional sections in
     * the generated agent file.
     */
    private function compileLocalGuidelineAdditions(): string
    {
        if ($this->magentoRoot === null) {
            return '';
        }

        $localDir = $this->magentoRoot . '/.bricklayer/guidelines/';
        $relativePaths = $this->collectMarkdownRelativePaths($localDir);
        if ($relativePaths === []) {
            return '';
        }

        $localDirPrefix = rtrim($localDir, '/\\') . '/';
        $bundledRelativePaths = $this->getBundledGuidelineRelativePaths();

        $grouped = [];
        foreach ($relativePaths as $relativePath) {
            $absolute = $localDirPrefix . $relativePath;

            if (in_array($relativePath, $bundledRelativePaths, true)) {
                $this->appliedLocalOverrides[] = '.bricklayer/guidelines/' . $relativePath;
                continue;
            }

            $categoryDir = dirname($relativePath);
            if ($categoryDir === '.' || $categoryDir === '') {
                $categoryDir = 'project';
            }
            $categoryDir = basename($categoryDir);

            $sectionName = LocalOverrideHelper::defaultDisplayName($categoryDir);
            $filenameBase = (string) preg_replace('/\.md$/i', '', basename($relativePath));
            $subHeading = LocalOverrideHelper::defaultDisplayName($filenameBase);

            $content = file_get_contents($absolute);
            if ($content === false) {
                continue;
            }
            $content = trim($content);

            $grouped[$sectionName][] = [
                'heading' => $subHeading,
                'content' => $content,
                'path' => '.bricklayer/guidelines/' . $relativePath,
            ];
        }

        if ($grouped === []) {
            return '';
        }

        ksort($grouped);
        $output = '';
        foreach ($grouped as $sectionName => $entries) {
            usort($entries, fn($a, $b) => strcmp($a['heading'], $b['heading']));
            $output .= "## {$sectionName}\n\n";
            foreach ($entries as $entry) {
                $output .= "### {$entry['heading']}\n\n{$entry['content']}\n\n";
                $this->appliedLocalAdditions[] = $entry['path'];
            }
        }

        return rtrim($output, "\n");
    }

    /**
     * Build extra rows for the context categories table from local skill
     * directories that have no bundled equivalent.
     */
    private function buildLocalSkillsTableRows(): string
    {
        if ($this->magentoRoot === null) {
            return '';
        }

        $localSkillsDir = $this->magentoRoot . '/.bricklayer/skills/';
        if (!is_dir($localSkillsDir)) {
            return '';
        }

        $bundledCategories = $this->getBundledSkillCategories();
        $rows = '';

        $entries = @scandir($localSkillsDir);
        if ($entries === false) {
            return '';
        }
        sort($entries);

        foreach ($entries as $entryName) {
            if ($entryName === '.' || $entryName === '..') {
                continue;
            }
            $entryPath = $localSkillsDir . $entryName;
            if (!is_dir($entryPath)) {
                continue;
            }
            $skillFile = $entryPath . '/SKILL.md';
            if (!file_exists($skillFile)) {
                continue;
            }

            $category = $entryName;
            if (in_array($category, $bundledCategories, true)) {
                // Override case: bundled row already covers it.
                $this->appliedLocalOverrides[] = '.bricklayer/skills/' . $category . '/SKILL.md';
                continue;
            }

            $meta = LocalOverrideHelper::parseSkillFrontmatter($skillFile);
            $description = $meta['description'] ?? LocalOverrideHelper::defaultDisplayName($category);
            $description = str_replace('|', '\\|', $description);

            $rows .= "| `{$category}` | {$description} |\n";
            $this->appliedLocalAdditions[] = '.bricklayer/skills/' . $category . '/SKILL.md';
        }

        return $rows;
    }

    /**
     * Collect the set of bundled guideline relative paths (e.g. "patterns/plugin.md").
     * Used to distinguish override files from additions in the local guidelines directory.
     *
     * @return list<string>
     */
    private function getBundledGuidelineRelativePaths(): array
    {
        return $this->collectMarkdownRelativePaths($this->packageRoot . '/config/guidelines/');
    }

    /**
     * @return list<string>
     */
    private function getBundledSkillCategories(): array
    {
        $bundledDir = $this->packageRoot . '/config/skills/';
        if (!is_dir($bundledDir)) {
            return [];
        }

        $categories = [];
        $entries = @scandir($bundledDir);
        if ($entries === false) {
            return [];
        }
        foreach ($entries as $entryName) {
            if ($entryName === '.' || $entryName === '..') {
                continue;
            }
            if (is_dir($bundledDir . $entryName)) {
                $categories[] = $entryName;
            }
        }

        return $categories;
    }
}
