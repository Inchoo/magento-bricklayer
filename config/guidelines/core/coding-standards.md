# Magento 2 Coding Standards

## PHP Standards

Magento 2 follows PSR-1, PSR-2, and PSR-12 coding standards with additional Magento-specific rules.

### Key Requirements

- Use `declare(strict_types=1);` in all PHP files
- Use constructor property promotion (PHP 8.1+)
- Always type-hint method parameters and return types
- Use `readonly` properties where applicable
- Avoid `mixed` type unless absolutely necessary

### Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Classes | PascalCase | `ProductRepository` |
| Methods | camelCase | `getProductById` |
| Constants | UPPER_SNAKE_CASE | `DEFAULT_STORE_ID` |
| Variables | camelCase | `$productCollection` |
| Interfaces | PascalCase + Interface | `ProductRepositoryInterface` |
| Abstract Classes | Abstract + PascalCase | `AbstractModel` |

### File Organization

- One class per file
- Class name must match filename exactly
- Namespace must match directory structure under `app/code/` or `vendor/`

## Documentation Standards

### PHPDoc Requirements

All public methods must have PHPDoc blocks:

```php
/**
 * Get product by SKU.
 *
 * @param string $sku The product SKU
 * @param bool $editMode If true, load for editing
 * @param int|null $storeId Store ID for store-specific data
 * @return ProductInterface
 * @throws NoSuchEntityException If product doesn't exist
 * @api
 */
public function get(string $sku, bool $editMode = false, ?int $storeId = null): ProductInterface;
```

### Special Annotations

- `@api` - Mark methods that are part of the public API (service contracts)
- `@deprecated` - Mark deprecated functionality with migration path
- `@since` - Indicate version when feature was added
- `@inheritdoc` - Reference parent documentation

## Error Handling

### Exception Types

Use specific exception types:

| Exception | When to Use |
|-----------|-------------|
| `NoSuchEntityException` | Entity not found |
| `CouldNotSaveException` | Save operation failed |
| `CouldNotDeleteException` | Delete operation failed |
| `InputException` | Invalid input parameters |
| `LocalizedException` | General localized error |

### Best Practices

- Never catch generic `\Exception` unless re-throwing
- Log errors before throwing exceptions
- Provide meaningful exception messages
- Include context in exception messages

```php
throw new NoSuchEntityException(
    __('Product with SKU "%1" does not exist.', $sku)
);
```

## Code Quality

### Avoid

- God classes (too many responsibilities)
- Deep nesting (max 3 levels)
- Magic numbers (use constants)
- Hardcoded strings (use translation)
- Direct ObjectManager usage in application code

### Prefer

- Small, focused classes
- Composition over inheritance
- Dependency injection
- Interface-based design
- Clear method names that describe behavior
