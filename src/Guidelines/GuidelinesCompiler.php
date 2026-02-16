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
        $sections[] = $this->buildContextCategoriesSection();
        $sections[] = $this->buildEfficiencySection();
        $sections[] = $this->buildToolSections();
        $sections[] = $this->buildArchitectureSection();
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
        $lines[] = '### Context Quick Reference';
        $lines[] = '';
        $lines[] = 'When writing code, call `development-context` for **each** matching category:';
        $lines[] = '';
        $lines[] = '| You are writing... | Call `development-context` with |';
        $lines[] = '|--------------------|-------------------------------|';
        $lines[] = '| **Any PHP file** | `coding-standards` (always) |';
        $lines[] = '| `Cron/*.php`, `etc/crontab.xml` | `cron` |';
        $lines[] = '| `Observer/*.php`, `etc/events.xml` | `observer` |';
        $lines[] = '| `Plugin/*.php` | `plugin` |';
        $lines[] = '| `Model/*.php`, `ResourceModel/*.php`, `Api/Data/*Interface.php` | `model` |';
        $lines[] = '| `Api/*Interface.php` (service contracts) | `model` |';
        $lines[] = '| `Model/ResourceModel/*.php`, `etc/db_schema.xml` | `model` |';
        $lines[] = '| `Setup/Patch/Data/*.php` | `data-patch` |';
        $lines[] = '| `Controller/Adminhtml/*.php`, admin UI | `adminhtml` |';
        $lines[] = '| `view/adminhtml/ui_component/*.xml` (grid) | `ui-component` |';
        $lines[] = '| `view/adminhtml/ui_component/*.xml` (form) | `ui-component-form` |';
        $lines[] = '| `Controller/*.php` (frontend) | `frontend` |';
        $lines[] = '| `*.phtml`, `view/frontend/layout/*.xml` | `frontend` |';
        $lines[] = '| `Magewire/*.php`, `wire:` templates | `hyva-checkout` |';
        $lines[] = '| `hyva_checkout_*.xml` | `hyva-checkout-config` |';
        $lines[] = '| Hyvä `*.phtml` with Alpine.js | `hyva-theme` |';
        $lines[] = '| `etc/webapi.xml`, REST API classes | `rest-api` |';
        $lines[] = '| `etc/schema.graphqls`, resolvers | `graphql` |';
        $lines[] = '| `etc/indexer.xml`, indexer classes | `indexer` |';
        $lines[] = '| `registration.php`, `etc/module.xml`, `composer.json` | `module` |';

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
Use `max_entry_length` on `log-read` and `log-search` to limit long entries.
Start with `max_entry_length=500` and increase only if you need full stack traces.
MARKDOWN;
    }

    private function buildToolSections(): string
    {
        $toolData = $this->toolScanner->scan();
        $sections = [];

        foreach ($toolData['groups'] as $title => $group) {
            $lines = [];
            $lines[] = "### {$title}";
            $lines[] = '';

            if (isset($group['subtitle'])) {
                $lines[] = $group['subtitle'];
                $lines[] = '';
            }

            if ($title === 'System & Development Tools') {
                $lines[] = '*Tip: For multi-step operations, prefer `code-runner` over chaining individual tool calls.*';
                $lines[] = '';
            }

            $columns = $group['columns'];
            $hasPrerequisite = in_array('Prerequisite', $columns, true);

            // Build header row
            $lines[] = '| ' . implode(' | ', $columns) . ' |';
            $lines[] = '|' . implode('|', array_map(fn() => '------', $columns)) . '|';

            // Build data rows
            foreach ($group['tools'] as $tool) {
                $cells = ['`' . $tool['name'] . '`', $tool['description']];

                if ($hasPrerequisite) {
                    $cells[] = $tool['meta']['prerequisite'] ?? 'None';
                }

                $lines[] = '| ' . implode(' | ', $cells) . ' |';
            }

            $sections[] = implode("\n", $lines);
        }

        return implode("\n\n", $sections);
    }

    private function buildArchitectureSection(): string
    {
        return <<<'MARKDOWN'
## Magento Architecture Guidelines

### Module Structure

All module files must be placed in the correct directories:

```
app/code/Vendor/Module/
├── Api/                     # Service contracts (interfaces)
│   └── Data/               # Data interfaces
├── Block/                  # View blocks
├── Controller/             # Controllers
│   ├── Adminhtml/         # Admin controllers
│   └── Index/             # Frontend controllers
├── etc/                    # Configuration
│   ├── adminhtml/         # Admin-specific config
│   ├── frontend/          # Frontend-specific config
│   ├── di.xml             # Dependency injection
│   ├── module.xml         # Module declaration
│   └── routes.xml         # Route configuration
├── Model/                  # Business logic
│   └── ResourceModel/     # Database operations
├── Observer/              # Event observers
├── Plugin/                # Plugins (interceptors)
├── Setup/                 # Installation scripts
│   └── Patch/            # Data/Schema patches
├── view/                  # View files
│   ├── adminhtml/        # Admin templates/layouts
│   └── frontend/         # Frontend templates/layouts
├── registration.php       # Module registration
└── composer.json         # Composer definition
```

### Coding Standards

- Use `declare(strict_types=1);` in all PHP files
- Follow PSR-12 coding standards
- Use constructor property promotion (PHP 8.1+)
- Always type-hint method parameters and return types
- Use service contracts (interfaces) over concrete implementations
- Prefer composition over inheritance

### Dependency Injection

- Never use ObjectManager directly in application code
- Inject dependencies via constructor
- Use interfaces for dependencies, not concrete classes
- Define preferences in di.xml for interface implementations

### Plugins vs Observers vs Preferences

| Mechanism | When to Use |
|-----------|-------------|
| Plugin | Modify method behavior (before/after/around) |
| Observer | React to events without modifying source |
| Preference | Complete class replacement (use sparingly) |

### Database Operations

- Use declarative schema (db_schema.xml) for table definitions
- Use data patches for data migrations
- Always use repositories for CRUD operations
- Use SearchCriteriaBuilder for complex queries
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
