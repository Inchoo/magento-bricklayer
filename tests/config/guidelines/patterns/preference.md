# Preference Guidelines

## Overview

Preferences (also called rewrites) replace entire class implementations. Use sparingly as they can cause conflicts.

## When to Use Preferences

| Scenario | Use Preference? | Alternative |
|----------|-----------------|-------------|
| Implement interface | Yes | - |
| Add new methods | Consider | Decorator pattern |
| Modify single method | No | Plugin |
| Replace core behavior | Last resort | Plugin/Observer |

## Preference Configuration

### etc/di.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <!-- Interface implementation (recommended) -->
    <preference for="Vendor\Module\Api\ServiceInterface"
                type="Vendor\Module\Model\Service"/>

    <!-- Class rewrite (use cautiously) -->
    <preference for="Magento\Catalog\Model\Product\Type"
                type="Vendor\Module\Model\Product\Type"/>

</config>
```

## Interface Implementation

The primary use case for preferences:

```php
<?php
// Api/ServiceInterface.php
namespace Vendor\Module\Api;

interface ServiceInterface
{
    public function process(int $id): bool;
}

// Model/Service.php
namespace Vendor\Module\Model;

class Service implements \Vendor\Module\Api\ServiceInterface
{
    public function process(int $id): bool
    {
        // Implementation
        return true;
    }
}
```

```xml
<preference for="Vendor\Module\Api\ServiceInterface"
            type="Vendor\Module\Model\Service"/>
```

## Class Rewrite (Legacy Pattern)

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Product;

use Magento\Catalog\Model\Product\Type as BaseType;

class Type extends BaseType
{
    /**
     * Override parent method
     */
    public function getAllowedSelectionTypes(): array
    {
        $types = parent::getAllowedSelectionTypes();
        // Modify types
        return $types;
    }

    /**
     * Add new method
     */
    public function customMethod(): void
    {
        // New functionality
    }
}
```

## Area-Specific Preferences

```xml
<!-- etc/frontend/di.xml -->
<preference for="Magento\Catalog\Block\Product\View"
            type="Vendor\Module\Block\Product\View"/>

<!-- etc/adminhtml/di.xml -->
<preference for="Magento\Catalog\Block\Adminhtml\Product\Edit"
            type="Vendor\Module\Block\Adminhtml\Product\Edit"/>
```

## Preference Conflicts

When multiple modules define preferences for the same class:
- Only one can win (based on load order)
- Creates maintenance nightmares
- Better alternatives: plugins, observers

## Alternatives to Preferences

### Plugin (for method modification)

```xml
<type name="Magento\Catalog\Model\Product">
    <plugin name="vendor_product_plugin"
            type="Vendor\Module\Plugin\ProductPlugin"/>
</type>
```

### Decorator (for adding functionality)

```php
<?php
namespace Vendor\Module\Model;

class ServiceDecorator implements ServiceInterface
{
    public function __construct(
        private readonly ServiceInterface $decorated
    ) {
    }

    public function process(): void
    {
        // Pre-processing
        $this->decorated->process();
        // Post-processing
    }
}
```

## Best Practices

1. **Prefer interface preferences** - Clean DI pattern
2. **Avoid class rewrites** - Use plugins instead
3. **Call parent methods** - Maintain expected behavior
4. **Document thoroughly** - Explain why rewrite is needed
5. **Test extensively** - Check for conflicts
6. **Consider module order** - Sequence in module.xml
7. **Use area scoping** - Limit scope when possible
