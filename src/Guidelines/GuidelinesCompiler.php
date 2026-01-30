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

You have access to an MCP server with 50+ tools for Magento development.
Always prefer using these tools over assumptions about the codebase.

### Introspection Tools (Use First)

Before generating or modifying code, use these tools to understand context:

| Tool | When to Use |
|------|-------------|
| `application-info` | Start of session to understand Magento version and edition |
| `module-list` | Before creating modules to check naming conflicts |
| `module-structure` | Before modifying a module to understand its layout |
| `database-schema` | Before creating models to verify table structures |
| `eav-attributes` | Before creating/modifying attributes |
| `di-configuration` | Before creating plugins to check existing interceptors |
| `route-list` | Before creating controllers to avoid route conflicts |

### Catalog Tools

| Tool | Purpose | Example Use Case |
|------|---------|------------------|
| `product-get` | Retrieve single product | Verify product exists before update |
| `product-list` | Search products | Find products matching criteria |
| `product-create` | Create new product | After verifying SKU is unique |
| `product-update` | Modify product | Update attributes or status |
| `product-stock-update` | Adjust inventory | Bulk stock adjustments |
| `category-tree` | View hierarchy | Understand category structure |

### Order Tools

| Tool | Purpose | Prerequisite |
|------|---------|--------------|
| `order-get` | Retrieve order details | None |
| `order-cancel` | Cancel order | Order must be in cancellable state |
| `invoice-create` | Create invoice | Order must be uninvoiced |
| `shipment-create` | Create shipment | Order must be invoiced (usually) |
| `creditmemo-create` | Process refund | Order must have invoice |

### Customer Tools

| Tool | Purpose | Note |
|------|---------|------|
| `customer-get` | Retrieve by ID or email | Use email for lookups |
| `customer-create` | Create account | Validates email uniqueness |
| `customer-orders` | Order history | Useful for customer service tasks |

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
