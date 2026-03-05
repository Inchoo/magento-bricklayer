<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Guidelines;

use Inchoo\MagentoBricklayer\Mcp\Tool\ContextTools;

class GuidelinesCompiler
{
    public function __construct(
        private readonly ToolScanner $toolScanner = new ToolScanner(),
    ) {
    }

    public function compile(string $agent, string $envType = 'native'): string
    {
        $sections = [];
        $sections[] = $this->buildHeader();
        $sections[] = $this->buildIntrospectionSection();
        $sections[] = $this->buildContextCategoriesSection();
        $sections[] = $this->buildEfficiencySection();
        $sections[] = $this->getShellCommandsSection($envType);
        $sections[] = $this->buildFooter($agent);

        return implode("\n\n", array_filter($sections));
    }

    public function getFilename(string $agent): string
    {
        $fileMap = [
            'claude-code' => 'CLAUDE.md',
            'cursor' => '.cursorrules',
            'copilot' => '.github/copilot-instructions.md',
            'phpstorm' => '.junie/guidelines.md',
            'gemini' => 'AGENTS.md',
        ];

        return $fileMap[$agent] ?? 'AGENTS.md';
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
        return <<<'MARKDOWN'
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
| Creating a cron job | `system-status check=cron` | `development-context category=cron` |
| Debugging an error | `diagnose-error` | (based on diagnosis) |
| Investigating performance | `diagnose-performance` | `development-context category=performance` |
| Writing **any** PHP file | — | `development-context category=coding-standards` (always) |
MARKDOWN;
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

        return <<<MARKDOWN
---

*Generated by magento-bricklayer*
*Timestamp: {$timestamp}*
*Agent: {$agent}*
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
}
