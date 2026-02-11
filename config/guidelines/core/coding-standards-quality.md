# Magento 2 Coding Standards: Quality & Best Practices

> Related: See [coding-standards-syntax.md](coding-standards-syntax.md) for syntax, formatting, naming, and control structure rules.

## Documentation

### PHPDoc Requirements

- All public methods MUST have PHPDoc blocks
- Asterisks in doc comments MUST be aligned
- One space between asterisk and tags
- Document `@throws` for any method that throws exceptions
- Include `@param`, `@return`, and `@throws` annotations
- Add `@api` annotation for service contract methods

```php
/**
 * Get product by SKU.
 *
 * @param string $sku The product SKU
 * @param bool $editMode If true, load for editing
 * @param int|null $storeId Store ID for store-specific data
 * @return ProductInterface
 * @throws NoSuchEntityException If product does not exist
 * @api
 */
public function get(string $sku, bool $editMode = false, ?int $storeId = null): ProductInterface
{
    // implementation
}
```

### Comments

- Do NOT leave `TODO` comments in code - resolve them before committing
- Do NOT leave `FIXME` comments in code - resolve them before committing
- Comments should explain "why", not "what"

## PHP Features

### Constructor Property Promotion (PHP 8.0+)

Use constructor property promotion to reduce boilerplate:

```php
class ProductService
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger
    ) {
    }
}
```

### Readonly Properties (PHP 8.1+)

Use `readonly` for properties that should not change after construction:

```php
class OrderProcessor
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceServiceInterface $invoiceService
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceServiceInterface $invoiceService
    ) {
    }
}
```

### Type Hints

- Always type-hint method parameters and return types
- Use union types and nullable types where appropriate
- Avoid `mixed` type unless absolutely necessary

## Code Quality

### Complexity Limits

- Cyclomatic complexity MUST NOT exceed 20 per function (aim for under 10)
- Nesting level MUST NOT exceed 10 (aim for under 5)

### Forbidden Patterns

- Do NOT suppress errors with the `@` operator
- Do NOT use deprecated PHP functions
- Do NOT use call-time pass-by-reference
- Do NOT use unconditional if statements (`if (true)`, `if (false)`)
- Do NOT prefix functions with double underscore `__` (reserved for PHP magic methods)
- Do NOT define useless overriding methods that only call the parent
- All function parameters MUST be used within the function
- Use different incrementer variables in nested loops

### Arguments with Default Values

Arguments with default values MUST go at the end of the argument list:

```php
// CORRECT
/**
 * @param string $dsn
 * @param bool $persistent
 * @return void
 */
public function connect(string $dsn, bool $persistent = false): void
{
    // body
}

// WRONG
public function connect(bool $persistent = false, string $dsn): void
{
    // body
}
```

### Function Closing Brace

The closing brace of a function MUST follow directly after the body (no blank line):

```php
// CORRECT
/**
 * @return void
 */
public function process(): void
{
    $this->doSomething();
}

// WRONG - blank line before closing brace
public function process(): void
{
    $this->doSomething();

}
```

## Dependency Injection

- Never use ObjectManager directly in application code
- Inject dependencies via constructor
- Use interfaces for dependencies, not concrete classes
- Define preferences in `di.xml` for interface implementations

## Error Handling

### Exception Types

| Exception | When to Use |
|-----------|-------------|
| `NoSuchEntityException` | Entity not found |
| `CouldNotSaveException` | Save operation failed |
| `CouldNotDeleteException` | Delete operation failed |
| `InputException` | Invalid input parameters |
| `LocalizedException` | General localized error |

### Exception Example

```php
/**
 * @param int $id
 * @return EntityInterface
 * @throws NoSuchEntityException
 */
public function getById(int $id): EntityInterface
{
    $entity = $this->entityFactory->create();
    $this->resource->load($entity, $id);

    if (!$entity->getId()) {
        throw new NoSuchEntityException(
            __('Entity with ID "%1" does not exist.', $id)
        );
    }

    return $entity;
}
```

## Magento Framework Property Exceptions

Some Magento framework properties require underscore-prefixed names. These are the ONLY acceptable exceptions to the "no underscore prefix" rule:

- `$_eventPrefix` - Event prefix for model events
- `$_eventObject` - Event object name for model events
- `$_idFieldName` - Primary key field name for collections
- `_construct()` - Magento's internal initialization method (not `__construct`)
- `_init()` - Resource model initialization method

When using these framework properties, add a visibility keyword and type hint where possible:

```php
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected string $_idFieldName = 'entity_id';

    /**
     * @var string
     */
    protected string $_eventPrefix = 'vendor_module_entity_collection';

    /**
     * @var string
     */
    protected string $_eventObject = 'entity_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Entity::class, EntityResource::class);
    }
}
```
