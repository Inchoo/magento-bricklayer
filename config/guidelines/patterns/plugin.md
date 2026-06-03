# Plugin (Interceptor) Pattern in Magento 2

## Overview

Plugins allow you to modify the behavior of public methods without modifying the original class. They are Magento's implementation of the interceptor pattern.

## Plugin Types

| Type | Method Prefix | Purpose |
|------|---------------|---------|
| Before | `before{Method}` | Modify input arguments |
| After | `after{Method}` | Modify return value |
| Around | `around{Method}` | Full control over method execution |

## Before Plugin

Modify arguments before the original method executes:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductRepositoryPlugin
{
    /**
     * Modify product before save
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
        // Trim product name
        $name = $product->getName();
        if ($name !== null) {
            $product->setName(trim($name));
        }

        // Return array of modified arguments
        return [$product, $saveOptions];
    }
}
```

## After Plugin

Modify the return value after the original method executes:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductRepositoryPlugin
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
        // Add custom attribute value
        $result->setCustomAttribute('loaded_at', date('Y-m-d H:i:s'));

        return $result;
    }
}
```

## Around Plugin

Control the entire method execution:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

class ProductRepositoryPlugin
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Log product save operations
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

        $this->logger->info('Saving product', ['sku' => $sku]);

        try {
            // Call the original method
            $result = $proceed($product, $saveOptions);

            $duration = microtime(true) - $startTime;
            $this->logger->info('Product saved', [
                'sku' => $sku,
                'duration' => $duration,
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

## Configuration

Register plugins in `etc/di.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Catalog\Api\ProductRepositoryInterface">
        <plugin name="vendor_module_product_repository"
                type="Vendor\Module\Plugin\ProductRepositoryPlugin"
                sortOrder="10"
                disabled="false"/>
    </type>

</config>
```

### Area-Specific Plugins

Configure in `etc/frontend/di.xml` or `etc/adminhtml/di.xml`:

```xml
<!-- etc/frontend/di.xml - Only applies to storefront -->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Catalog\Api\ProductRepositoryInterface">
        <plugin name="vendor_module_frontend_product"
                type="Vendor\Module\Plugin\Frontend\ProductPlugin"/>
    </type>

</config>
```

## Best Practices

### DO

- Use **before/after** plugins when possible
- Keep plugin logic focused and minimal
- Use descriptive plugin names
- Specify appropriate sort order
- Handle exceptions properly in around plugins

### DON'T

- Don't use around plugins for simple modifications
- Don't create plugins on final methods or classes
- Don't call `$subject` methods that would trigger the same plugin (infinite loop)
- Don't rely on plugin execution order without explicit sort order
- Don't use plugins to replace entire class behavior (use preference instead)

## When to Use Plugins vs Other Options

| Scenario | Solution |
|----------|----------|
| Modify method arguments | Before plugin |
| Modify return value | After plugin |
| Add logging/timing | Around plugin |
| React to events | Observer |
| Replace class entirely | Preference |
| Add new method | Preference or decorator |

## Plugin Limitations

Plugins cannot be used on:

- Final methods
- Final classes
- Non-public methods
- Static methods
- `__construct`
- Virtual types
- Objects created without ObjectManager

## Sort Order

When multiple plugins exist:

1. **Before plugins**: Executed in ascending sort order
2. **Original method**: Executed after all before plugins
3. **After plugins**: Executed in ascending sort order

```xml
<!-- Plugin with sortOrder=10 runs before sortOrder=20 -->
<plugin name="first_plugin" type="..." sortOrder="10"/>
<plugin name="second_plugin" type="..." sortOrder="20"/>
```

## Plugin Chain on Repositories

A powerful pattern is chaining multiple `beforeSave` plugins on a repository to handle cross-cutting concerns. Each plugin handles one responsibility, and the repository stays clean (CRUD only).

### Example: Repository Plugin Chain

```xml
<type name="Vendor\Module\Api\CustomEntityRepositoryInterface">
    <plugin name="validate_max_entities"
            type="Vendor\Module\Plugin\ValidateMaxEntitiesPlugin"
            sortOrder="10"/>
    <plugin name="set_timestamps"
            type="Vendor\Module\Plugin\SetTimestampsPlugin"
            sortOrder="20"/>
    <plugin name="encrypt_sensitive_data"
            type="Vendor\Module\Plugin\EncryptSensitiveDataPlugin"
            sortOrder="30"/>
</type>
```

**Plugin 1 — Validation (sortOrder 10):**

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Vendor\Module\Api\CustomEntityRepositoryInterface;
use Vendor\Module\Api\Data\CustomEntityInterface;
use Vendor\Module\Model\ConfigInterface;
use Vendor\Module\Model\ResourceModel\CustomEntity\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class ValidateMaxEntitiesPlugin
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Enforce maximum entity limit before saving new entities.
     */
    public function beforeSave(
        CustomEntityRepositoryInterface $subject,
        CustomEntityInterface $entity
    ): array {
        // Only validate new entities (no ID yet)
        if ($entity->getId()) {
            return [$entity];
        }

        // Use CollectionFactory instead of repository to avoid circular plugin chain
        $count = $this->collectionFactory->create()
            ->addFieldToFilter('customer_id', $entity->getCustomerId())
            ->getSize();

        if ($count >= $this->config->getMaxEntities()) {
            throw new LocalizedException(
                __('Maximum of %1 entities allowed.', $this->config->getMaxEntities())
            );
        }

        return [$entity];
    }
}
```

**Plugin 2 — Timestamps (sortOrder 20):**

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Plugin;

use Vendor\Module\Api\CustomEntityRepositoryInterface;
use Vendor\Module\Api\Data\CustomEntityInterface;

class SetTimestampsPlugin
{
    public function beforeSave(
        CustomEntityRepositoryInterface $subject,
        CustomEntityInterface $entity
    ): array {
        $now = date('Y-m-d H:i:s');

        if (!$entity->getId()) {
            $entity->setCreatedAt($now);
        }

        $entity->setUpdatedAt($now);

        return [$entity];
    }
}
```

**Execution flow:**

```
$repository->save($entity)
    → sortOrder 10: ValidateMaxEntitiesPlugin (reject if limit exceeded)
    → sortOrder 20: SetTimestampsPlugin (set created_at / updated_at)
    → sortOrder 30: EncryptSensitiveDataPlugin (encrypt passwords)
    → Repository::save() (clean CRUD — no validation, no timestamps)
```

### Benefits of Plugin Chain on Repository

- **Repository stays clean** — only CRUD logic, no cross-cutting concerns
- **Each plugin is testable in isolation**
- **Third-party modules can add their own plugins** with higher sortOrder
- **Plugins can be disabled individually** in `di.xml` without touching others
