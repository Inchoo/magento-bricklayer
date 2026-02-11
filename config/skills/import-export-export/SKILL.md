# Import/Export: Export & Advanced Processing

> Related: See [import-export-import](../import-export-import/SKILL.md) for custom import entity development and validation.

## Custom Export Entity

### 1. Export Configuration (etc/export.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_ImportExport:etc/export.xsd">

    <entity name="custom_entity"
            label="Custom Entities"
            model="Vendor\Import\Model\Export\CustomEntity"
            entityAttributeFilterType="custom_entity"/>

</config>
```

### 2. Export Entity Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Import\Model\Export;

use Magento\ImportExport\Model\Export\AbstractEntity;
use Magento\Framework\App\ResourceConnection;

class CustomEntity extends AbstractEntity
{
    public const ENTITY_CODE = 'custom_entity';

    /**
     * @var array
     */
    protected array $_permanentAttributes = ['entity_id', 'name'];

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\ImportExport\Model\Export\Factory $collectionFactory
     * @param \Magento\ImportExport\Model\ResourceModel\CollectionByPagesIteratorFactory $resourceColFactory
     * @param ResourceConnection $resource
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\ImportExport\Model\Export\Factory $collectionFactory,
        \Magento\ImportExport\Model\ResourceModel\CollectionByPagesIteratorFactory $resourceColFactory,
        private readonly ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $storeManager, $collectionFactory, $resourceColFactory, $data);
    }

    /**
     * @return string
     */
    public function export(): string
    {
        $writer = $this->getWriter();

        // Write header
        $writer->setHeaderCols($this->_getHeaderColumns());

        // Fetch and write data
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('vendor_custom_entity'));

        // Apply filters
        $this->_applyFiltersToSelect($select);

        $data = $connection->fetchAll($select);

        foreach ($data as $row) {
            $writer->writeRow($this->_prepareRowForExport($row));
        }

        return $writer->getContents();
    }

    /**
     * @return array
     */
    protected function _getHeaderColumns(): array
    {
        return [
            'entity_id',
            'name',
            'status',
            'description',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @param array $row
     * @return array
     */
    protected function _prepareRowForExport(array $row): array
    {
        return [
            'entity_id' => $row['entity_id'],
            'name' => $row['name'],
            'status' => $row['status'] ? 'enabled' : 'disabled',
            'description' => $row['description'] ?? '',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * @param \Magento\Framework\DB\Select $select
     * @return void
     */
    protected function _applyFiltersToSelect(\Magento\Framework\DB\Select $select): void
    {
        foreach ($this->_parameters['export_filter'] ?? [] as $field => $value) {
            if (!empty($value)) {
                if (is_array($value)) {
                    // Date range filter
                    if (!empty($value[0])) {
                        $select->where("$field >= ?", $value[0]);
                    }
                    if (!empty($value[1])) {
                        $select->where("$field <= ?", $value[1]);
                    }
                } else {
                    $select->where("$field LIKE ?", "%$value%");
                }
            }
        }
    }

    /**
     * @return string
     */
    public function getEntityTypeCode(): string
    {
        return self::ENTITY_CODE;
    }

    /**
     * @return \Magento\Framework\Data\Collection
     */
    public function getAttributeCollection(): \Magento\Framework\Data\Collection
    {
        return new \Magento\Framework\Data\Collection();
    }
}
```

## Programmatic Import

```php
<?php

declare(strict_types=1);

namespace Vendor\Import\Model;

use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Source\Csv;
use Magento\Framework\Filesystem\Directory\ReadFactory;

class Importer
{
    /**
     * @param Import $import
     * @param ReadFactory $readFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        private readonly Import $import,
        private readonly ReadFactory $readFactory,
        private readonly \Psr\Log\LoggerInterface $logger
    ) {
    }

    /**
     * @param string $filePath
     * @param string $behavior
     * @return array
     */
    public function importFromFile(string $filePath, string $behavior = Import::BEHAVIOR_APPEND): array
    {
        $directory = $this->readFactory->create(dirname($filePath));

        $source = new Csv($directory->getAbsolutePath(basename($filePath)), $directory);

        $this->import->setData([
            'entity' => 'custom_entity',
            'behavior' => $behavior,
            'validation_strategy' => 'validation-stop-on-errors',
        ]);

        // Validate
        $validationResult = $this->import->validateSource($source);

        if (!$validationResult) {
            return [
                'success' => false,
                'errors' => $this->import->getErrorAggregator()->getAllErrors(),
            ];
        }

        // Import
        $this->import->importSource();

        return [
            'success' => true,
            'processed' => $this->import->getProcessedRowsCount(),
            'errors' => $this->import->getErrorAggregator()->getAllErrors(),
        ];
    }
}
```

## Large Dataset Handling

```php
/**
 * @param string $filePath
 * @param int $chunkSize
 * @return void
 */
public function importLargeFile(string $filePath, int $chunkSize = 5000): void
{
    $handle = fopen($filePath, 'r');
    $header = fgetcsv($handle);

    $chunk = [];
    $rowNum = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $chunk[] = array_combine($header, $row);
        $rowNum++;

        if (count($chunk) >= $chunkSize) {
            $this->processChunk($chunk);
            $chunk = [];

            // Free memory
            gc_collect_cycles();
        }
    }

    // Process remaining
    if (!empty($chunk)) {
        $this->processChunk($chunk);
    }

    fclose($handle);
}
```

## Best Practices

1. **Validate thoroughly** - Check all constraints before import
2. **Use transactions** - Wrap bulk operations in transactions
3. **Process in batches** - Avoid memory exhaustion
4. **Cache lookups** - Pre-load reference data
5. **Log progress** - Track import/export operations
6. **Handle duplicates** - Define clear deduplication strategy
7. **Provide sample files** - Help users with correct format
8. **Support incremental** - Allow delta imports/exports
