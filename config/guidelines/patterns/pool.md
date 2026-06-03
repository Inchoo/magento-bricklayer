# Pool Pattern in Magento 2

## Overview

The Pool pattern provides a DI-configured registry of strategy objects, resolved by a string key at runtime. It replaces if/else or switch blocks that branch on a type identifier (e.g., product type, payment method, format code) with an extensible, DI-populated array of implementations.

Magento core uses this pattern extensively: `Magento\Payment\Gateway\Command\CommandPool`, `Magento\Payment\Gateway\Config\ValueHandlerPool`, `Magento\Framework\View\Element\Block\ArgumentPool`.

## When to Use

| Scenario | Use Pool? |
|----------|:---------:|
| Different logic per product type (simple, configurable, bundle) | Yes |
| Different logic per payment method or shipping carrier | Yes |
| Multiple format renderers (CSV, XML, JSON) | Yes |
| Multiple upload adapters (FTP, SFTP, S3) | Yes |
| Simple conditional with 2 branches unlikely to grow | No |

## Implementation

### 1. Define the Strategy Interface

Each strategy must implement a shared interface:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Cart;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;

interface AddToCartProcessorInterface
{
    public function prepareRequest(
        ProductInterface $product,
        array $itemOptions,
        float $qty
    ): DataObject;
}
```

### 2. Implement Concrete Strategies

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Cart\Processor;

use Vendor\Module\Model\Cart\AddToCartProcessorInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;

class SimpleProcessor implements AddToCartProcessorInterface
{
    public function prepareRequest(
        ProductInterface $product,
        array $itemOptions,
        float $qty
    ): DataObject {
        return new DataObject([
            'product' => $product->getId(),
            'qty' => $qty,
        ]);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Cart\Processor;

use Vendor\Module\Model\Cart\AddToCartProcessorInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;

class ConfigurableProcessor implements AddToCartProcessorInterface
{
    public function prepareRequest(
        ProductInterface $product,
        array $itemOptions,
        float $qty
    ): DataObject {
        return new DataObject([
            'product' => $product->getId(),
            'qty' => $qty,
            'super_attribute' => $itemOptions['super_attribute'] ?? [],
        ]);
    }
}
```

### 3. Create the Pool Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Cart;

use Magento\Framework\Exception\LocalizedException;

class AddToCartProcessorPool
{
    /**
     * @param AddToCartProcessorInterface[] $processors
     */
    public function __construct(
        private readonly array $processors = []
    ) {
    }

    public function get(string $productType): AddToCartProcessorInterface
    {
        if (!isset($this->processors[$productType])) {
            throw new LocalizedException(
                __('No add-to-cart processor registered for product type "%1".', $productType)
            );
        }

        return $this->processors[$productType];
    }

    public function has(string $productType): bool
    {
        return isset($this->processors[$productType]);
    }
}
```

### 4. Register Strategies in di.xml

```xml
<type name="Vendor\Module\Model\Cart\AddToCartProcessorPool">
    <arguments>
        <argument name="processors" xsi:type="array">
            <item name="simple" xsi:type="object">Vendor\Module\Model\Cart\Processor\SimpleProcessor</item>
            <item name="virtual" xsi:type="object">Vendor\Module\Model\Cart\Processor\SimpleProcessor</item>
            <item name="configurable" xsi:type="object">Vendor\Module\Model\Cart\Processor\ConfigurableProcessor</item>
            <item name="bundle" xsi:type="object">Vendor\Module\Model\Cart\Processor\BundleProcessor</item>
        </argument>
    </arguments>
</type>
```

### 5. Use the Pool in a Service

```php
$processor = $this->processorPool->get($product->getTypeId());
$request = $processor->prepareRequest($product, $itemOptions, $qty);
$cart->addProduct($product, $request);
```

## Third-Party Extensibility

A third-party module adds support for a new product type by adding to its own `di.xml`:

```xml
<!-- ThirdParty/GiftCard/etc/di.xml -->
<type name="Vendor\Module\Model\Cart\AddToCartProcessorPool">
    <arguments>
        <argument name="processors" xsi:type="array">
            <item name="giftcard" xsi:type="object">ThirdParty\GiftCard\Model\Cart\Processor\GiftCardProcessor</item>
        </argument>
    </arguments>
</type>
```

Zero changes to the original module. Magento DI merges the arrays automatically.

## Pool with Sort Order

When execution order matters, use `sortOrder` on array items:

```xml
<type name="Vendor\Module\Model\Export\RowProcessorPool">
    <arguments>
        <argument name="processors" xsi:type="array">
            <item name="default_value" xsi:type="object" sortOrder="5">Vendor\Module\Model\Export\RowProcessor\DefaultValueProcessor</item>
            <item name="sanitize" xsi:type="object" sortOrder="10">Vendor\Module\Model\Export\RowProcessor\SanitizeProcessor</item>
            <item name="truncate" xsi:type="object" sortOrder="30">Vendor\Module\Model\Export\RowProcessor\TruncateProcessor</item>
        </argument>
    </arguments>
</type>
```

The Pool applies processors sequentially (chain of responsibility):

```php
public function process(array $row, ProfileInterface $profile): array
{
    foreach ($this->processors as $processor) {
        $row = $processor->process($row, $profile);
    }

    return $row;
}
```

## Best Practices

### DO

- Provide `get()` and `has()` methods on the Pool
- Throw `LocalizedException` with a descriptive message when a key is missing
- Use the same Pool class structure across the project (consistent API)
- Keep each strategy class focused on a single product type / format / adapter

### DON'T

- Don't add business logic to the Pool class itself — it is only a registry
- Don't use a Pool for 2 fixed branches that will never grow — a simple `if` is clearer
- Don't inject the Pool where only one strategy is ever needed — inject the strategy directly

## Pool vs If/Else

| Aspect | If/Else | Pool Pattern |
|--------|---------|-------------|
| Adding new type | Edit existing class | New class + DI config |
| Class size | Grows linearly | Each strategy ~20-30 lines |
| Unit testing | Mock entire service | Test each strategy in isolation |
| Third-party extension | Must patch your file | Adds own DI config |
| Open/Closed Principle | Violates | Respects |
