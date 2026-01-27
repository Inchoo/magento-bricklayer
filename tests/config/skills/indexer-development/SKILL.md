# Indexer Development Skill

## Overview

Magento indexers optimize data for fast retrieval. They transform EAV and relational data into flat, searchable formats. This skill covers creating custom indexers.

## Key Concepts

| Concept | Description |
|---------|-------------|
| Indexer | Processes data and populates index tables |
| Index Table | Flat table optimized for queries |
| MView | Materialized View for change tracking |
| Changelog | Tracks entity changes for partial reindex |

## Creating a Custom Indexer

### 1. Index Table (db_schema.xml)

```xml
<table name="vendor_module_product_index" resource="default" engine="innodb" comment="Product Index">
    <column xsi:type="int" name="product_id" unsigned="true" nullable="false" comment="Product ID"/>
    <column xsi:type="int" name="store_id" unsigned="true" nullable="false" comment="Store ID"/>
    <column xsi:type="varchar" name="computed_value" nullable="true" length="255" comment="Computed Value"/>
    <column xsi:type="decimal" name="score" precision="12" scale="4" nullable="false" default="0" comment="Score"/>

    <constraint xsi:type="primary" referenceId="PRIMARY">
        <column name="product_id"/>
        <column name="store_id"/>
    </constraint>

    <index referenceId="VENDOR_MODULE_PRODUCT_INDEX_SCORE" indexType="btree">
        <column name="score"/>
    </index>
</table>
```

### 2. Indexer Configuration (indexer.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Indexer/etc/indexer.xsd">

    <indexer id="vendor_module_product"
             view_id="vendor_module_product"
             class="Vendor\Module\Model\Indexer\Product"
             primary="product_id">
        <title translate="true">Product Score Index</title>
        <description translate="true">Indexes product scores for fast retrieval</description>

        <fieldset name="vendor_module_product_fieldset" source="Vendor\Module\Model\Indexer\Source\Product">
            <field name="product_id" xsi:type="filterable" dataType="int"/>
            <field name="store_id" xsi:type="filterable" dataType="int"/>
            <field name="computed_value" xsi:type="searchable" dataType="varchar"/>
            <field name="score" xsi:type="filterable" dataType="decimal"/>
        </fieldset>
    </indexer>

</config>
```

### 3. MView Configuration (mview.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Mview/etc/mview.xsd">

    <view id="vendor_module_product" class="Vendor\Module\Model\Indexer\Product" group="indexer">
        <subscriptions>
            <table name="catalog_product_entity" entity_column="entity_id"/>
            <table name="catalog_product_entity_varchar" entity_column="entity_id"/>
            <table name="catalog_product_entity_decimal" entity_column="entity_id"/>
        </subscriptions>
    </view>

</config>
```

### 4. Indexer Class

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Psr\Log\LoggerInterface;

class Product implements ActionInterface, MviewActionInterface
{
    public function __construct(
        private readonly ProductIndexer $productIndexer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute full reindex
     */
    public function executeFull(): void
    {
        $this->logger->info('Starting full product index');
        $this->productIndexer->reindexAll();
        $this->logger->info('Full product index completed');
    }

    /**
     * Execute partial reindex by IDs
     *
     * @param int[] $ids
     */
    public function executeList(array $ids): void
    {
        $this->logger->info('Starting partial product index', ['count' => count($ids)]);
        $this->productIndexer->reindexByIds($ids);
    }

    /**
     * Execute reindex for single entity
     *
     * @param int $id
     */
    public function executeRow($id): void
    {
        $this->productIndexer->reindexByIds([$id]);
    }

    /**
     * Execute by MView (changelog)
     *
     * @param int[] $ids
     */
    public function execute($ids): void
    {
        $this->executeList($ids);
    }
}
```

### 5. Indexer Logic

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Indexer;

use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductIndexer
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScoreCalculator $scoreCalculator
    ) {
    }

    public function reindexAll(): void
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('vendor_module_product_index');

        // Clear existing index
        $connection->truncateTable($tableName);

        // Reindex all stores
        foreach ($this->storeManager->getStores() as $store) {
            $this->reindexStore((int) $store->getId());
        }
    }

    public function reindexByIds(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('vendor_module_product_index');

        // Delete existing entries for these products
        $connection->delete($tableName, ['product_id IN (?)' => $productIds]);

        // Reindex for all stores
        foreach ($this->storeManager->getStores() as $store) {
            $this->reindexProducts($productIds, (int) $store->getId());
        }
    }

    private function reindexStore(int $storeId): void
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect('*');

        $productIds = $collection->getAllIds();

        // Process in batches
        $batches = array_chunk($productIds, 1000);
        foreach ($batches as $batch) {
            $this->reindexProducts($batch, $storeId);
        }
    }

    private function reindexProducts(array $productIds, int $storeId): void
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('vendor_module_product_index');

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addIdFilter($productIds);
        $collection->addAttributeToSelect('*');

        $data = [];
        foreach ($collection as $product) {
            $data[] = [
                'product_id' => (int) $product->getId(),
                'store_id' => $storeId,
                'computed_value' => $this->computeValue($product),
                'score' => $this->scoreCalculator->calculate($product),
            ];
        }

        if (!empty($data)) {
            $connection->insertOnDuplicate($tableName, $data);
        }
    }

    private function computeValue($product): string
    {
        // Custom computation logic
        return sprintf('%s-%s', $product->getSku(), $product->getTypeId());
    }
}
```

## Indexer Commands

```bash
# View all indexers
bin/magento indexer:info

# Check status
bin/magento indexer:status

# Reindex all
bin/magento indexer:reindex

# Reindex specific
bin/magento indexer:reindex vendor_module_product

# Set mode (realtime or schedule)
bin/magento indexer:set-mode schedule vendor_module_product
bin/magento indexer:set-mode realtime vendor_module_product

# Reset
bin/magento indexer:reset vendor_module_product
```

## Indexer Modes

| Mode | Description |
|------|-------------|
| realtime | Index updates immediately on save |
| schedule | Index updates via cron using changelog |

### Setting Mode Programmatically

```php
use Magento\Framework\Indexer\IndexerRegistry;

$indexer = $this->indexerRegistry->get('vendor_module_product');
$indexer->setScheduled(true); // Schedule mode
$indexer->setScheduled(false); // Realtime mode
```

## Invalidating Index

```php
use Magento\Framework\Indexer\IndexerRegistry;

// Invalidate (mark as "Reindex Required")
$indexer = $this->indexerRegistry->get('vendor_module_product');
$indexer->invalidate();
```

## Using the Index

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Framework\App\ResourceConnection;

class ProductScoreProvider
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function getTopProducts(int $storeId, int $limit = 10): array
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('vendor_module_product_index');

        $select = $connection->select()
            ->from($tableName, ['product_id', 'score'])
            ->where('store_id = ?', $storeId)
            ->order('score DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }
}
```

## Best Practices

1. **Use batching** - Process large datasets in chunks
2. **Implement partial reindex** - Use MView for efficiency
3. **Add proper indexes** - On the index table
4. **Log progress** - For debugging long-running indexes
5. **Test both modes** - Realtime and schedule
6. **Handle errors gracefully** - Don't break on single item failures
7. **Clean up old data** - When removing entities
8. **Monitor performance** - Track index build times
