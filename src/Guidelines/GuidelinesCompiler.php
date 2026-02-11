<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Guidelines;

class GuidelinesCompiler
{
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

| Category | Description |
|----------|-------------|
| **Hyvä Theme** | |
| `hyva-theme` | Hyvä theme setup and Alpine.js CSP components |
| `hyva-theme-advanced` | Hyvä ViewModels, module compatibility, and customization |
| `hyva-ui-component` | Hyvä UI component CSS and design system |
| `hyva-ui-component-js` | Hyvä UI component Alpine.js and interactivity |
| `hyva-checkout` | Hyvä Checkout Magewire component development |
| `hyva-checkout-config` | Hyvä Checkout XML configuration and layout |
| `hyva-checkout-api` | Hyvä Checkout evaluation, form, and frontend APIs |
| **Module Development** | |
| `module` | Module scaffolding and structure |
| `model` | Model, repository, and data layer development |
| `plugin` | Plugin (interceptor) development |
| `observer` | Event observer development |
| `preference` | Class preference (rewrite) development |
| `eav` | EAV attribute and entity development |
| `data-patch` | Data and schema patch development |
| **API & Integration** | |
| `rest-api` | REST API endpoint development |
| `graphql` | GraphQL schema and resolver development |
| `payment` | Payment method module setup and configuration |
| `payment-gateway` | Payment gateway components (builders, handlers, validators) |
| `payment-checkout` | Payment checkout integration and frontend |
| `shipping` | Shipping carrier integration |
| `message-queue` | Message queue and async processing |
| `import` | Custom import entity development |
| `export` | Custom export entity development |
| **Frontend & Admin** | |
| `frontend` | Frontend development (layout, templates, JS) |
| `theme` | Theme structure, layout XML, and templates |
| `theme-styling` | Theme LESS/CSS styling and JavaScript |
| `checkout` | Checkout custom steps and layout processors |
| `checkout-advanced` | Checkout config providers, mixins, and validation |
| `adminhtml` | Admin panel development |
| `ui-component` | Admin UI component grids |
| `ui-component-form` | Admin UI component forms |
| **System & Quality** | |
| `cron` | Cron job development |
| `indexer` | Custom indexer development |
| `testing` | Unit, integration, and API testing |
| `coding-standards` | PHP coding standards, syntax, formatting, and quality rules |
| `security` | Security best practices and guidelines |
| `performance` | Performance optimization guidelines |
| `list` | See all categories with skill/guideline counts |

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
| `diagnose-error` | Diagnose recent Magento errors with full context, DI analysis, and fix suggestions |
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
            'hooli' => 'Hooli',
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

        if ($envType === 'hooli') {
            return <<<'SECTION'
## Shell Commands

This project runs in a **Hooli** environment. When running Magento CLI commands:

```bash
../hooli console --run "bin/magento <command>"
```

Common commands after code changes:
- `../hooli console --run "bin/magento setup:upgrade"` - Run database migrations after adding/updating modules
- `../hooli console --run "bin/magento setup:di:compile"` - Compile dependency injection (required after DI changes)
- `../hooli console --run "bin/magento cache:clean"` - Clear cache
- `../hooli console --run "bin/magento cache:flush"` - Flush cache storage
- `../hooli console --run "bin/magento indexer:reindex"` - Rebuild indexes
- `../hooli console --run "composer install"` - Install dependencies
- `../hooli console --run "composer require <package>"` - Add new dependencies
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
