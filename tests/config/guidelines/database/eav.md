# EAV (Entity-Attribute-Value) Guidelines

## Overview

EAV is Magento's flexible attribute storage system for products, categories, and customers. It allows adding attributes without schema changes.

## EAV Entity Types

| Entity Type | Entity Code | Table Prefix |
|-------------|-------------|--------------|
| Product | `catalog_product` | `catalog_product_entity` |
| Category | `catalog_category` | `catalog_category_entity` |
| Customer | `customer` | `customer_entity` |
| Customer Address | `customer_address` | `customer_address_entity` |

## EAV Structure

```
eav_entity_type         - Entity type definitions
eav_attribute           - Attribute definitions
eav_attribute_set       - Attribute sets
eav_attribute_group     - Groups within sets
catalog_eav_attribute   - Entity-specific attribute config
catalog_product_entity  - Main entity table
catalog_product_entity_* - Value tables (varchar, int, decimal, datetime, text)
```

## Creating EAV Attributes

### Data Patch Method (Recommended)

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;

class AddCustomAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'custom_attribute',
            [
                'type' => 'varchar',
                'label' => 'Custom Attribute',
                'input' => 'text',
                'required' => false,
                'sort_order' => 100,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'user_defined' => true,
                'searchable' => true,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => true,
                'used_in_product_listing' => true,
                'group' => 'General',
            ]
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
```

## Attribute Types

| Backend Type | PHP Type | Use Case |
|--------------|----------|----------|
| `varchar` | string | Short text (up to 255 chars) |
| `int` | integer | Numbers, boolean, select |
| `decimal` | float | Prices, weights |
| `datetime` | DateTime | Dates and times |
| `text` | string | Long text, HTML |
| `static` | varies | Column in main entity table |

## Input Types

| Input | Backend | Description |
|-------|---------|-------------|
| `text` | varchar | Single line text |
| `textarea` | text | Multi-line text |
| `select` | int | Dropdown (uses source model) |
| `multiselect` | varchar | Multiple selection |
| `boolean` | int | Yes/No |
| `date` | datetime | Date picker |
| `price` | decimal | Price field |
| `media_image` | varchar | Image upload |
| `weee` | decimal | Fixed product tax |

## Attribute Scopes

| Scope | Constant | Behavior |
|-------|----------|----------|
| Global | `SCOPE_GLOBAL` | Same value for all stores |
| Website | `SCOPE_WEBSITE` | Can differ per website |
| Store View | `SCOPE_STORE` | Can differ per store view |

## Source Models

For select/multiselect attributes:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class CustomOptions extends AbstractSource
{
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => '', 'label' => __('-- Please Select --')],
                ['value' => 'option1', 'label' => __('Option 1')],
                ['value' => 'option2', 'label' => __('Option 2')],
                ['value' => 'option3', 'label' => __('Option 3')],
            ];
        }
        return $this->_options;
    }
}
```

Usage in attribute:
```php
$eavSetup->addAttribute(
    Product::ENTITY,
    'custom_select',
    [
        'type' => 'int',
        'input' => 'select',
        'source' => \Vendor\Module\Model\Attribute\Source\CustomOptions::class,
        // ...
    ]
);
```

## Backend Models

For custom save/load logic:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Attribute\Backend;

use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\DataObject;

class CustomBackend extends AbstractBackend
{
    public function beforeSave($object): self
    {
        $value = $object->getData($this->getAttribute()->getAttributeCode());
        // Process value before save
        $value = strtoupper($value);
        $object->setData($this->getAttribute()->getAttributeCode(), $value);
        return parent::beforeSave($object);
    }

    public function afterLoad($object): self
    {
        // Process value after load
        return parent::afterLoad($object);
    }
}
```

## Frontend Models

For display rendering:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Attribute\Frontend;

use Magento\Eav\Model\Entity\Attribute\Frontend\AbstractFrontend;

class CustomFrontend extends AbstractFrontend
{
    public function getValue(\Magento\Framework\DataObject $object): string
    {
        $value = parent::getValue($object);
        // Format for display
        return sprintf('Custom: %s', $value);
    }
}
```

## Reading EAV Data

```php
<?php
// Using product model
$product = $this->productRepository->get('sku123');
$customValue = $product->getData('custom_attribute');
$customValue = $product->getCustomAttribute('custom_attribute')?->getValue();

// Using collection with attribute
$collection = $this->productCollectionFactory->create();
$collection->addAttributeToSelect('custom_attribute')
           ->addAttributeToFilter('custom_attribute', ['eq' => 'value']);
```

## Best Practices

1. **Use flat tables** for frequently accessed attributes (`used_in_product_listing`)
2. **Limit custom attributes** - EAV queries are expensive
3. **Index filterable attributes** - For search performance
4. **Use static type** when possible - Avoids joins
5. **Test with large catalogs** - EAV performance varies with scale
6. **Consider Elasticsearch** for search - Better than MySQL EAV queries
