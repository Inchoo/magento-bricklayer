<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Guidelines;

/**
 * Guidelines Compiler
 *
 * Generates guidelines content for AI coding agents.
 * This centralizes the guidelines generation logic used by both
 * InstallCommand and UpdateCommand.
 */
class GuidelinesCompiler
{
    /**
     * Generate guidelines content for an agent
     *
     * @param string $agent The agent name (claude-code, cursor, copilot, phpstorm, gemini)
     * @param string $envType The environment type (native, docker-compose, ddev, warden)
     * @return string The guidelines content in Markdown format
     */
    public function compile(string $agent, string $envType = 'native'): string
    {
        $timestamp = date('Y-m-d H:i:s T');
        $shellCommandsSection = $this->getShellCommandsSection($envType);

        return <<<MARKDOWN
# Magento 2 Development Guidelines

You are assisting with Magento 2 development. This project uses Magento Bricklayer
for AI-assisted development tooling.

## MCP Server: magento-bricklayer

You have access to an MCP server with 85+ tools for Magento development.
Always prefer using these tools over assumptions about the codebase.

### Development Context Tool

Before writing or generating code, call the `development-context` tool with the relevant
task category to load coding guidelines and development patterns.

| Example Category | Use Case |
|------------------|----------|
| `hyva-checkout` | Hyvä Checkout & Magewire development |
| `plugin` | Plugin (interceptor) development |
| `eav` | EAV attribute and entity development |
| `rest-api` | REST API endpoint development |
| `graphql` | GraphQL schema and resolver development |
| `model` | Model, repository, and data layer development |
| `module` | Module scaffolding and structure |
| `list` | See all 25 available categories |

### Introspection Tools (Use First)

Before generating or modifying code, use these tools to understand context:

| Tool | When to Use |
|------|-------------|
| `application-info` | Start of session to understand Magento version and edition |
| `store-configuration` | View store/website/store view hierarchy |
| `module-list` | Before creating modules to check naming conflicts |
| `module-structure` | Before modifying a module to understand its layout |
| `validate-module` | Validate module structure and configuration |
| `database-schema` | Before creating models to verify table structures |
| `eav-attributes` | Before creating/modifying attributes |
| `eav-entity-types` | List all EAV entity types in the system |
| `di-configuration` | Before creating plugins to check existing interceptors |
| `plugin-list` | List all plugins (interceptors) for a class |
| `event-list` | List events and their observers |
| `preference-list` | List all class preferences (rewrites) |
| `configuration-get` | Retrieve a system configuration value |
| `configuration-list` | List available configuration paths for a section |
| `route-list` | Before creating controllers to avoid route conflicts |
| `route-info` | Get detailed information about a specific route |
| `api-endpoints` | List all configured REST API endpoints |
| `url-rewrites` | List URL rewrites with optional filtering |

### Catalog Tools

| Tool | Purpose |
|------|---------|
| `product-get` | Retrieve product data by SKU |
| `product-list` | Search/list products with pagination |
| `product-create` | Create a new product (verify SKU is unique first) |
| `product-update` | Update an existing product |
| `product-delete` | Delete a product by SKU |
| `product-stock-get` | Get stock/inventory information |
| `product-stock-update` | Update stock quantity |
| `product-media-list` | List media gallery entries for a product |
| `product-media-add` | Add media to product gallery |
| `product-link-list` | List related/upsell/crosssell products |
| `product-link-set` | Set product links (related, upsell, crosssell) |
| `category-tree` | View category hierarchy |
| `category-get` | Retrieve category data by ID |
| `category-create` | Create a new category |
| `category-update` | Update an existing category |
| `category-delete` | Delete a category |
| `category-products` | List products in a category |
| `category-assign-products` | Assign products to a category |

### Order Tools

| Tool | Purpose | Prerequisite |
|------|---------|--------------|
| `order-get` | Retrieve order by increment ID | None |
| `order-list` | List orders with pagination/filters | None |
| `order-items` | Get line items for an order | None |
| `order-comments` | List order status history/comments | None |
| `order-add-comment` | Add comment to order history | None |
| `order-cancel` | Cancel an order | Order must be cancellable |
| `order-hold` | Place order on hold | Order must be holdable |
| `order-unhold` | Release order from hold | Order must be on hold |
| `invoice-create` | Create invoice | Order must be uninvoiced |
| `invoice-list` | List invoices with pagination | None |
| `shipment-create` | Create shipment | Order must be invoiced (usually) |
| `shipment-list` | List shipments with pagination | None |
| `shipment-track-add` | Add tracking to a shipment | Shipment must exist |
| `creditmemo-create` | Create credit memo (refund) | Order must have invoice |
| `creditmemo-list` | List credit memos with pagination | None |

### Customer Tools

| Tool | Purpose |
|------|---------|
| `customer-get` | Retrieve customer by email |
| `customer-list` | List customers with pagination |
| `customer-create` | Create a new customer account |
| `customer-update` | Update customer data |
| `customer-delete` | Delete a customer account |
| `customer-validate` | Validate customer data before create/update |
| `customer-groups-list` | List all customer groups |
| `customer-orders` | List orders for a customer |
| `customer-addresses` | List customer addresses |
| `customer-address-create` | Add address to a customer |
| `customer-address-update` | Update a customer address |
| `customer-address-delete` | Delete a customer address |

### Database & Log Tools

| Tool | Purpose |
|------|---------|
| `database-schema` | View table structure with columns, indexes, foreign keys |
| `database-query` | Execute read-only SELECT queries against the database |
| `log-read` | Read recent entries from Magento log files |
| `log-list` | List available log files with sizes |
| `log-analyze` | Analyze exception log for error patterns and frequency |
| `log-search` | Search for a pattern across all log files |

### GraphQL Tools

| Tool | Purpose |
|------|---------|
| `graphql-types` | List GraphQL schema types |
| `graphql-type-info` | Get detailed info about a specific GraphQL type |
| `graphql-queries` | List all available GraphQL queries |
| `graphql-mutations` | List all available GraphQL mutations |
| `graphql-resolvers` | List GraphQL resolvers registered for types |

### System & Development Tools

| Tool | Purpose |
|------|---------|
| `cache-status` | View cache status for all cache types |
| `indexer-status` | View status of all indexers |
| `cron-list` | List all configured cron jobs |
| `cron-history` | View recent cron job execution history |
| `deploy-mode` | View current deploy mode |
| `code-runner` | Execute PHP code in Magento context (disabled in production) |
| `search-docs` | Search Magento documentation for topics and guidance |

### Code Generation Tools

| Tool | Output |
|------|--------|
| `generate-module` | Complete module scaffold |
| `generate-model` | Model + ResourceModel + Collection |
| `generate-controller` | Controller + routes.xml + layout |
| `generate-api` | REST endpoint + webapi.xml |

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

$shellCommandsSection

---

*Generated by Magento Bricklayer v1.0.0*
*Timestamp: $timestamp*
*Agent: $agent*
MARKDOWN;
    }

    /**
     * Get the filename for an agent's configuration file
     *
     * @param string $agent The agent name
     * @return string The filename
     */
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

    /**
     * Get the shell commands section based on environment type
     *
     * @param string $envType The environment type
     * @return string The shell commands section in Markdown format
     */
    private function getShellCommandsSection(string $envType): string
    {
        $commandPrefix = match ($envType) {
            'ddev' => 'ddev exec',
            'warden' => 'warden shell -c',
            'docker-compose' => 'docker compose exec php',
            'docker' => 'docker exec -it <container>',
            default => '',
        };

        $envLabel = match ($envType) {
            'ddev' => 'DDEV',
            'warden' => 'Warden',
            'docker-compose' => 'Docker Compose',
            'docker' => 'Docker',
            default => 'Native',
        };

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

        return <<<SECTION
## Shell Commands

This project runs in a **{$envLabel}** environment. When running Magento CLI commands:

```bash
{$commandPrefix} bin/magento <command>
```

Common commands after code changes:
- `{$commandPrefix} bin/magento setup:upgrade` - Run database migrations after adding/updating modules
- `{$commandPrefix} bin/magento setup:di:compile` - Compile dependency injection (required after DI changes)
- `{$commandPrefix} bin/magento cache:clean` - Clear cache
- `{$commandPrefix} bin/magento cache:flush` - Flush cache storage
- `{$commandPrefix} bin/magento indexer:reindex` - Rebuild indexes
- `{$commandPrefix} composer install` - Install dependencies
- `{$commandPrefix} composer require <package>` - Add new dependencies
SECTION;
    }
}
