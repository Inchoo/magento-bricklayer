# Indexer Guidelines

## Overview

Indexers transform raw data into optimized flat tables for fast read operations. They improve performance for catalogs, search, and pricing.

## Built-in Indexers

| Indexer | Purpose |
|---------|---------|
| `catalog_category_product` | Category to product relationships |
| `catalog_product_category` | Product to category relationships |
| `catalog_product_price` | Product prices |
| `catalog_product_attribute` | Product attributes for filters |
| `cataloginventory_stock` | Stock status |
| `catalogrule_rule` | Catalog price rules |
| `catalogrule_product` | Products affected by rules |
| `catalogsearch_fulltext` | Full-text search index |
| `customer_grid` | Customer grid in admin |
| `design_config_grid` | Design config grid |
| `inventory` | Multi-source inventory |
| `targetrule_product_rule` | Related products rules |
| `targetrule_rule_product` | Product rule associations |

## Indexer Modes

| Mode | Description | Use Case |
|------|-------------|----------|
| Real-time | Updates on save | Small catalogs |
| Scheduled | Updates via cron | Large catalogs |

## Managing Indexers

```bash
# View indexer status
bin/magento indexer:status

# Reindex all
bin/magento indexer:reindex

# Reindex specific indexer
bin/magento indexer:reindex catalog_product_price

# Set mode
bin/magento indexer:set-mode schedule
bin/magento indexer:set-mode realtime catalog_product_price

# Reset indexer state
bin/magento indexer:reset
```

## Creating Custom Indexer

### 1. Indexer Configuration (etc/indexer.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Indexer/etc/indexer.xsd">

    <indexer id="vendor_custom_index"
             view_id="vendor_custom_index"
             class="Vendor\Module\Model\Indexer\CustomIndexer"
             primary="entity_id">
        <title translate="true">Custom Entity Index</title>
        <description translate="true">Indexes custom entity data</description>
    </indexer>

</config>
```

### 2. MView Configuration (etc/mview.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Mview/etc/mview.xsd">

    <view id="vendor_custom_index"
          class="Vendor\Module\Model\Indexer\CustomIndexer"
          group="indexer">
        <subscriptions>
            <table name="vendor_custom_entity" entity_column="entity_id"/>
        </subscriptions>
    </view>

</config>
```

### 3. Indexer Class

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Psr\Log\LoggerInterface;

class CustomIndexer implements ActionInterface, MviewActionInterface
{
    public function __construct(
        private readonly IndexBuilder $indexBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute full reindex
     */
    public function executeFull(): void
    {
        $this->logger->info('Starting full reindex');
        $this->indexBuilder->reindexAll();
        $this->logger->info('Full reindex completed');
    }

    /**
     * Execute partial reindex by IDs
     *
     * @param int[] $ids
     */
    public function executeList(array $ids): void
    {
        $this->indexBuilder->reindexByIds($ids);
    }

    /**
     * Execute single entity reindex
     *
     * @param int $id
     */
    public function executeRow($id): void
    {
        $this->indexBuilder->reindexByIds([$id]);
    }

    /**
     * Execute MView indexer
     *
     * @param int[] $ids
     */
    public function execute($ids): void
    {
        $this->executeList($ids);
    }
}
```

### 4. Index Builder

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model\Indexer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class IndexBuilder
{
    private const INDEX_TABLE = 'vendor_custom_index';
    private const SOURCE_TABLE = 'vendor_custom_entity';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function reindexAll(): void
    {
        $connection = $this->getConnection();

        // Clear index table
        $connection->truncateTable($this->getIndexTable());

        // Build full index
        $select = $connection->select()
            ->from($this->getSourceTable(), ['entity_id', 'name', 'status'])
            ->where('status = ?', 1);

        $connection->query(
            $connection->insertFromSelect(
                $select,
                $this->getIndexTable(),
                ['entity_id', 'name', 'status']
            )
        );
    }

    public function reindexByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $connection = $this->getConnection();

        // Remove old index entries
        $connection->delete(
            $this->getIndexTable(),
            ['entity_id IN (?)' => $ids]
        );

        // Rebuild for specific IDs
        $select = $connection->select()
            ->from($this->getSourceTable(), ['entity_id', 'name', 'status'])
            ->where('entity_id IN (?)', $ids)
            ->where('status = ?', 1);

        $connection->query(
            $connection->insertFromSelect(
                $select,
                $this->getIndexTable(),
                ['entity_id', 'name', 'status']
            )
        );
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function getIndexTable(): string
    {
        return $this->resource->getTableName(self::INDEX_TABLE);
    }

    private function getSourceTable(): string
    {
        return $this->resource->getTableName(self::SOURCE_TABLE);
    }
}
```

### 5. Index Table Schema (etc/db_schema.xml)

```xml
<table name="vendor_custom_index" resource="default" engine="innodb" comment="Custom Index Table">
    <column xsi:type="int" name="entity_id" unsigned="true" nullable="false" comment="Entity ID"/>
    <column xsi:type="varchar" name="name" nullable="false" length="255" comment="Name"/>
    <column xsi:type="smallint" name="status" unsigned="true" nullable="false" comment="Status"/>
    <constraint xsi:type="primary" referenceId="PRIMARY">
        <column name="entity_id"/>
    </constraint>
    <index referenceId="VENDOR_CUSTOM_INDEX_STATUS" indexType="btree">
        <column name="status"/>
    </index>
</table>
```

## MView (Materialized View)

MView tracks changes to source tables and triggers partial reindex:

1. Changes written to `*_cl` (changelog) tables
2. Cron processes changelog entries
3. Indexer updates only changed data

## Best Practices

1. **Use scheduled mode** for large catalogs
2. **Optimize index queries** - Profile with MySQL EXPLAIN
3. **Batch operations** - Process in chunks to manage memory
4. **Monitor changelog size** - Large changelogs indicate problems
5. **Test reindex time** - Ensure it fits within cron schedule
6. **Use indexes** on source tables for filter columns
7. **Consider flat tables** for read-heavy operations
