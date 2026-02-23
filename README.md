# Magento Bricklayer

AI-assisted development toolkit for Magento 2. An MCP (Model Context Protocol) server that enables AI coding agents to interact with Magento installations through introspection tools, code generation capabilities, and comprehensive Magento knowledge.

## What is Bricklayer?

Bricklayer is a Composer library that implements an MCP server for Magento 2. When started, it exposes vast number of tools that AI agents can invoke to:

- Inspect modules, configuration, and database schema
- Query EAV attributes and entity types
- Manage products, orders, and customers
- Generate Magento-compliant code
- Search Magento documentation

The name "Bricklayer" reflects the methodical, structured approach to building Magento 2 modules and extensions, laying each component (the "bricks") in the correct order and position to construct a solid, maintainable codebase.

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
- `--agents=AGENTS` - Comma-separated list of agents to configure (claude-code, cursor, phpstorm, copilot, gemini)
- `--force` - Overwrite existing configuration files

### init
Generates `.bricklayer.json` configuration file with deploy-mode-aware defaults.

```bash
vendor/bin/bricklayer init [options]
```

Options:
- `--magento-root=PATH` - Specify Magento root directory (auto-detected by default)
- `--force` - Overwrite existing `.bricklayer.json`

The generated config varies by deploy mode:
- **production** — Destructive tools and code generation disabled, code-runner disabled, query limits reduced
- **developer/default** — All tools enabled, code-runner read-only

This command is also called automatically during `bricklayer install`. Additionally, `bricklayer verify` will auto-generate the file if it's missing.

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
Regenerates agent configuration files (CLAUDE.md, .cursorrules, etc.) and documentation index.

```bash
vendor/bin/bricklayer update [options]
```

Options:
- `--config-only` - Only regenerate configuration files (CLAUDE.md, .cursorrules, etc.)
- `--docs-only` - Only update documentation index
- `--magento-root=PATH` - Specify Magento root directory

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

### Application & Store Tools
- `application-info` - Magento version, PHP version, deploy mode, module counts
- `store-configuration` - Website/store/store view hierarchy with locale and base URLs
- `deploy-mode` - Current deploy mode with recommendations

### Module Tools
- `module-list` - All installed modules with version, status, and vendor
- `module-structure` - File/folder structure of a module with dependencies
- `validate-module` - Validates module code structure (registration.php, module.xml, composer.json, strict_types)

### Database Tools
- `database-schema` - Table structures, columns, indexes, foreign keys
- `database-query` - Execute read-only SELECT queries with automatic LIMIT enforcement

### EAV Tools
- `eav-attributes` - EAV attributes for entity types (catalog_product, catalog_category, customer, customer_address)
- `eav-entity-types` - List all supported EAV entity types

### Configuration & DI Tools
- `configuration-get` - Retrieve system configuration values by path (with scope support)
- `configuration-list` - List available configuration paths by section
- `di-configuration` - DI configuration showing preferences and plugins for classes
- `plugin-list` - List all plugins/interceptors for a class with method filtering
- `event-list` - List events and observers with area filtering
- `preference-list` - List all class preference rewrites

### Routing & API Tools
- `route-list` - Frontend and admin routes with handling modules
- `route-info` - Detailed information about specific routes with controller classes
- `api-endpoints` - REST API endpoints with method/path filtering and security info
- `url-rewrites` - URL rewrites with request path and store filtering

### GraphQL Tools
- `graphql-types` - List all GraphQL types in the schema
- `graphql-type-info` - Detailed information about a specific GraphQL type
- `graphql-queries` - List all available GraphQL queries
- `graphql-mutations` - List all available GraphQL mutations
- `graphql-resolvers` - List GraphQL resolvers with their implementation classes

### Catalog Tools
- `product-get`, `product-list`, `product-create`, `product-update`, `product-delete`
- `product-stock-get`, `product-stock-update`
- `product-media-list` - List product media gallery entries
- `product-media-add` - Add images to product gallery (base64 or file path)
- `product-link-list` - List related, upsell, or crosssell products
- `product-link-set` - Set product relationships
- `category-get`, `category-tree`, `category-create`, `category-update`, `category-delete`
- `category-products` - List products assigned to category
- `category-assign-products` - Assign products to category with position ordering

### Order Tools
- `order-get`, `order-list`, `order-cancel`, `order-hold`, `order-unhold`
- `order-items` - List line items with quantities, prices, discounts, taxes
- `order-comments` - List order status history and comments
- `order-add-comment` - Add comment to order with optional status change and notification
- `invoice-create`, `invoice-list`
- `shipment-create`, `shipment-list`
- `shipment-track-add` - Add tracking information to shipment
- `creditmemo-create`, `creditmemo-list`

### Customer Tools
- `customer-get`, `customer-list`, `customer-create`, `customer-update`, `customer-delete`
- `customer-validate` - Validate customer data before create/update
- `customer-groups-list` - List all customer groups with tax class IDs
- `customer-orders` - List orders for specific customer
- `customer-addresses` - List all addresses for customer
- `customer-address-create`, `customer-address-update`, `customer-address-delete`

### Development Tools
- `cache-status` - Status of all cache types with enabled/disabled counts
- `indexer-status` - Status of all indexers (valid/invalid/processing, mode)
- `cron-list` - Configured cron jobs with schedule expressions
- `cron-history` - Recent cron execution history with optional job code filtering
- `code-runner` - Execute PHP code in Magento context with helper functions, area emulation, read-only mode, and metrics
- `code-runner-help` - Returns detailed code-runner usage guide with helpers, variables, areas, and examples
- `search-docs` - Semantic documentation search
- `search-tools` - Search available MCP tools by keyword or group with configurable detail level
- `batch-execute` - Execute multiple tool operations in a single call (max 20)

### Log Tools
- `log-read` - Read recent entries from log files (system, exception, debug, cron)
- `log-list` - List all available log files with sizes and modification times
- `log-analyze` - Analyze exception log for error patterns and frequency
- `log-search` - Search across all log files with pattern matching

### Diagnostic Tools
- `diagnose-error` - Diagnoses the most recent Magento error with full context, DI analysis, and actionable fix suggestions in a single call

The `diagnose-error` tool orchestrates multiple introspection tools to produce a comprehensive diagnosis:

```
diagnose-error(index=0, source="exception", since="1h", pattern="")
```

**Parameters:**
| Parameter | Default | Description |
|-----------|---------|-------------|
| `index` | `0` | Which error to diagnose (0 = most recent) |
| `source` | `exception` | Log file: exception, system, debug, cron |
| `since` | `1h` | Time window: 5m, 1h, 24h, 7d |
| `pattern` | `""` | Substring filter for error messages |

**Response structure:**
- `error` - Parsed exception with class, message, file, line, stack trace, and chained previous exception
- `module_context` - Responsible module's name, version, enabled status, dependencies, and validation issues
- `di_context` - DI preferences and plugins for the error class
- `environment` - Deploy mode, disabled caches, invalid indexers, generated code age
- `history` - Error frequency and top exception types in the time period
- `suggestions` - Actionable fixes with confidence levels (high/medium/low) and CLI commands

The tool recognizes 15 common Magento error patterns including class-not-found, DI compilation errors, database issues, search engine failures, invalid templates/blocks, memory exhaustion, and session errors.

### Code Runner Tool
- `code-runner` - Execute PHP code within the Magento application context with helper functions, area emulation, transaction rollback, and execution metrics

```
code-runner(code, area="", allow_write=false, timeout=30)
```

**Parameters:**
| Parameter | Default | Description |
|-----------|---------|-------------|
| `code` | (required) | PHP code to execute (without `<?php` tags) |
| `area` | `""` | Magento area for DI resolution: frontend, adminhtml, webapi_rest, graphql, crontab, global |
| `allow_write` | `false` | When false, DB changes are rolled back after execution |
| `timeout` | `30` | Maximum execution time in seconds |

**Helper functions** available in executed code:
- `$get(ClassName::class)` - Retrieve singleton from DI container
- `$create(ClassName::class, ['arg' => val])` - Create new instance
- `$repo(RepositoryInterface::class)` - Alias for `$get()`, semantic sugar for repositories
- `$config('section/group/field')` - Read system configuration value
- `query('SELECT ...', $binds)` - Execute a read-only SELECT query, returns array of rows
- `runLog($value, 'label')` - Capture values to return in the response `log` key

**Response includes:**
- `success`, `output`, `return`, `error` - Standard execution result
- `read_only` - Whether DB changes were rolled back
- `area` - Effective area code (if specified)
- `log` - Values captured via `runLog()` helper
- `metrics` - Execution time (ms), memory delta (MB), peak memory (MB), queries executed

The tool validates code against 9 dangerous patterns (shell execution, file writes, superglobals, cURL, eval, header manipulation, global handler registration, long sleeps). Disabled in production mode and configurable via `.bricklayer.json`.

### Code Generation Tools
- `generate-module` - Scaffold a new Magento 2 module with registration.php, module.xml, composer.json
- `generate-model` - Create model, resource model, and collection classes with db_schema.xml
- `generate-controller` - Create controller with routes.xml, layout XML, and template
- `generate-api` - Create REST API endpoint with interface, implementation, and webapi.xml

All code generation tools support `dry_run` (preview without writing) and `force` (overwrite existing files) parameters. In dry-run mode, each file is annotated with `new` or `exists` status. Without `force`, existing files cause a conflict error listing the affected paths.

### Development Context Tool
- `development-context` - Load coding guidelines and development patterns for a task category (37 categories covering plugins, EAV, GraphQL, Hyvä, checkout, payment, testing, and more). Use category `list` to see all available categories.

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
27 development skills accessed via URI template `magento://skills/{name}`, covering:
- Checkout customization (steps & layout processors, config providers & validation)
- Cron job development
- EAV attribute development
- GraphQL API development
- Hyvä Checkout (Magewire components, XML configuration, evaluation & form APIs)
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
Code templates for common patterns (`magento://templates/module`, `magento://templates/controller`, `magento://templates/api`, `magento://templates/model`) and reference documentation (`magento://reference/events`, `magento://reference/layouts`, `magento://reference/di-patterns`, `magento://reference/acl`).

## Configuration

Create `.bricklayer.json` in your Magento root:

```json
{
    "tools": {
        "code-runner": {
            "enabled": true,
            "allow_write": false,
            "max_timeout": 30
        },
        "database-query": {
            "enabled": true,
            "max_rows": 100
        },
        "log-reader": {
            "enabled": true,
            "max_lines": 500
        },
        "product-delete": { "enabled": false },
        "customer-delete": { "enabled": false },
        "category-delete": { "enabled": false }
    },
    "guidelines": {
        "include": ["core", "modules", "patterns"],
        "exclude": ["ecosystem/adobe-commerce"]
    },
    "agents": ["claude-code", "cursor"]
}
```

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

Destructive tools (`product-delete`, `category-delete`, `customer-delete`, `customer-address-delete`, `order-cancel`, `creditmemo-create`, and all 4 code generation tools) are **blocked by default in production mode**. They must be explicitly enabled in `.bricklayer.json` with `"enabled": true` to work in production.

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

```bash
BRICKLAYER_MAGENTO_ROOT=/path/to/magento    # Override Magento root detection
BRICKLAYER_CODE_RUNNER_ENABLED=false        # Disable code-runner tool
BRICKLAYER_CODE_RUNNER_ALLOW_WRITE=false    # Enforce read-only mode globally
BRICKLAYER_CODE_RUNNER_MAX_TIMEOUT=30       # Maximum timeout in seconds
BRICKLAYER_DATABASE_QUERY_MAX_ROWS=50       # Limit query results
BRICKLAYER_DEBUG=1                          # Enable debug output
```

## Architecture

Bricklayer is implemented as a standalone Composer library rather than a Magento module. This follows the proven pattern established by n98-magerun2:

- **Zero Magento footprint** - No module registration, no `setup:upgrade` required
- **Full Magento access** - Uses ObjectManager for complete framework integration
- **Easy installation** - Just `composer require`, ready to use
- **Clean removal** - Just `composer remove`, no database cleanup

## Extending Bricklayer

Bricklayer uses auto-discovery for tools, guidelines, skills, and categories. Adding new capabilities requires editing only the source file — documentation (CLAUDE.md, .cursorrules, etc.) and MCP resource indexes are regenerated automatically when you run `vendor/bin/bricklayer update --config-only`.

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
vendor/bin/bricklayer update --config-only
```

## Security

### Query & Log Safety
- Database queries are read-only (SELECT only) with dangerous pattern detection
- Table names in schema queries are validated against actual database tables to prevent SQL injection
- Configurable row limits via `tools.database-query.max_rows`
- Configurable log line limits via `tools.log-reader.max_lines`
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
