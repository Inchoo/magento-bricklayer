# Contributing

## Development Setup

### Prerequisites

- PHP 8.1+
- Composer 2.2+
- A Magento 2.4.4+ installation (for integration testing)

### Clone and Install

```bash
git clone git@gitlab.com:inchoo/magento-bricklayer.git
cd magento-bricklayer
composer install
```

### Running Tests

```bash
# Unit tests
vendor/bin/phpunit

# Static analysis (PHPStan level 8)
vendor/bin/phpstan analyse

# Code style (PSR-12)
vendor/bin/phpcs --standard=PSR12 src/
```

## Project Structure

```
src/                  # Main PHP source code
├── Bootstrap/        # Magento initialization
├── Command/          # CLI commands
├── Config/           # Configuration management
├── Exception/        # Custom exceptions
├── Guidelines/       # Guidelines compilation
├── Integration/      # Agent config writers
└── Mcp/              # MCP server + tools
    ├── Tool/         # 21 tool classes
    ├── Resource/     # 6 resource providers
    └── Prompt/       # 8 prompt providers

config/               # Auto-discovered content
├── guidelines/       # 30 markdown guidelines
└── skills/           # 28 skill directories

tests/                # PHPUnit tests
└── Unit/             # Unit test files
```

## Adding a New Tool

1. Add a method with `#[McpTool]` attribute to an existing tool class in `src/Mcp/Tool/`:

```php
#[McpTool(
    name: 'my-new-tool',
    description: 'Short description (max 80 words).',
    group: 'catalog'
)]
public function myNewTool(string $requiredParam, string $optionalParam = ''): array
{
    $this->requireMagento();
    // Implementation
    return ['result' => $data];
}
```

2. For a new tool group, create a new class in `src/Mcp/Tool/` and register it in `ToolRegistry`.

3. Add `meta: ['hidden' => true]` for tier 2 tools (discoverable via `search-tools` only).

## Adding a Guideline

Drop a markdown file in the appropriate `config/guidelines/` subdirectory:

```
config/guidelines/
├── areas/        # Area-specific (adminhtml, frontend, webapi, graphql)
├── core/         # Core patterns
├── database/     # Database patterns
├── ecosystem/    # Ecosystem-specific
├── modules/      # Module structure
└── patterns/     # Design patterns
```

Guidelines are auto-discovered — no registration needed.

## Adding a Skill

Create a directory in `config/skills/` with a `SKILL.md` file:

```
config/skills/my-new-skill/
└── SKILL.md
```

Then add the category mapping in `ContextTools::CATEGORY_MAP`.

## Adding a Development Context Category

1. Add the mapping in `src/Mcp/Tool/ContextTools.php` `CATEGORY_MAP` array
2. Ensure the guideline/skill files exist in `config/`
3. Run `vendor/bin/bricklayer update --config-only` to regenerate agent documentation

## Code Standards

- `declare(strict_types=1)` in all PHP files
- PSR-12 coding standard
- PHPStan level 8 compliance
- Type-hint all parameters and return types
- Constructor property promotion (PHP 8.1+)

## Testing

All new tools should have corresponding unit tests in `tests/Unit/Tool/`. Tests should cover:

- Happy path execution
- Error handling
- Configuration checks (enabled/disabled)
- Production safety enforcement
