# Data Patches in Magento 2

## Overview

Data patches are the recommended way to modify data in the database during setup. They replace the old InstallData/UpgradeData scripts.

## Location

```
app/code/Vendor/Module/
├── Setup/
│   └── Patch/
│       └── Data/
│           └── AddCustomData.php
```

## Basic Structure

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomData implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @return self
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Your data modification logic here

        $this->moduleDataSetup->getConnection()->endSetup();

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

## Adding EAV Attributes

### Product Attribute

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
                'position' => 100,
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

### Customer Attribute

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerAttribute implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeSetFactory $attributeSetFactory
    ) {
    }

    /**
     * @return self
     */
    public function apply(): self
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

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

        $attribute = $customerSetup->getEavConfig()
            ->getAttribute(Customer::ENTITY, 'custom_field');

        $attribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => [
                'adminhtml_customer',
                'customer_account_create',
                'customer_account_edit',
            ],
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

## Inserting Data

```php
/**
 * @return self
 */
public function apply(): self
{
    $connection = $this->moduleDataSetup->getConnection();
    $tableName = $this->moduleDataSetup->getTable('custom_table');

    $connection->insertMultiple(
        $tableName,
        [
            ['code' => 'item1', 'value' => 'Value 1'],
            ['code' => 'item2', 'value' => 'Value 2'],
            ['code' => 'item3', 'value' => 'Value 3'],
        ]
    );

    return $this;
}
```

## Updating Data

```php
/**
 * @return self
 */
public function apply(): self
{
    $connection = $this->moduleDataSetup->getConnection();
    $tableName = $this->moduleDataSetup->getTable('custom_table');

    $connection->update(
        $tableName,
        ['status' => 1],
        ['code = ?' => 'item1']
    );

    return $this;
}
```

## Dependencies

Control execution order with dependencies:

```php
/**
 * @return array
 */
public static function getDependencies(): array
{
    return [
        \Vendor\Module\Setup\Patch\Data\InitialDataPatch::class,
        \Magento\Catalog\Setup\Patch\Data\SetSecurityAttributeProduct::class,
    ];
}
```

## Aliases

Handle renamed patches:

```php
/**
 * @return array
 */
public function getAliases(): array
{
    return [
        'Vendor\Module\Setup\Patch\Data\OldPatchName',
    ];
}
```

## Reversible Patches

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class ReversiblePatch implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @return self
     */
    public function apply(): self
    {
        // Apply changes
        return $this;
    }

    /**
     * @return void
     */
    public function revert(): void
    {
        // Revert changes
        $connection = $this->moduleDataSetup->getConnection();
        $connection->delete(
            $this->moduleDataSetup->getTable('custom_table'),
            ['code = ?' => 'added_item']
        );
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

## Version-Specific Patches

Implement `PatchVersionInterface` for version control:

```php
use Magento\Framework\Setup\Patch\PatchVersionInterface;

class VersionedPatch implements DataPatchInterface, PatchVersionInterface
{
    /**
     * @return string
     */
    public static function getVersion(): string
    {
        return '1.0.1';
    }

    // ... rest of implementation
}
```

## Schema Patches

For DDL operations, use Schema Patches:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class AddColumn implements SchemaPatchInterface
{
    /**
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(
        private readonly SchemaSetupInterface $schemaSetup
    ) {
    }

    /**
     * @return self
     */
    public function apply(): self
    {
        $this->schemaSetup->startSetup();

        $this->schemaSetup->getConnection()->addColumn(
            $this->schemaSetup->getTable('existing_table'),
            'new_column',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 255,
                'nullable' => true,
                'comment' => 'New Column',
            ]
        );

        $this->schemaSetup->endSetup();

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

## Best Practices

1. **One responsibility per patch** - Don't mix different data operations
2. **Use dependencies** for ordered execution
3. **Make patches idempotent** when possible
4. **Test patches** before deploying to production
5. **Use transactions** for complex operations
6. **Log important operations** for debugging
7. **Consider reversibility** for critical changes
