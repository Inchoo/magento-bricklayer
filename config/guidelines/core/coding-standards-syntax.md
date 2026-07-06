# Magento 2 Coding Standards: Syntax & Formatting

> Related: See [coding-standards-quality.md](coding-standards-quality.md) for documentation, code quality, error handling, and DI guidelines.

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

## Template Files (.phtml)

`.phtml` templates are PHP files and MUST follow every rule above, plus a fixed
header structure. This applies to **both Luma and Hyvä** templates — the header
and escaping approach are identical across themes for consistency.

Every template MUST open with these three blocks, in this EXACT order:

1. `declare(strict_types=1);` — immediately after the opening `<?php` tag
2. All `use` statements — import every class referenced in the `@var` annotations and body
3. All `@var` annotations — one per line, typed via the imported short class names

```php
<?php

declare(strict_types=1);

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;

/** @var Template $block */
/** @var Escaper $escaper */
?>
<div class="example">
    <h1><?= $escaper->escapeHtml(__('Title')) ?></h1>
</div>
```

### Output Escaping

Every echoed value falls into exactly ONE of three cases. Pick the right one — do
not escape blindly, and do not annotate blindly.

**Case 1 — value needs escaping.** Any string that may contain user or store data.
Wrap it in the `$escaper` object Magento injects into every template. Prefer
`$escaper->escape*()` over the legacy `$block->escape*()` / `$this->escape*()`
helpers so escaping is identical across Luma and Hyvä. Match the method to the
output context:

| Method | Use for |
|--------|---------|
| `$escaper->escapeHtml($v)` | Text content inside HTML |
| `$escaper->escapeHtmlAttr($v)` | HTML attribute values |
| `$escaper->escapeUrl($v)` | URLs (`href`, `src`) |
| `$escaper->escapeJs($v)` | Values inside inline `<script>` |
| `$escaper->escapeCss($v)` | Values inside inline styles |

```php
<h1><?= $escaper->escapeHtml($block->getTitle()) ?></h1>
<a href="<?= $escaper->escapeUrl($product->getProductUrl()) ?>">…</a>
```

**Case 2 — already-safe HTML from a self-describing expression.** A call whose name
ends in `Html` (`getChildHtml()`, `getProductDetailsHtml()`, `toHtml()`, an icon
`->…Html()`), or a value guarded by an `(int)`/`(float)`/`(bool)` cast. Output it
directly with NO `$escaper` call and NO comment — the Magento phpcs sniff already
treats these as safe, so a marker would only add noise:

```php
<?= $block->getChildHtml('child.block') ?>
<?= $lucideIcons->shoppingCartHtml('', 24, 24) ?>
<?= (int) $product->getId() ?>
```

**Case 3 — safe output the sniff cannot recognise.** A value that is genuinely safe
but is NOT escaped, NOT `*Html`-suffixed, and NOT cast: a non-`Html` method that
returns markup (`getProductPrice()`, `formatPrice()`), a ViewModel object rendered
via `__toString()`, or a variable already holding rendered HTML. Mark it with an
explicit single-star `/* @noEscape */` comment (NOT the docblock `/** @noEscape */`,
which the sniff does not recognise) so phpcs passes and the intent stays auditable:

```php
<?= /* @noEscape */ $block->getProductPrice($product) ?>
<?= /* @noEscape */ $modal ?>
<?= /* @noEscape */ $safeHtml ?>
```

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
