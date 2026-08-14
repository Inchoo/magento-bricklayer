<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;

/**
 * Provides Magento coding standards documentation as an MCP resource.
 */
class CodingStandardsResource
{
    use FileLoaderTrait;

    /**
     * Returns Magento coding standards documentation.
     *
     * @return string Markdown content with coding standards
     */
    #[McpResource(
        uri: 'magento://standards/coding',
        name: 'coding_standards',
        description: 'Magento 2 coding standards and best practices',
        mimeType: 'text/markdown'
    )]
    public function getCodingStandards(): string
    {
        $content = $this->loadConfigFile('guidelines', 'core/coding-standards.md');

        return $this->isPlaceholder($content) ? $this->getDefaultCodingStandards() : $content;
    }

    /**
     * Returns Magento architecture guidelines.
     *
     * @return string Markdown content with architecture guidelines
     */
    #[McpResource(
        uri: 'magento://standards/architecture',
        name: 'architecture_guidelines',
        description: 'Magento 2 architecture guidelines and patterns',
        mimeType: 'text/markdown'
    )]
    public function getArchitectureGuidelines(): string
    {
        $content = $this->loadConfigFile('guidelines', 'core/architecture.md');

        return $this->isPlaceholder($content) ? $this->getDefaultArchitectureGuidelines() : $content;
    }

    /**
     * Check if content is a placeholder.
     *
     * @param string $content
     * @return bool
     */
    private function isPlaceholder(string $content): bool
    {
        return str_contains($content, 'This documentation is not yet available.');
    }

    /**
     * Get default coding standards content.
     *
     * @return string
     */
    private function getDefaultCodingStandards(): string
    {
        return <<<'MARKDOWN'
# Magento 2 Coding Standards (MCGA)

## CRITICAL: Follow These Rules Exactly

All generated PHP code MUST comply with these standards.

## File Structure

- `<?php` tag MUST be the first item (no whitespace before it)
- Use `declare(strict_types=1);` in all PHP files
- Do NOT use the closing `?>` tag in PHP-only files
- Files MUST end with exactly one newline
- One blank line after namespace declaration
- One `use` per line; one blank line after final `use` statement
- One class per file under a namespace

## Naming & Properties

- Classes: PascalCase, Methods: camelCase, Constants: UPPER_SNAKE_CASE
- Do NOT prefix properties/methods with underscore (exception: framework overrides like `_construct()`, `$_idFieldName`)
- Use visibility keywords, not `var`; one property per statement
- `static` MUST come after visibility: `public static`
- Class constants MUST have visibility declared

## Brace Placement

- Classes and methods: opening brace on NEW line
- Control structures (if, for, foreach, while): opening brace on SAME line
- Method keyword order: `final`/`abstract`, visibility, `static`

## Spacing & Formatting

- 4 spaces indentation, no tabs
- 0 spaces inside control structure parentheses
- 1 space between keyword and parenthesis, 1 space before brace
- Multi-line function calls: one arg per line, closing paren on new line
- One space after commas; spaces around `=` in defaults
- All keywords lowercase; `true`, `false`, `null` lowercase
- Always use braces on control structures
- Use `elseif` not `else if`
- Short array syntax `[]` required (not `array()`)
- No trailing whitespace, no consecutive blank lines in functions
- No multiple statements per line

## Quality Rules

- No `TODO` or `FIXME` comments
- No error suppression with `@` operator
- No deprecated functions
- Cyclomatic complexity under 20 (aim for under 10)
- Nesting level under 10 (aim for under 5)
- All function parameters must be used
- Default value arguments at end of parameter list
- Document `@throws` for exception-throwing methods

## Dependency Injection

- Never use ObjectManager directly in application code
- Inject dependencies via constructor
- Use interfaces, not concrete classes
- Use constructor property promotion (PHP 8.0+) and `readonly` (PHP 8.1+)
MARKDOWN;
    }

    /**
     * Get default architecture guidelines.
     *
     * @return string
     */
    private function getDefaultArchitectureGuidelines(): string
    {
        return <<<'MARKDOWN'
# Magento 2 Architecture Guidelines

## Service Contracts

Service contracts define the public API of a module through PHP interfaces.

### Data Interfaces

- Located in `Api/Data/`
- Define getters and setters for entity attributes
- Extend `\Magento\Framework\Api\ExtensibleDataInterface` for extension attributes
- Use `@api` annotation

### Service Interfaces

- Located in `Api/`
- Define methods for business operations
- Use data interfaces for input/output types
- Methods should be atomic and focused

## Repository Pattern

Repositories provide CRUD operations for entities.

```php
interface ProductRepositoryInterface
{
    /**
     * @param ProductInterface $product
     * @return ProductInterface
     */
    public function save(ProductInterface $product): ProductInterface;

    /**
     * @param string $sku
     * @return ProductInterface
     */
    public function get(string $sku): ProductInterface;

    /**
     * @param int $productId
     * @return ProductInterface
     */
    public function getById(int $productId): ProductInterface;

    /**
     * @param ProductInterface $product
     * @return bool
     */
    public function delete(ProductInterface $product): bool;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return ProductSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): ProductSearchResultsInterface;
}
```

## Plugins (Interceptors)

Modify method behavior without changing the original class.

### Types

- **Before plugins**: Modify input arguments
- **After plugins**: Modify return value
- **Around plugins**: Control method execution (use sparingly)

### Best Practices

- Prefer after/before over around plugins
- Keep plugin logic focused and minimal
- Don't use plugins on final methods or classes
- Consider observers for event-based extension

## Event-Observer Pattern

React to events without modifying source code.

### When to Use

- Cross-cutting concerns (logging, caching)
- Reacting to state changes
- Loose coupling between modules

### When NOT to Use

- When you need to modify return values (use plugins)
- When order of execution is critical

## Module Areas

- `global`: Applied everywhere
- `frontend`: Storefront
- `adminhtml`: Admin panel
- `webapi_rest`: REST API
- `webapi_soap`: SOAP API
- `graphql`: GraphQL API
- `crontab`: Cron execution
MARKDOWN;
    }
}
