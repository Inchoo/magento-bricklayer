# Magento 2 Coding Standards (MCGA)

## CRITICAL: Follow These Rules Exactly

All generated PHP code MUST comply with these standards. Violations will be flagged by the MCGA phpcs ruleset. These rules are NON-NEGOTIABLE for all PHP files.

## File Structure

### Opening Tag & Strict Types

- The opening `<?php` tag MUST be the first item in the file (no whitespace before it)
- Use `declare(strict_types=1);` in all PHP files immediately after the opening tag
- Do NOT use the closing `?>` tag in PHP-only files
- Files MUST end with exactly one newline character
- Do NOT use short open tags `<? ?>` or ASP-style tags `<% %>`
- No Byte Order Marks (BOM)

### Namespace & Use Declarations

- There MUST be one blank line after the namespace declaration
- Each `use` declaration MUST contain only one namespace
- `use` statements MUST come after the first namespace declaration
- There MUST be one blank line after the final `use` statement

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Module\Api\Data\EntityInterface;

class Entity extends AbstractModel implements EntityInterface
{
```

### One Class Per File

- Each class MUST be in a file by itself under a namespace
- A PHP file MUST either contain declarations with no side effects, or logic with no declarations (exception: `registration.php`)

## Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Classes | PascalCase | `ProductRepository` |
| Methods | camelCase | `getProductById` |
| Constants | UPPER_SNAKE_CASE | `DEFAULT_STORE_ID` |
| Variables | camelCase | `$productCollection` |
| Interfaces | PascalCase + Interface | `ProductRepositoryInterface` |
| Abstract Classes | Abstract + PascalCase | `AbstractModel` |

### Method Names

- Method names MUST be declared in camelCase
- Do NOT prefix method names with an underscore to indicate visibility
- Exception: Magento framework override methods like `_construct()`, `_init()` are required by the framework

### Property Names

- Do NOT prefix property names with an underscore to indicate visibility
- Exception: Magento framework properties like `$_eventPrefix`, `$_eventObject`, `$_idFieldName` are required by the framework and cannot be renamed
- Use visibility keywords (`public`, `private`, `protected`) instead of `var`
- Declare only one property per statement
- The `static` keyword MUST come after the visibility declaration

```php
// CORRECT
class Example
{
    /**
     * @var string
     */
    public static string $config = '';

    /**
     * @var int
     */
    private int $count = 0;

    /**
     * @var string
     */
    protected string $name = '';
}

// WRONG - do NOT do this
class Example
{
    private $_count = 0;        // underscore prefix
    var $name = '';              // var keyword
    private $bar, $baz;         // multiple per statement
    static protected $config;   // static before visibility
}
```

### Constants

- Constants MUST be all-uppercase with underscores separating words
- Class constants MUST have visibility declared (PHP 7.1+)

```php
class Config
{
    public const MAX_RETRIES = 3;
    private const CACHE_KEY = 'custom_cache';
    protected const DEFAULT_STATUS = 1;
}
```

## Brace Placement

### Classes and Methods: Opening Brace on NEW LINE

The opening brace for classes and methods goes on the line AFTER the declaration:

```php
class ProductRepository
{
    /**
     * @param int $id
     * @return ProductInterface
     */
    public function getById(int $id): ProductInterface
    {
        // method body
    }
}
```

### Control Structures: Opening Brace on SAME LINE

The opening brace for control structures goes at the END of the same line:

```php
if ($condition) {
    // body
}

foreach ($items as $item) {
    // body
}

while ($condition) {
    // body
}
```

### Method Declaration Order

The keyword order for methods MUST be: `final`/`abstract`, then visibility, then `static`:

```php
/**
 * @return self
 */
final public static function getInstance(): self
{
    // body
}

/**
 * @return void
 */
abstract protected function processData(): void;
```

## Spacing Rules

### Control Structure Spacing

- 0 spaces after opening parenthesis, 0 spaces before closing parenthesis
- 1 space between keyword and opening parenthesis
- 1 space before opening brace

```php
// CORRECT
if ($foo) {
    $var = 1;
}

// WRONG
if ( $foo ) {     // spaces inside parentheses
    $var = 1;
}

if($foo){         // no space before parenthesis or brace
    $var = 1;
}
```

### Function Call Spacing

- No space inside parentheses for function calls
- For multi-line calls: one argument per line, 4-space indent, closing parenthesis on new line, first argument on new line

```php
// Single line
$result = foo($bar, $baz);

// Multi-line
$result = $this->repository->getList(
    $searchCriteria,
    $storeId,
    $includeInactive
);
```

### Function Argument Spacing

- One space after each comma in argument lists
- Single spaces surrounding equals sign for default values

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
public function connect(string $dsn,bool $persistent=false): void
{
    // body
}
```

### Keyword Spacing

- `static`, `public`, `private`, `protected` MUST have exactly one space after them
- All PHP keywords MUST be lowercase: `if`, `else`, `elseif`, `foreach`, `for`, `do`, `switch`, `while`, `try`, `catch`, `function`, `public`, `private`, `protected`, `static`
- The constants `true`, `false`, `null` MUST be lowercase

## Control Structures

### Always Use Braces

Control structures MUST use braces, even for single-line bodies:

```php
// CORRECT
if ($test) {
    $var = 1;
}

// WRONG - no braces
if ($test)
    $var = 1;
```

### Use `elseif` Not `else if`

```php
// CORRECT
if ($foo) {
    $var = 1;
} elseif ($bar) {
    $var = 2;
}

// WRONG
if ($foo) {
    $var = 1;
} else if ($bar) {
    $var = 2;
}
```

### Switch Statements

- `case` indented 4 spaces from `switch`
- `break` indented 4 more spaces from `case`
- MUST include a `// no break` comment for intentional fall-through

```php
switch ($value) {
    case 'option1':
        $result = handleOption1();
        break;
    case 'option2':
        // no break
    case 'option3':
        $result = handleOptions2And3();
        break;
    default:
        $result = handleDefault();
        break;
}
```

### Foreach Loops

- Space between each element and `as` keyword (lowercase)

```php
foreach ($items as $key => $value) {
    processItem($key, $value);
}
```

### For Loops

- No space inside brackets
- 0 spaces before semicolons, 1 space after semicolons
- Do NOT call functions in the test expression; compute beforehand

```php
// CORRECT
$count = count($items);
for ($i = 0; $i < $count; $i++) {
    echo $items[$i];
}

// WRONG - function call in test
for ($i = 0; $i < count($items); $i++) {
    echo $items[$i];
}
```

## Array Syntax

- Short array syntax `[]` MUST be used (not `array()`)

```php
// CORRECT
$items = [
    'foo' => 'bar',
    'baz' => 'qux',
];

// WRONG
$items = array(
    'foo' => 'bar',
    'baz' => 'qux',
);
```

## Indentation

- Use 4 spaces per indentation level
- Do NOT use tabs
- Closing brace indentation MUST match the line containing the opening brace
- Closing brace MUST be on a line by itself

## Line Formatting

- Lines SHOULD be approximately 80 characters for readability
- No trailing whitespace at end of lines
- No consecutive blank lines within functions
- No superfluous whitespace at start of file
- Unix-style line endings (`\n` not `\r\n`)
- No multiple statements on a single line

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
