# EAV Development Skill

## Overview

The Entity-Attribute-Value (EAV) model is Magento's flexible data storage system that allows adding attributes to entities without schema changes.

## Key Concepts

### Entity Types

| Entity Type | Code | Table Prefix |
|-------------|------|--------------|
| Product | `catalog_product` | `catalog_product_entity` |
| Category | `catalog_category` | `catalog_category_entity` |
| Customer | `customer` | `customer_entity` |
| Customer Address | `customer_address` | `customer_address_entity` |

### Attribute Properties

| Property | Description |
|----------|-------------|
| `attribute_code` | Unique identifier (lowercase, underscore) |
| `frontend_input` | Input type (text, select, multiselect, etc.) |
| `backend_type` | Storage type (varchar, int, decimal, text, datetime) |
| `frontend_label` | Display label |
| `is_required` | Whether attribute is required |
| `is_user_defined` | Whether created by admin vs system |
| `is_visible` | Whether visible in forms |
| `is_searchable` | Whether searchable in catalog |
| `is_filterable` | Whether can be used as layered navigation filter |

## Creating Attributes

### Product Attribute via Data Patch

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomProductAttribute implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * @return self
     */
    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'custom_attribute',
            [
                'type' => 'varchar',
                'label' => 'Custom Attribute',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'General',
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
            ]
        );

        return $this;
    }

    /**
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
```

### Select Attribute with Source Model

```php
// Setup/Patch/Data/AddStatusAttribute.php
$eavSetup->addAttribute(
    Product::ENTITY,
    'custom_status',
    [
        'type' => 'int',
        'label' => 'Custom Status',
        'input' => 'select',
        'source' => \Vendor\Module\Model\Source\CustomStatus::class,
        'required' => false,
        'visible' => true,
        'user_defined' => true,
        'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
        'group' => 'General',
    ]
);
```

### Source Model

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class CustomStatus extends AbstractSource
{
    public const STATUS_PENDING = 1;
    public const STATUS_APPROVED = 2;
    public const STATUS_REJECTED = 3;

    /**
     * @return array
     */
    public function getAllOptions(): array
    {
        return [
            ['value' => self::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => self::STATUS_APPROVED, 'label' => __('Approved')],
            ['value' => self::STATUS_REJECTED, 'label' => __('Rejected')],
        ];
    }
}
```

## Customer Attributes

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerAttribute implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    /**
     * @return self
     */
    public function apply(): self
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $customerSetup->addAttribute(
            Customer::ENTITY,
            'custom_field',
            [
                'type' => 'varchar',
                'label' => 'Custom Field',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 200,
                'system' => false,
            ]
        );

        // Add to forms
        $attribute = $customerSetup->getEavConfig()
            ->getAttribute(Customer::ENTITY, 'custom_field');

        $attribute->setData('used_in_forms', [
            'adminhtml_customer',
            'customer_account_create',
            'customer_account_edit',
        ]);

        $attribute->save();

        return $this;
    }

    /**
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }
}
```

## Reading Attributes

```php
// Get attribute value
$value = $product->getData('custom_attribute');
$value = $product->getCustomAttribute('custom_attribute')?->getValue();

// Get attribute object
$eavConfig = $this->eavConfig;
$attribute = $eavConfig->getAttribute('catalog_product', 'custom_attribute');

// Get attribute options
$options = $attribute->getSource()->getAllOptions();
```

## Best Practices

1. **Use appropriate backend type**: Choose the smallest type that fits your data
2. **Set scope correctly**: SCOPE_GLOBAL, SCOPE_WEBSITE, or SCOPE_STORE
3. **Add to attribute set/group**: Ensure visibility in admin
4. **Use source models**: For select/multiselect attributes
5. **Consider indexing**: Set `is_filterable` and `is_searchable` appropriately
6. **Flat table considerations**: Enable `used_in_product_listing` if needed in catalog listings
