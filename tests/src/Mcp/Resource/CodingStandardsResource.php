<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;

/**
 * Coding Standards Resource
 *
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
# Magento 2 Coding Standards

## PHP Standards

Magento 2 follows PSR-1, PSR-2, and PSR-12 coding standards with additional Magento-specific rules.

### Key Requirements

- Use `declare(strict_types=1);` in all PHP files
- Use constructor property promotion (PHP 8.1+)
- Always type-hint method parameters and return types
- Use `readonly` properties where applicable

### Naming Conventions

- Classes: PascalCase (e.g., `ProductRepository`)
- Methods: camelCase (e.g., `getProductById`)
- Constants: UPPER_SNAKE_CASE (e.g., `DEFAULT_STORE_ID`)
- Variables: camelCase (e.g., `$productCollection`)

### File Organization

- One class per file
- Class name must match filename
- Namespace must match directory structure

## Documentation

- All public methods must have PHPDoc blocks
- Include `@param`, `@return`, and `@throws` annotations
- Add `@api` annotation for service contract methods
- Add `@deprecated` for deprecated functionality

## Copyright Headers

All PHP files must include a copyright header:

```php
<?php
/**
 * Copyright © Vendor Name. All rights reserved.
 * See LICENSE.txt for license details.
 */
```

## Dependency Injection

- Never use ObjectManager directly in application code
- Inject dependencies via constructor
- Use interfaces for dependencies, not concrete classes
- Define preferences in di.xml for interface implementations

## Error Handling

- Use specific exception types (NoSuchEntityException, LocalizedException)
- Never catch generic \Exception unless re-throwing
- Log errors before throwing exceptions
- Provide meaningful exception messages
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
    public function save(ProductInterface $product): ProductInterface;
    public function get(string $sku): ProductInterface;
    public function getById(int $productId): ProductInterface;
    public function delete(ProductInterface $product): bool;
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
