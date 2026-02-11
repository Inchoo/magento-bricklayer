# Magento Bricklayer

AI-assisted development toolkit for Magento 2. An MCP (Model Context Protocol) server that enables AI coding agents to interact with Magento installations through introspection tools, code generation capabilities, and comprehensive Magento knowledge.

## What is Bricklayer?

Bricklayer is a Composer library that implements an MCP server for Magento 2. When started, it exposes 85+ tools that AI agents can invoke to:

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

This generates configuration files for your AI agents:
- `.mcp.json` - MCP server configuration
- `CLAUDE.md` - Guidelines for Claude Code
- `.cursorrules` - Guidelines for Cursor

### 2. Start Using with Your AI Agent

The MCP server is automatically started by compatible agents. Your agent can now:

- Use `application-info` to understand your Magento installation
- Use `module-list` to see installed modules
- Use `database-schema` to inspect table structures
- Use `product-get`, `order-get`, `customer-get` for data access
- Use `development-context` to load coding guidelines for your task
- And 85+ more tools for comprehensive Magento development

## Supported AI Agents

| Agent | Configuration File | Status |
|-------|-------------------|--------|
| Claude Code | `.mcp.json` | Fully Supported |
| Cursor | `.mcp.json` + `.cursorrules` | Fully Supported |
| GitHub Copilot | `.github/copilot-instructions.md` | Supported |
| JetBrains AI (PhpStorm) | `.idea/mcp.json` | Supported |
| Gemini CLI | `.mcp.json` | Supported |

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
Updates the documentation index.

```bash
vendor/bin/bricklayer update
```

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
- `code-runner` - Execute PHP code (disabled in production)
- `search-docs` - Semantic documentation search

### Log Tools
- `log-read` - Read recent entries from log files (system, exception, debug, cron)
- `log-list` - List all available log files with sizes and modification times
- `log-analyze` - Analyze exception log for error patterns and frequency
- `log-search` - Search across all log files with pattern matching

### Code Generation Tools
- `generate-module` - Scaffold a new Magento 2 module with registration.php, module.xml, composer.json
- `generate-model` - Create model, resource model, and collection classes with db_schema.xml
- `generate-controller` - Create controller with routes.xml, layout XML, and template
- `generate-api` - Create REST API endpoint with interface, implementation, and webapi.xml

### Development Context Tool
- `development-context` - Load coding guidelines and development patterns for a task category (37 categories covering plugins, EAV, GraphQL, Hyvä, checkout, payment, testing, and more). Use category `list` to see all available categories.

## MCP Resources

Bricklayer provides several MCP resources that AI agents can access for context:

### Guidelines Resource
Comprehensive Magento development guidelines compiled from 20+ markdown documents covering:
- Architecture patterns (plugins, observers, preferences, factories, repositories)
- Coding standards (PSR-12, type hinting, strict types)
- Database patterns (declarative schema, EAV, data patches, indexers)
- Security best practices
- Testing strategies
- Frontend development (layout XML, templates, JavaScript)
- Area-specific guidance (adminhtml, frontend, webapi, graphql)
- Ecosystem guidelines (Adobe Commerce, Hyva, Mage-OS)

### Coding Standards Resource
Magento coding standards reference with PSR-12 compliance and best practices.

### Skills Resource
28 development skills for common Magento tasks:
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

### Template & Reference Resources
Code templates for common patterns and API/framework reference documentation.

## Configuration

Create `.bricklayer.json` in your Magento root:

```json
{
    "tools": {
        "code-runner": {
            "enabled": false
        },
        "database-query": {
            "enabled": true,
            "max_rows": 100
        }
    },
    "guidelines": {
        "include": ["core", "modules", "patterns"],
        "exclude": ["ecosystem/adobe-commerce"]
    },
    "agents": ["claude-code", "cursor"]
}
```

Environment variable overrides:

```bash
BRICKLAYER_MAGENTO_ROOT=/path/to/magento    # Override Magento root detection
BRICKLAYER_CODE_RUNNER_ENABLED=false        # Disable code-runner tool
BRICKLAYER_DATABASE_QUERY_MAX_ROWS=50       # Limit query results
BRICKLAYER_DEBUG=1                          # Enable debug output
```

## Architecture

Bricklayer is implemented as a standalone Composer library rather than a Magento module. This follows the proven pattern established by n98-magerun2:

- **Zero Magento footprint** - No module registration, no `setup:upgrade` required
- **Full Magento access** - Uses ObjectManager for complete framework integration
- **Easy installation** - Just `composer require`, ready to use
- **Clean removal** - Just `composer remove`, no database cleanup

## Security

- Database queries are read-only (SELECT only)
- `code-runner` is disabled in production mode
- File operations restricted to `app/code` and `app/design`
- Sensitive configuration values are masked
- No network access for executed code

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
