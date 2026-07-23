# Magento Bricklayer

An MCP server that gives AI coding agents runtime visibility into Magento 2. Agents reading source files miss the full picture — DI preferences, plugin chains, EAV attributes, and event observers are resolved at runtime across dozens of modules. Bricklayer exposes this runtime state so agents make informed architectural decisions instead of guessing.

## Table of Contents

- [What is Bricklayer?](#what-is-bricklayer)
- [How Agents Use Bricklayer](#how-agents-use-bricklayer)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Supported AI Agents](#supported-ai-agents)
- [Available Commands](#available-commands)
  - [install](#install)
  - [init](#init)
  - [mcp](#mcp)
  - [inspect](#inspect)
  - [update](#update)
  - [verify](#verify)
- [Docker / Container Environments](#docker--container-environments)
  - [Automatic Container Detection](#automatic-container-detection)
- [MCP Tools Overview](#mcp-tools-overview)
  - [Application & Module Tools](#application--module-tools)
  - [Database Tools](#database-tools)
  - [EAV Tools](#eav-tools)
  - [Configuration & DI Tools](#configuration--di-tools)
  - [Routing & API Tools](#routing--api-tools)
  - [GraphQL Tools](#graphql-tools)
  - [View Tools](#view-tools)
  - [Message Queue Tools](#message-queue-tools)
  - [Catalog Tools](#catalog-tools)
  - [Order Tools](#order-tools)
  - [Customer Tools](#customer-tools)
  - [Development Tools](#development-tools)
  - [Log Tools](#log-tools)
  - [Diagnostic Tools](#diagnostic-tools)
  - [Code Runner Tool](#code-runner-tool)
  - [Code Generation Tools](#code-generation-tools)
  - [Development Context Tool](#development-context-tool)
  - [Context-Aware Hints](#context-aware-hints)
  - [Pagination](#pagination)
- [MCP Resources](#mcp-resources)
  - [Guidelines Resource](#guidelines-resource)
  - [Coding Standards Resource](#coding-standards-resource)
  - [Skills Resource](#skills-resource)
  - [Template & Reference Resources](#template--reference-resources)
- [Configuration](#configuration)
  - [Per-Tool Configuration](#per-tool-configuration)
  - [Recommended Production Configuration](#recommended-production-configuration)
  - [Environment Variable Overrides](#environment-variable-overrides)
- [Architecture](#architecture)
  - [Auto-Reinitialize](#auto-reinitialize)
  - [Config Hot-Reload](#config-hot-reload)
- [Project-Local Overrides](#project-local-overrides)
  - [Directory layout](#directory-layout)
  - [Override vs addition semantics](#override-vs-addition-semantics)
  - [Optional SKILL.md frontmatter](#optional-skillmd-frontmatter)
  - [Typical workflow](#typical-workflow)
- [Extending Bricklayer](#extending-bricklayer)
  - [Add a tool to an existing class](#add-a-tool-to-an-existing-class)
  - [Add a new tool class](#add-a-new-tool-class)
  - [Add a guideline](#add-a-guideline)
  - [Add a skill](#add-a-skill)
  - [Add a development-context category](#add-a-development-context-category)
  - [Regenerate documentation](#regenerate-documentation)
- [Security](#security)
  - [Query & Log Safety](#query--log-safety)
  - [Production Mode Protection](#production-mode-protection)
  - [Per-Tool Configuration](#per-tool-configuration-1)
  - [Code Execution Safety](#code-execution-safety)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

## What is Bricklayer?

Bricklayer is a Composer library that implements an MCP server for Magento 2. When started, it exposes 84 tools that AI agents can invoke to:

- **Check runtime state before writing code** — see actual plugin chains, DI resolution, preferences, and event observers across all installed modules
- Inspect database schema, EAV attributes, and system configuration as they exist at runtime
- Diagnose errors with full context (exception + stack trace + DI + plugin chains + fix suggestions)
- Analyze performance across indexes, cache, cron, and query patterns
- Manage products, orders, and customers through Magento's service layer
- Generate Magento-compliant module scaffolding with conflict detection
- Load domain-specific development guidelines and coding standards on demand

Only 17 essential tools are visible at startup — the remaining 67 are discoverable via `search-tools`, reducing token overhead while keeping all tools callable.

The name "Bricklayer" reflects the methodical, structured approach to building Magento 2 modules and extensions, laying each component (the "bricks") in the correct order and position to construct a solid, maintainable codebase.

## How Agents Use Bricklayer

Magento resolves DI configuration, plugin chains, preferences, and event observers at runtime across all installed modules. An agent reading source files sees only one module's perspective — it misses overrides, conflicts, and customizations from other modules. Bricklayer bridges this gap.

**Before writing a plugin**, an agent calls `check-class` to see existing plugins and their sortOrders, DI preferences, and class rewrites — preventing conflicts that would only surface in environments with more extensions installed.

**Before working with products or customers**, an agent calls `eav-attributes` to discover custom attributes that exist only in the database, not in any source file.

**When debugging**, an agent calls `diagnose-error` which combines the exception log, stack trace, DI context, and plugin chain analysis into a single actionable diagnosis — far more than reading `var/log/exception.log` alone.

**Before writing any code**, an agent calls `development-context` to load domain-specific guidelines (plugin patterns, EAV best practices, Hyvä checkout APIs, etc.) ensuring the generated code follows Magento conventions.

Each tool response includes hints pointing to the next logical step — introspection tools suggest relevant guidelines to load, and guidelines suggest which runtime checks to perform. This creates a natural workflow: **check → learn → write**.

## Requirements

- PHP 8.1 or higher
- Magento 2.4.4 or higher
- Composer 2.2 or higher

## Installation

### As a Development Dependency (Recommended)

```bash
composer require --dev inchoo/magento-bricklayer
```

### Global Installation

```bash
composer global require inchoo/magento-bricklayer
```

## Quick Start

### 1. Install Agent Configuration

Run from your Magento project root:

```bash
vendor/bin/bricklayer install
```

This prompts you to select which AI agents to configure and generates:
- `.mcp.json` - MCP server configuration (always created)
- `.bricklayer.json` - Tool safety configuration with deploy-mode-aware defaults
- Agent-specific guideline files based on your selection (e.g. `CLAUDE.md`, `.cursorrules`)

### 2. Start Using with Your AI Agent

The MCP server is automatically started by compatible agents. Your agent can now:

- Use `application-info` to understand your Magento installation
- Use `module-list` to see installed modules
- Use `database-schema` to inspect table structures
- Use `product-get`, `order-get`, `customer-get` for data access
- Use `diagnose-error` to diagnose errors with full context and fix suggestions
- Use `development-context` to load coding guidelines for your task
- And many more tools for comprehensive Magento development

## Supported AI Agents

| Agent | Configuration Files | Status |
|-------|---------------------|--------|
| Claude Code | `.mcp.json` + `CLAUDE.md` | Fully Supported |
| Cursor | `.mcp.json` + `.cursorrules` | Fully Supported |
| GitHub Copilot | `.mcp.json` + `.github/copilot-instructions.md` | Supported |
| JetBrains AI (PhpStorm) | `.mcp.json` + `.junie/guidelines.md` | Supported |
| Gemini CLI | `.mcp.json` + `AGENTS.md` | Supported |

## Available Commands

### install
Generates agent configuration files for your AI tools.

```bash
vendor/bin/bricklayer install [options]
```

Options:
- `--magento-root=PATH` - Specify Magento root directory (auto-detected by default)
- `--agents=AGENT` - Agent to configure; **repeat the flag** for multiple (e.g. `--agents=claude-code --agents=cursor`). Valid values: claude-code, cursor, phpstorm, copilot, gemini. Omit to choose interactively
- `--force` - Overwrite existing configuration files

### init
Generates `.bricklayer.json` configuration file with deploy-mode-aware defaults.

```bash
vendor/bin/bricklayer init [options]
```

Options:
- `--magento-root=PATH` - Specify Magento root directory (auto-detected by default)
- `--force` - Overwrite existing `.bricklayer.json`

The generated config contains **one entry per runtime-configurable tool** (36 tools at the time of writing). The list is discovered by scanning the source for `requireToolEnabled()` call sites, so every key the file contains is one the runtime actually honors — no dead keys, no drift.

Deploy-mode behavior:
- **production** — 12 tools disabled (code-runner + all 11 destructive/code-generation tools), `database-query.max_rows` lowered to 50
- **developer/default** — 11 destructive tools (`*-delete`, `order-cancel`, `order-create`, `creditmemo-create`, 4 `generate-*`) disabled by default, everything else enabled, `code-runner` read-only

This command is also called automatically during `bricklayer install`. Additionally, `bricklayer verify` will auto-generate the file if it's missing.

### config:set
Update a single value in `.bricklayer.json` with full validation, round-trip verification, and a guided interactive mode for discoverability.

```bash
# Interactive — walks you through tool selection, setting, and value
vendor/bin/bricklayer config:set

# Scripted — dot-notation key + value
vendor/bin/bricklayer config:set tools.product-delete.enabled true
vendor/bin/bricklayer config:set tools.database-query.max_rows 250
vendor/bin/bricklayer config:set tools.code-runner.allow_write false
```

Options:
- `--magento-root=PATH` — Specify Magento root directory (auto-detected by default)

**Interactive mode** (no arguments) is the recommended path for newcomers. It lists all 36 runtime-configurable tools with their current values inline, lets you pick a tool, pick a setting (when more than one is available), and enter a new value with type-aware validation (bool picker, int validator that re-prompts on non-numeric input).

**Scripted mode** (positional arguments) is for automation. Values are parsed automatically: `true`/`false` → bool, `null` → null, numeric → int/float, `[...]`/`{...}` → JSON-decoded, anything else → string.

**Safety checks** on every successful set:
- **Validation** — refuses to write if the resulting config fails `ConfigValidator` (e.g. negative `max_rows`, non-bool `enabled`)
- **Round-trip verification** — re-loads the file through `ConfigLoader` and confirms the new value is readable
- **Non-configurable tool warning** — warns if you try to set `enabled` on a read-only introspection tool that does not honor the flag at runtime
- **Environment-variable shadow warning** — warns if a matching `BRICKLAYER_*` env var is set that would override your file change
- **Hot-reload note** — reminds you that the running MCP server picks up changes on its next tool call, so no agent restart is required

Auto-creates `.bricklayer.json` with deploy-mode-aware defaults if the file is missing.

Negative integer values need the `--` separator (standard Symfony Console behavior):
```bash
vendor/bin/bricklayer config:set -- tools.database-query.max_rows -1  # will fail validation
```

### mcp
Starts the MCP server (invoked automatically by AI agents).

```bash
vendor/bin/bricklayer mcp [options]
```

Options:
- `--magento-root=PATH` - Specify Magento root directory

### inspect
Displays information about the current Magento installation.

```bash
vendor/bin/bricklayer inspect [options]
```

Options:
- `--json` - Output as JSON instead of formatted table
- `--no-bootstrap` - Skip full Magento bootstrap (faster, limited info)

### update
Regenerates agent configuration files (CLAUDE.md, .cursorrules, etc.) from the current bundled content plus any project-local overrides in `.bricklayer/`.

```bash
vendor/bin/bricklayer update [options]
```

Options:
- `--magento-root=PATH` - Specify Magento root directory (auto-detected by default)

When the project has files under `.bricklayer/` (see [Project-Local Overrides](#project-local-overrides)), the command reports which local files were applied alongside the regenerated agent files.

### verify
Runs a post-install health check against your Bricklayer installation.

```bash
vendor/bin/bricklayer verify [options]
```

Options:
- `--magento-root=PATH` - Specify Magento root directory (auto-detected by default)
- `--json` - Output results as JSON

Checks performed: Magento bootstrap, deploy mode detection, MCP server creation and tool count, agent configuration files, PsySH availability for `code-runner`, database connectivity, log directory writability, and `.bricklayer.json` validation. If `.bricklayer.json` is missing, `verify` auto-generates it with deploy-mode-aware defaults.

## Docker / Container Environments

Bricklayer automatically detects Docker, DDEV, and Warden environments:

```bash
# The install command generates appropriate configuration
vendor/bin/bricklayer install

# For Docker Compose, it generates:
# "command": "docker compose exec -T php php vendor/bin/bricklayer-mcp"

# For DDEV:
# "command": "ddev exec php vendor/bin/bricklayer-mcp"

# For Warden:
# "command": "warden shell -c php vendor/bin/bricklayer-mcp"
```

### Automatic Container Detection

For Docker environments with dynamic or non-standard container names, use the `bricklayer-mcp-docker` wrapper script. This script automatically detects the PHP container at runtime:

```json
{
    "mcpServers": {
        "magento-bricklayer": {
            "command": "vendor/inchoo/magento-bricklayer/bin/bricklayer-mcp-docker",
            "args": []
        }
    }
}
```

The wrapper script:
- Automatically finds the PHP container by matching common patterns (apache-php, php-fpm, magento, web, app)
- Excludes utility containers (phpmyadmin, redis, elasticsearch, varnish, etc.)
- Returns a proper JSON-RPC error if no container is found
- Supports custom Magento root via `MAGENTO_ROOT` environment variable (defaults to `/var/www/html`)

This is useful when container names vary between environments or are dynamically generated (e.g., `projectname-apache-php-1`).

## MCP Tools Overview

Bricklayer uses **progressive disclosure** — 17 essential tools are visible in `tools/list` while 67 additional tools remain callable and discoverable via `search-tools`. This reduces token overhead for AI agents. Tools marked with **[tier 1]** are always visible; all others are tier 2.

### Application & Module Tools
- `application-info` — Magento version, PHP version, deploy mode, module counts. Use `include=stores` for website/store hierarchy
- `module-list` — All installed modules with version, status, and vendor
- `module-structure` — File/folder structure of a module with dependencies
- `validate-module` — Validates module code structure (registration.php, module.xml, composer.json, strict_types)

### Database Tools
- `database-schema` **[tier 1]** — Table structures, columns, indexes, foreign keys
- `database-query` **[tier 1]** — Execute read-only SELECT queries with automatic LIMIT enforcement

### EAV Tools
- `eav-attributes` **[tier 1]** — EAV attributes for entity types (catalog_product, catalog_category, customer, customer_address)
- `eav-entity-types` — List all supported EAV entity types

### Configuration & DI Tools
- `check-class` **[tier 1]** — Combined pre-modification check: returns plugins, DI configuration, and preferences for any class in one call. Essential before writing plugins, preferences, or DI overrides
- `configuration-get` — Retrieve system configuration values by path (with scope support)
- `configuration-list` — List available configuration paths by section
- `di-configuration` **[tier 1]** — DI configuration showing preferences and plugins for classes
- `plugin-list` **[tier 1]** — List all plugins/interceptors for a class with method filtering
- `event-list` — List events and observers with area filtering
- `preference-list` **[tier 1]** — List all class preference rewrites

### Routing & API Tools
- `route-list` — Frontend and admin routes with handling modules
- `route-info` — Detailed information about specific routes with controller classes
- `api-endpoints` — REST API endpoints with method/path filtering and security info
- `url-rewrites` — URL rewrites with request path and store filtering

### GraphQL Tools
- `graphql-inspect` — Consolidated GraphQL introspection tool. Use `target` (types|queries|mutations|resolvers) to select what to inspect, and optional `name` for detail on a specific type

### View Tools
- `layout-inspect` — Resolve a layout handle into its runtime-merged block/container tree (merged across all modules and the active theme), or omit the handle to list every registered handle for an area. Shows applied `referenceBlock`/`referenceContainer`/`move`/`remove` directives, declared and theme-resolved `.phtml` template paths, and the page layout. Params: `handle`, `area` (frontend|adminhtml), `verbosity`
- `ui-component-inspect` — Resolve an admin grid or form UI component's runtime-merged configuration — component tree, data source, columns/fieldsets, and child components — merged across all modules. Params: `name` (e.g. `customer_listing`), `verbosity`

### Message Queue Tools
- `message-queue-inspect` — Runtime-merged message-queue wiring: consumers, topics, queue/exchange bindings, publishers, and handlers assembled across `communication.xml`, `queue_consumer.xml`, `queue_topology.xml`, and `queue_publisher.xml` of every module. Optional `consumer`/`topic` filters; each section degrades independently if a sub-config is absent

### Catalog Tools
- `product-get` **[tier 1]**, `product-list`, `product-create`, `product-update`, `product-delete`
- `product-stock-get`, `product-stock-update`
- `product-media-list`, `product-media-add`
- `product-link-list`, `product-link-set`
- `category-get`, `category-tree`, `category-create`, `category-update`, `category-delete`
- `category-products`, `category-assign-products`

### Order Tools
- `order-get` **[tier 1]**, `order-list`, `order-create`, `order-cancel`, `order-hold`, `order-unhold`
- `order-items`, `order-comments`, `order-add-comment`
- `invoice-create`, `invoice-list`
- `shipment-create`, `shipment-list`, `shipment-track-add`
- `creditmemo-create`, `creditmemo-list`

### Customer Tools
- `customer-get` **[tier 1]**, `customer-list`, `customer-create`, `customer-update`, `customer-delete`
- `customer-validate`, `customer-groups-list`, `customer-orders`
- `customer-addresses`, `customer-address-create`, `customer-address-update`, `customer-address-delete`

### Development Tools
- `system-status` — Consolidated system check tool. Use `check` (cache|indexers|deploy-mode|cron|cron-history) to select what to inspect
- `reinitialize` — Rebuild the Magento ObjectManager after external state changes. Clears any defined code-runner functions. Also triggers automatically when `app/etc/config.php` or `generated/metadata/global.php` change on disk
- `code-runner` **[tier 1]** — Execute PHP code in Magento context with helper functions, area emulation, read-only mode, and metrics
- `code-runner-help` **[tier 1]** — Returns detailed code-runner usage guide with helpers, variables, areas, and examples
- `search-docs` — Semantic documentation search
- `search-tools` **[tier 1]** — Search available MCP tools by keyword or group with configurable detail level
- `batch-execute` **[tier 1]** — Execute multiple tool operations in a single call (max 20)

### Log Tools
- `log` — Consolidated log tool. Use `action` (read|list|search|analyze) with per-action parameters (logType, lines, filter, query, hours, etc.)

### Diagnostic Tools
- `diagnose-error` **[tier 1]** — Diagnoses the most recent Magento error with full context, DI analysis, and actionable fix suggestions in a single call
- `diagnose-performance` — Analyzes Magento performance configuration and data. Use `check` (all|indexes|cache|flat-tables|cron-backlog|config|queries) to select what to inspect. Returns findings with severity levels (info/warning/critical) and fix suggestions

The `diagnose-error` tool orchestrates multiple introspection tools to produce a comprehensive diagnosis:

```
diagnose-error(index=0, source="exception", since="1h", pattern="", verbosity="standard")
```

**Parameters:**
| Parameter | Default | Description |
|-----------|---------|-------------|
| `index` | `0` | Which error to diagnose (0 = most recent) |
| `source` | `exception` | Log file: exception, system, debug, cron |
| `since` | `1h` | Time window: 5m, 1h, 24h, 7d |
| `pattern` | `""` | Substring filter for error messages |
| `verbosity` | `standard` | Detail level: minimal, standard, detailed |

**Response structure:**
- `error` — Parsed exception with class, message, file, line, stack trace, and chained previous exception
- `module_context` — Responsible module's name, version, enabled status, dependencies, and validation issues
- `di_context` — DI preferences and plugins for the error class
- `environment` — Deploy mode, disabled caches, invalid indexers, generated code age
- `history` — Error frequency and top exception types, counted from the log the matched error actually came from (e.g. an error found via the `system.log` fallback is counted in `system.log`, not `exception.log`). Carries `available: false` for sources with no aggregate log to count from (e.g. `var/report`), instead of reporting misleading counts from an unrelated log
- `suggestions` — Actionable fixes with confidence levels (high/medium/low) and CLI commands
- `_hint` — *(conditional)* Next-step guidance when plugins or DI issues are involved

The tool recognizes 15 common Magento error patterns including class-not-found, DI compilation errors, database issues, search engine failures, invalid templates/blocks, memory exhaustion, and session errors.

### Code Runner Tool
- `code-runner` - Execute PHP code within the Magento application context with helper functions, area emulation, transaction rollback, and execution metrics

```
code-runner(code, area="", allow_write=false, timeout=30, mode="execute")
```

**Parameters:**
| Parameter | Default | Description |
|-----------|---------|-------------|
| `code` | (required) | PHP code to execute (without `<?php` tags) |
| `area` | `""` | Magento area for DI resolution: frontend, adminhtml, webapi_rest, graphql, crontab, global |
| `allow_write` | `false` | When false, DB changes are rolled back after execution |
| `timeout` | `30` | Maximum execution time in seconds |
| `mode` | `"execute"` | `execute` runs code normally; `define` saves reusable functions for the session |

**Reusable functions** (`mode=define`):

Define named PHP functions that persist across `code-runner` calls within the same session. Functions are validated against the same dangerous pattern blocklist and cleared on `reinitialize`. Maximum 20 defined functions per session.

```
code-runner(mode="define", code="function getProductBySku($sku) { return get(\Magento\Catalog\Api\ProductRepositoryInterface::class)->get($sku); }")
code-runner(code="$p = getProductBySku('24-MB01'); return $p->getName();")
```

**Helper functions** available in executed code:
- `$get(ClassName::class)` - Retrieve singleton from DI container
- `$create(ClassName::class, ['arg' => val])` - Create new instance
- `$repo(RepositoryInterface::class)` - Alias for `$get()`, semantic sugar for repositories
- `$config('section/group/field')` - Read system configuration value
- `query('SELECT ...', $binds)` - Execute a read-only SELECT query, returns array of rows
- `runLog($value, 'label')` - Capture values to return in the response `log` key

**Response includes:**
- `success`, `output`, `return`, `error` - Standard execution result
- `runtime` - Execution engine: `psysh` when PsySH is installed (the default), or `eval` as a fallback when it is not
- `read_only` - Whether DB changes were rolled back (`true` on every default call — see below)
- `area` - Effective area code (if specified)
- `log` - Values captured via `runLog()` helper
- `metrics` - Execution time (ms), memory delta (MB), peak memory (MB), queries executed

Code runs through a [PsySH](https://psysh.org/) shell when the `psy/psysh` dependency is available (it ships as a Bricklayer requirement), falling back to a plain `eval` engine otherwise — `verify` reports which is active. Read-only mode wraps execution in a database transaction that is **always rolled back** unless `allow_write=true`, so `read_only: true` and the rollback note appear on every default call (even for code that performs no writes); pass `allow_write=true` to commit.

The tool validates code against 9 dangerous patterns (shell execution, file writes, superglobals, cURL, eval, header manipulation, global handler registration, long sleeps). Disabled in production mode and configurable via `.bricklayer.json`.

### Code Generation Tools
- `generate-module` — Scaffold a new Magento 2 module with registration.php, module.xml, composer.json
- `generate-model` — Create model, resource model, and collection classes with db_schema.xml
- `generate-controller` — Create controller with routes.xml, layout XML, and template
- `generate-api` — Create REST API endpoint with interface, implementation, and webapi.xml

All code generation tools support `dry_run` (preview without writing) and `force` (overwrite existing files) parameters. In dry-run mode, each file is annotated with `new` or `exists` status. Without `force`, existing files cause a conflict error listing the affected paths.

### Development Context Tool
- `development-context` **[tier 1]** — Load coding guidelines and development patterns BEFORE writing code. 38 categories covering plugins, EAV, GraphQL, Hyvä, Magewire, checkout, payment, testing, and more. Returns `_next_steps` suggesting which introspection tools to call. Use category `list` to see all available categories. Always load `coding-standards` for any PHP file.

### Context-Aware Hints

Select tools return a conditional `_hint` field in their response when they detect a situation where the agent would benefit from a follow-up action:

| Tool | Condition | Hint |
|------|-----------|------|
| `product-get` | Product has custom EAV attributes | Points to `eav-attributes` for attribute metadata |
| `customer-get` | Customer has custom EAV attributes | Points to `eav-attributes` for attribute metadata |
| `system-status check=indexers` | Any indexer is invalid | Points to `log` for related errors |
| `system-status check=cache` | Any cache type is disabled | Warns about potential impact |
| `diagnose-error` | Exception involves plugins | Points to `plugin-list` for the relevant class |
| `diagnose-error` | Exception involves DI config | Points to `di-configuration` for the relevant class |

Additionally, introspection tools return a `_skill_hint` field that guides agents to load relevant development guidelines:

| Tool | Hint |
|------|------|
| `plugin-list` | Points to `development-context category=plugin` |
| `di-configuration` | Points to `development-context category=module` |
| `preference-list` | Points to `development-context category=preference` |
| `event-list` | Points to `development-context category=observer` |
| `eav-attributes` | Points to `development-context category=eav` |
| `database-schema` | Points to `development-context category=model` |
| `graphql-inspect` | Points to `development-context category=graphql` |
| `route-list` | Points to `development-context category=frontend` |
| `api-endpoints` | Points to `development-context category=rest-api` |
| `layout-inspect` | Points to `development-context category=frontend` |
| `ui-component-inspect` | Points to `development-context category=ui-component` |
| `message-queue-inspect` | Points to `development-context category=message-queue` |
| `diagnose-performance` | Points to `development-context category=performance` |

The `development-context` tool itself returns `_next_steps` — suggesting which introspection tools to call for the loaded category (e.g., loading the `plugin` category suggests calling `check-class` for the target class).

### Pagination

All list tools include pagination metadata in their response:

```json
{
    "total_count": 150,
    "page_size": 20,
    "current_page": 1,
    "has_more": true,
    "items": [...]
}
```

The `has_more` field provides a reliable signal for agents to decide whether to fetch additional pages.

## MCP Resources

Bricklayer provides MCP resources that AI agents can access for context. Guidelines and skills are auto-discovered from the filesystem — adding new files makes them available as resources automatically (see [Extending Bricklayer](#extending-bricklayer)).

### Guidelines Resource
30 Magento development guidelines accessed via URI template `magento://guidelines/{category}/{name}`, covering:
- Architecture patterns (plugins, observers, preferences, factories, repositories, service contracts)
- Coding standards (syntax & formatting, quality & best practices)
- Database patterns (declarative schema, EAV, data patches, indexers)
- Security, performance, and testing
- Frontend development (layout XML, templates, JavaScript)
- Area-specific guidance (adminhtml, frontend, webapi, graphql)
- Ecosystem guidelines (Adobe Commerce, Hyvä, Mage-OS)

Use `magento://guidelines/index` for a complete listing.

### Coding Standards Resource
Magento coding standards reference with PSR-12 compliance and architecture guidelines (`magento://standards/coding`, `magento://standards/architecture`).

### Skills Resource
Development skills are auto-discovered and accessed through `magento://skills/{name}`. A skill can bundle focused sub-skills in topic sub-directories, served via `magento://skills/{name}/{topic}`. Skills cover:
- Checkout customization (steps & layout processors, config providers & validation)
- Cron job development
- EAV attribute development
- GraphQL API development
- Hyvä Checkout (architecture, navigation, payments, components, XML, evaluation, and forms)
- Magewire standalone reactive components
- Hyvä theme development (setup & Alpine.js CSP, ViewModels & compatibility)
- Hyvä UI component development (CSS design system, Alpine.js interactivity)
- Import/Export functionality (import entities, export & advanced processing)
- Indexer development
- Message queue implementation
- Payment integration (module setup, gateway components, checkout integration)
- Plugin development
- REST API development
- Shipping integration
- Testing strategies
- Theme development (structure & layout XML, LESS/CSS styling & JavaScript)
- UI component development (admin grids, admin forms)

Use `magento://skills/index` for a complete listing.

### Template & Reference Resources
Code templates for common patterns (`magento://templates/module`, `magento://templates/controller`, `magento://templates/api`, `magento://templates/model`) and reference documentation (`magento://reference/events`, `magento://reference/di-patterns`, `magento://reference/acl`). Layout handle reference is no longer a static resource — the `layout-inspect` tool enumerates registered handles at runtime instead.

## Configuration

`.bricklayer.json` lives at your Magento root. The easiest way to create or edit it is:

```bash
# Generate with deploy-mode-aware defaults (also runs during `install`)
vendor/bin/bricklayer init

# Tweak individual values interactively (no need to memorize keys)
vendor/bin/bricklayer config:set
```

A generated developer-mode config looks like this (35 entries total — one per runtime-configurable tool; the snippet below is an excerpt):

```json
{
    "tools": {
        "code-runner": {
            "enabled": true,
            "allow_write": false,
            "max_timeout": 60
        },
        "database-query": {
            "enabled": true,
            "max_rows": 100
        },
        "database-schema": { "enabled": true },
        "log": {
            "enabled": true,
            "max_lines": 500
        },
        "diagnose-performance": { "enabled": true },
        "product-create": { "enabled": true },
        "product-update": { "enabled": true },
        "product-delete": { "enabled": false },
        "product-stock-update": { "enabled": true },
        "category-create": { "enabled": true },
        "category-delete": { "enabled": false },
        "order-cancel": { "enabled": false },
        "customer-delete": { "enabled": false },
        "generate-module": { "enabled": false }
    }
}
```

Every key in a generated config maps to a `requireToolEnabled()` call site in the source, so the file never contains dead keys. If you add keys that the runtime does not honor (e.g. `product-get.enabled`), `config:set` will warn you before writing.

### Per-Tool Configuration

Every write tool can be individually enabled or disabled via the `enabled` flag:

```json
{
    "tools": {
        "product-delete": { "enabled": false },
        "order-cancel": { "enabled": false },
        "generate-module": { "enabled": false }
    }
}
```

Destructive tools (`product-delete`, `category-delete`, `customer-delete`, `customer-address-delete`, `order-cancel`, `order-create`, `creditmemo-create`, and all 4 code generation tools) are **blocked by default in production mode**. They must be explicitly enabled in `.bricklayer.json` with `"enabled": true` to work in production.

### Recommended Production Configuration

```json
{
    "tools": {
        "code-runner": { "enabled": false },
        "database-query": { "enabled": false },
        "product-delete": { "enabled": false },
        "customer-delete": { "enabled": false },
        "category-delete": { "enabled": false },
        "generate-module": { "enabled": false },
        "generate-model": { "enabled": false },
        "generate-controller": { "enabled": false },
        "generate-api": { "enabled": false }
    }
}
```

### Environment Variable Overrides

Environment variables are the highest-priority configuration layer — they override `.bricklayer.json`.

Two variables are read directly and stand alone:

```bash
BRICKLAYER_MAGENTO_ROOT=/path/to/magento    # Override Magento root detection
BRICKLAYER_DEBUG=1                          # Enable debug output
```

Any tool setting is overridable by mapping its dot-notation config key to an env name:
prefix with `BRICKLAYER_`, upper-case, and replace both `.` and `-` with `_`. Hyphenated
tool names (`code-runner`, `database-query`, `product-delete`, …) are fully supported.

```bash
BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED=false       # tools.code-runner.enabled
BRICKLAYER_TOOLS_CODE_RUNNER_ALLOW_WRITE=false   # tools.code-runner.allow_write
BRICKLAYER_TOOLS_CODE_RUNNER_MAX_TIMEOUT=30      # tools.code-runner.max_timeout
BRICKLAYER_TOOLS_DATABASE_QUERY_MAX_ROWS=50      # tools.database-query.max_rows
BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED=true     # tools.product-delete.enabled
```

> The env key must include the full config path. For example `tools.code-runner.enabled`
> maps to `BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED` — **not** `BRICKLAYER_CODE_RUNNER_ENABLED`,
> which targets a different (unread) key and has no effect.

## Architecture

Bricklayer is implemented as a standalone Composer library rather than a Magento module. This follows the proven pattern established by n98-magerun2:

- **Zero Magento footprint** - No module registration, no `setup:upgrade` required
- **Full Magento access** - Uses ObjectManager for complete framework integration
- **Easy installation** - Just `composer require`, ready to use
- **Clean removal** - Just `composer remove`, no database cleanup

### Auto-Reinitialize

Bricklayer runs as a long-lived MCP server process that bootstraps Magento's ObjectManager once at startup. When external commands change the application state (e.g. `setup:upgrade`, `setup:di:compile`, `module:enable`), the in-memory ObjectManager can become stale — new modules won't be recognized, config defaults won't load, and DI preferences may be outdated.

To solve this, Bricklayer tracks the modification times of two sentinel files:
- `app/etc/config.php` — changes on `setup:upgrade`, `module:enable/disable`
- `generated/metadata/global.php` — changes on `setup:di:compile`

Before every tool call, the `RequiresMagento` trait checks these mtimes. If either file has changed since the last initialization, Magento is automatically reinitialized with a fresh ObjectManager — no manual intervention required. The staleness check costs two `filemtime()` calls (~microseconds) per tool invocation.

A manual `reinitialize` tool is also available for edge cases where sentinel files don't change (e.g. editing a module's `config.xml` without recompiling).

### Config Hot-Reload

Bricklayer also tracks the modification time of `.bricklayer.json`. When the file is edited while the MCP server is running (e.g. enabling a tool), the change is detected automatically on the next tool call — no server restart required. The staleness check costs a single `filemtime()` call per config access.

## Project-Local Overrides

Bricklayer ships with a bundled library of guidelines, skills, and a category map — but every Magento project has conventions that deviate from the bundled defaults (custom ERP integrations, CSP rules, payment quirks, house style). Project-local overrides let you add or replace content without forking the package.

Any file the project places under `.bricklayer/` at the Magento root is picked up automatically by `bricklayer update`, `development-context`, and `search-docs`. Nothing needs to be registered in `.bricklayer.json` — the path is the contract, modelled after Laravel Boost.

### Directory layout

```
{magento_root}/
└── .bricklayer/
    ├── project-context.md          # Free-form markdown appended to every generated agent file
    ├── decision-matrix.md          # Extra rows for the "Before Modifying" table (rows only, no header)
    ├── guidelines/
    │   ├── patterns/
    │   │   └── plugin.md           # OVERRIDE: replaces config/guidelines/patterns/plugin.md
    │   └── project/                # NEW CATEGORY: no bundled equivalent
    │       └── csp-scripts.md      # → compiled into CLAUDE.md as its own section
    └── skills/
        ├── plugin/
        │   └── SKILL.md            # OVERRIDE: replaces config/skills/plugin/SKILL.md
        └── csp-scripts/            # NEW SKILL: no bundled equivalent
            └── SKILL.md            # → callable via development-context category=csp-scripts
```

### Override vs addition semantics

- **Override** — a local file whose relative path matches a bundled file (`.bricklayer/guidelines/patterns/plugin.md` ↔ `config/guidelines/patterns/plugin.md`, or `.bricklayer/skills/plugin/SKILL.md` ↔ `config/skills/plugin/SKILL.md`). The local file replaces the bundled one wherever it would otherwise appear — `development-context` loads the local version, `search-docs` serves the local entry instead of the bundled one, and the override is reported in `bricklayer update` output.
- **Addition** — a local file with no bundled counterpart. New guideline files are compiled into the generated agent file as extra sections (heading derived from parent directory, sub-heading from filename). New skill directories become directly callable: `development-context category={directory-name}` returns the local SKILL.md content, and the category appears under a **Project-specific** group in the CLAUDE.md categories table.

### Optional SKILL.md frontmatter

Local `SKILL.md` files may start with a small YAML frontmatter block for richer display names and descriptions. The block is stripped before the content is handed to an agent.

```markdown
---
name: CSP Scripts
description: Patterns for managing Content Security Policy inline scripts in Magento 2.
---

# CSP Scripts

...skill content...
```

Only `name` and `description` are consumed — any other keys are ignored. Files with no frontmatter fall back to the directory name as display name.

### Typical workflow

```bash
# 1. Create the directory (bricklayer init also creates it for you)
mkdir -p .bricklayer/guidelines/project .bricklayer/skills/csp-scripts

# 2. Drop your project-specific content in
$EDITOR .bricklayer/project-context.md
$EDITOR .bricklayer/guidelines/project/csp-scripts.md
$EDITOR .bricklayer/skills/csp-scripts/SKILL.md

# 3. Regenerate agent files
vendor/bin/bricklayer update
```

`bricklayer update` reports which local files were applied:

```
  ✓ Regenerated CLAUDE.md
  Applied local overrides:
    - .bricklayer/project-context.md
    - .bricklayer/guidelines/project/csp-scripts.md
    - .bricklayer/skills/csp-scripts/SKILL.md
  Indexed 2 local file(s) into docs index
```

Local skills and guidelines are also tagged `[Project]` in `search-docs` results so agents can tell project conventions apart from bundled Magento knowledge.

## Extending Bricklayer

Bricklayer uses auto-discovery for tools, guidelines, skills, and categories. Adding new capabilities requires editing only the source file — agent documentation (CLAUDE.md, .cursorrules, etc.) is regenerated automatically when you run `vendor/bin/bricklayer update`. MCP resources (guidelines, skills, templates) are served dynamically on each agent request and need no separate build step.

### Add a tool to an existing class

Add a public method with `#[McpTool]` to any class in `src/Mcp/Tool/`:

```php
#[McpTool(name: 'product-archive', description: 'Archives a product by SKU')]
public function archiveProduct(string $sku): array { ... }
```

The tool is auto-detected via reflection and appears in the correct documentation section based on its class.

### Add a new tool class

Create a new file in `src/Mcp/Tool/`, e.g. `CmsTools.php`. Any class with `#[McpTool]` methods is picked up automatically and gets its own documentation section (class name `CmsTools` becomes heading "Cms Tools").

To customize the section title, column headers, or ordering, add an entry to `ToolScanner::GROUP_CONFIG`. This is optional — it works without it.

### Add a guideline

Drop a markdown file into `config/guidelines/{category}/{name}.md`. It becomes available as MCP resource `magento://guidelines/{category}/{name}` and appears in the guidelines index automatically.

### Add a skill

Create a directory `config/skills/{name}/` with a `SKILL.md` file inside. It becomes available as MCP resource `magento://skills/{name}` and appears in the skills index automatically.

A large skill can split focused topics into sub-directories: `config/skills/{name}/{topic}/SKILL.md` is served as `magento://skills/{name}/{topic}`. Keep the parent `SKILL.md` as the day-to-day core and point to sub-skills for deep dives, so agents load the focused content only when the task needs it.

An optional YAML frontmatter block (`---` delimited) at the top of any `SKILL.md` is stripped before the content is served to agents.

### Add a development-context category

Add one entry to `ContextTools::CATEGORY_MAP`:

```php
'cms' => [
    'skills' => [],
    'guidelines' => ['areas/cms'],
    'description' => 'CMS page and block development',
    'group' => 'Frontend & Admin',
],
```

The `group` key determines which heading it falls under in the generated documentation. Available groups: Hyvä Theme, Module Development, API & Integration, Frontend & Admin, System & Quality — or add a new one.

### Regenerate documentation

After making changes, regenerate agent configuration files:

```bash
vendor/bin/bricklayer update
```

## Security

### Query & Log Safety
- Database queries are read-only (SELECT only) with dangerous pattern detection
- Table names in schema queries are validated against actual database tables to prevent SQL injection
- Configurable row limits via `tools.database-query.max_rows`
- Configurable log line limits via `tools.log.max_lines`
- Sensitive configuration values (payment/\*, carriers/\*, oauth/\*, etc.) are automatically masked in query results

### Production Mode Protection
- **Destructive tools blocked by default** — `product-delete`, `category-delete`, `customer-delete`, `customer-address-delete`, `order-cancel`, and `creditmemo-create` are blocked in production mode unless explicitly enabled in `.bricklayer.json`
- **Code generation blocked** — All 4 code generation tools (`generate-module`, `generate-model`, `generate-controller`, `generate-api`) refuse to write files to production servers unless explicitly enabled
- **`code-runner` hard-blocked** — Cannot be enabled in production under any circumstances
- **Explicit override required** — Destructive tools require `"enabled": true` in `.bricklayer.json` to run in production (e.g., `"tools": {"product-delete": {"enabled": true}}`). Without a config file or without the entry, they are blocked.

### Per-Tool Configuration
- All 26 write/destructive tools can be individually enabled or disabled via `.bricklayer.json`
- Config checks are enforced via the shared `ChecksConfig` trait across all tool classes

### Code Execution Safety
- `code-runner` is read-only by default (`allow_write` must be explicitly set)
- No network access for executed code
- Code generation tools validate output paths with `realpath()` boundary checks to prevent path traversal
- File operations restricted to `app/code` and `app/design`

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting pull requests.

## License

MIT License - see [LICENSE](LICENSE) for details.

## Credits

Developed by [Inchoo](https://inchoo.net) - Magento Development Experts.

Inspired by:
- [Laravel Boost](https://laravel.com/docs/boost) - Laravel's AI toolkit
- [n98-magerun2](https://github.com/netz98/n98-magerun2) - Magento CLI tools
- [Model Context Protocol](https://modelcontextprotocol.io) - AI agent communication standard
