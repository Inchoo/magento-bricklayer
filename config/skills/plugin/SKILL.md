# Plugin Development Skill

## Overview

Plugins (interceptors) are Magento's mechanism for modifying the behavior of public class methods without modifying the original class. This skill covers creating, configuring, and optimizing plugins in Magento 2.

## When to Use Plugins

| Scenario | Use Plugin? | Alternative |
|----------|-------------|-------------|
| Modify method arguments | Yes (before) | - |
| Modify return value | Yes (after) | - |
| Add logging/timing | Yes (around) | - |
| React to state changes | No | Observer |
| Replace entire class | No | Preference |
| Add new methods | No | Preference/Decorator |

## Plugin Types

### Before Plugin

Modifies input arguments before the original method executes.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class ProductSavePlugin
{
    /**
     * Normalize product name before save
     *
     * @param ProductRepositoryInterface $subject
     * @param ProductInterface $product
     * @param bool $saveOptions
     * @return array Modified arguments
     */
    public function beforeSave(
        ProductRepositoryInterface $subject,
        ProductInterface $product,
        bool $saveOptions = false
    ): array {
        $name = $product->getName();
        if ($name !== null) {
            $product->setName(trim($name));
        }

        return [$product, $saveOptions];
    }
}
```

**Key Points:**
- Method name: `before` + PascalCase original method name
- First parameter is always `$subject` (the intercepted object)
- Remaining parameters match the original method signature
- Return an array of modified arguments (or null to keep original)

### After Plugin

Modifies the return value after the original method executes.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class ProductLoadPlugin
{
    /**
     * Add custom data after product load
     *
     * @param ProductRepositoryInterface $subject
     * @param ProductInterface $result
     * @param string $sku
     * @param bool $editMode
     * @param int|null $storeId
     * @param bool $forceReload
     * @return ProductInterface
     */
    public function afterGet(
        ProductRepositoryInterface $subject,
        ProductInterface $result,
        string $sku,
        bool $editMode = false,
        ?int $storeId = null,
        bool $forceReload = false
    ): ProductInterface {
        // Add custom extension attribute
        $extensionAttributes = $result->getExtensionAttributes();
        $extensionAttributes->setLoadedAt(date('Y-m-d H:i:s'));
        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}
```

**Key Points:**
- Method name: `after` + PascalCase original method name
- First parameter is `$subject`
- Second parameter is `$result` (original return value)
- Remaining parameters match original method signature
- Must return a value of the same type as original method

### Around Plugin

Controls the entire method execution.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Psr\Log\LoggerInterface;

class ProductSaveLoggerPlugin
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Log product save with timing
     *
     * @param ProductRepositoryInterface $subject
     * @param callable $proceed
     * @param ProductInterface $product
     * @param bool $saveOptions
     * @return ProductInterface
     */
    public function aroundSave(
        ProductRepositoryInterface $subject,
        callable $proceed,
        ProductInterface $product,
        bool $saveOptions = false
    ): ProductInterface {
        $startTime = microtime(true);
        $sku = $product->getSku();

        $this->logger->info('Product save started', ['sku' => $sku]);

        try {
            $result = $proceed($product, $saveOptions);

            $duration = microtime(true) - $startTime;
            $this->logger->info('Product save completed', [
                'sku' => $sku,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Product save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

**Key Points:**
- Method name: `around` + PascalCase original method name
- First parameter is `$subject`
- Second parameter is `$proceed` (callable to invoke original)
- Must call `$proceed()` to execute original method
- Can skip calling `$proceed()` to prevent execution (use carefully)

## Plugin Configuration

### Global Configuration (etc/di.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Catalog\Api\ProductRepositoryInterface">
        <plugin name="vendor_module_product_save"
                type="Vendor\Module\Plugin\ProductSavePlugin"
                sortOrder="10"
                disabled="false"/>
    </type>

</config>
```

### Area-Specific Configuration

```xml
<!-- etc/frontend/di.xml - Storefront only -->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Checkout\Model\Cart">
        <plugin name="vendor_module_cart_frontend"
                type="Vendor\Module\Plugin\Frontend\CartPlugin"/>
    </type>

</config>
```

```xml
<!-- etc/adminhtml/di.xml - Admin only -->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Catalog\Model\Product">
        <plugin name="vendor_module_product_admin"
                type="Vendor\Module\Plugin\Adminhtml\ProductPlugin"/>
    </type>

</config>
```

## Sort Order and Execution

When multiple plugins exist for the same method:

1. **Before plugins**: Executed in ascending sort order (10, 20, 30...)
2. **Original method**: Executed after all before plugins
3. **After plugins**: Executed in ascending sort order

```xml
<!-- Lower sortOrder = executes first -->
<plugin name="first_plugin" type="..." sortOrder="10"/>
<plugin name="second_plugin" type="..." sortOrder="20"/>
<plugin name="third_plugin" type="..." sortOrder="30"/>
```

### With Around Plugins

Around plugins create a nested execution stack:

```
around_10 starts
  around_20 starts
    before_10
    before_20
    ORIGINAL METHOD
    after_10
    after_20
  around_20 ends
around_10 ends
```

## Plugin Limitations

Plugins **CANNOT** be used on:

| Type | Reason |
|------|--------|
| Final methods | Cannot be intercepted |
| Final classes | Cannot be extended |
| Non-public methods | Only public methods are intercepted |
| Static methods | No instance context |
| `__construct` | Called before interception is set up |
| Virtual types | No class to intercept |
| Objects created without ObjectManager | Interception requires DI |

## Best Practices

### DO

1. **Prefer before/after over around** - Better performance, simpler code
2. **Keep plugins focused** - One responsibility per plugin
3. **Use descriptive plugin names** - `vendor_module_purpose`
4. **Handle exceptions properly** - Especially in around plugins
5. **Use type hints** - For all parameters and return types
6. **Test plugin behavior** - Including sort order interactions

### DON'T

1. **Don't use around for simple modifications** - Use before/after instead
2. **Don't call `$subject` methods from the same plugin** - Risk of infinite loop
3. **Don't skip `$proceed()` without clear reason** - Breaks expected behavior
4. **Don't assume plugin order** - Always use explicit sortOrder
5. **Don't create plugins for everything** - Consider observers for events

## Common Use Cases

### Validating Input

```php
/**
 * @param ProductRepositoryInterface $subject
 * @param ProductInterface $product
 * @param bool $saveOptions
 * @return array
 */
public function beforeSave(
    ProductRepositoryInterface $subject,
    ProductInterface $product,
    bool $saveOptions = false
): array {
    $sku = $product->getSku();
    if (strlen($sku) < 3) {
        throw new LocalizedException(__('SKU must be at least 3 characters'));
    }
    return [$product, $saveOptions];
}
```

### Adding Calculated Data

```php
/**
 * @param Product $subject
 * @param float $result
 * @return float
 */
public function afterGetPrice(
    Product $subject,
    float $result
): float {
    // Apply dynamic discount
    $discount = $this->discountCalculator->getDiscount($subject);
    return $result * (1 - $discount);
}
```

### Caching Results

```php
/**
 * @param ProductRepositoryInterface $subject
 * @param callable $proceed
 * @param string $sku
 * @param bool $editMode
 * @param int|null $storeId
 * @param bool $forceReload
 * @return ProductInterface
 */
public function aroundGet(
    ProductRepositoryInterface $subject,
    callable $proceed,
    string $sku,
    bool $editMode = false,
    ?int $storeId = null,
    bool $forceReload = false
): ProductInterface {
    $cacheKey = "product_{$sku}_{$storeId}";

    if (!$forceReload && $cached = $this->cache->get($cacheKey)) {
        return $cached;
    }

    $result = $proceed($sku, $editMode, $storeId, $forceReload);
    $this->cache->set($cacheKey, $result, 3600);

    return $result;
}
```

## Debugging Plugins

Use the `plugin-list` MCP tool to inspect plugins:

```
plugin-list Magento\Catalog\Api\ProductRepositoryInterface
plugin-list Magento\Catalog\Api\ProductRepositoryInterface save
```

Check di.xml compilation:
```bash
bin/magento setup:di:compile
bin/magento cache:clean
```

## Performance Considerations

1. **Around plugins add overhead** - Use only when necessary
2. **Multiple plugins compound** - Minimize chain length
3. **Avoid heavy operations in plugins** - Defer to async if possible
4. **Cache plugin results** - When computationally expensive
5. **Profile plugin execution** - Use Magento profiler or custom logging
