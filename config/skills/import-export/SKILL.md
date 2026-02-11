# Import/Export Skill

## Overview

Magento's import/export system enables bulk data operations for products, customers, and custom entities. This skill covers creating custom import/export entities, handling large datasets, and implementing efficient data processing.

## Supported Entities

| Entity | Import | Export | Format |
|--------|--------|--------|--------|
| Products | Yes | Yes | CSV |
| Categories | Limited | Yes | CSV |
| Customers | Yes | Yes | CSV |
| Customer Addresses | Yes | Yes | CSV |
| Advanced Pricing | Yes | Yes | CSV |
| Stock Sources | Yes | Yes | CSV |

## Custom Import Entity

### 1. Module Structure

```
app/code/Vendor/Import/
├── Files/
│   └── Sample/
│       └── custom_entity.csv
├── Model/
│   ├── Import/
│   │   └── CustomEntity.php
│   └── Source/
│       └── Import/
│           └── Behavior/
│               └── Custom.php
├── etc/
│   ├── di.xml
│   ├── import.xml
│   └── module.xml
└── registration.php
```

### 2. Import Configuration (etc/import.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_ImportExport:etc/import.xsd">

    <entity name="custom_entity"
            label="Custom Entities"
            model="Vendor\Import\Model\Import\CustomEntity"
            behaviorModel="Magento\ImportExport\Model\Source\Import\Behavior\Basic"/>

</config>
```

### 3. Import Entity Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Import\Model\Import;

use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;

class CustomEntity extends AbstractEntity
{
    public const ENTITY_CODE = 'custom_entity';
    public const TABLE = 'vendor_custom_entity';

    public const COL_ENTITY_ID = 'entity_id';
    public const COL_NAME = 'name';
    public const COL_STATUS = 'status';
    public const COL_DESCRIPTION = 'description';

    /**
     * @var array
     */
    protected array $_permanentAttributes = [self::COL_NAME];

    /**
     * @var array
     */
    protected array $validColumnNames = [
        self::COL_ENTITY_ID,
        self::COL_NAME,
        self::COL_STATUS,
        self::COL_DESCRIPTION,
    ];

    /**
     * @var bool
     */
    protected bool $needColumnCheck = true;

    /**
     * @var bool
     */
    protected bool $logInHistory = true;

    /**
     * @var array
     */
    private array $cachedEntities = [];

    /**
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\ImportExport\Helper\Data $importExportData
     * @param Data $importData
     * @param ResourceConnection $resource
     * @param Helper $resourceHelper
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param \Vendor\Module\Model\EntityFactory $entityFactory
     * @param \Vendor\Module\Api\EntityRepositoryInterface $entityRepository
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        Data $importData,
        ResourceConnection $resource,
        Helper $resourceHelper,
        ProcessingErrorAggregatorInterface $errorAggregator,
        private readonly \Vendor\Module\Model\EntityFactory $entityFactory,
        private readonly \Vendor\Module\Api\EntityRepositoryInterface $entityRepository
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->_resource = $resource;
        $this->_connection = $resource->getConnection();
        $this->errorAggregator = $errorAggregator;

        $this->initExistingEntities();
    }

    /**
     * @return string
     */
    public function getEntityTypeCode(): string
    {
        return self::ENTITY_CODE;
    }

    /**
     * @param array $rowData
     * @param int $rowNum
     * @return bool
     */
    public function validateRow(array $rowData, int $rowNum): bool
    {
        if ($this->_validatedRows !== null && isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        // Required field validation
        if (empty($rowData[self::COL_NAME])) {
            $this->addRowError('Name is required', $rowNum);
            return false;
        }

        // Validate name length
        if (strlen($rowData[self::COL_NAME]) > 255) {
            $this->addRowError('Name must be less than 255 characters', $rowNum);
            return false;
        }

        // Validate status
        if (isset($rowData[self::COL_STATUS])) {
            $validStatuses = ['enabled', 'disabled', '1', '0'];
            if (!in_array($rowData[self::COL_STATUS], $validStatuses, true)) {
                $this->addRowError('Invalid status value', $rowNum);
                return false;
            }
        }

        // Check for duplicates in import data
        $name = $rowData[self::COL_NAME];
        if ($this->checkDuplicate($name, $rowNum)) {
            return false;
        }

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    /**
     * @return bool
     */
    protected function _importData(): bool
    {
        switch ($this->getBehavior()) {
            case \Magento\ImportExport\Model\Import::BEHAVIOR_DELETE:
                $this->deleteEntities();
                break;
            case \Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE:
                $this->replaceEntities();
                break;
            case \Magento\ImportExport\Model\Import::BEHAVIOR_APPEND:
            default:
                $this->saveEntities();
                break;
        }

        return true;
    }

    /**
     * @return void
     */
    private function saveEntities(): void
    {
        $toCreate = [];
        $toUpdate = [];

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                $name = $rowData[self::COL_NAME];

                if (isset($this->cachedEntities[$name])) {
                    // Update existing
                    $toUpdate[] = $this->prepareDataForUpdate($rowData);
                } else {
                    // Create new
                    $toCreate[] = $this->prepareDataForInsert($rowData);
                }
            }
        }

        if (!empty($toCreate)) {
            $this->saveNewEntities($toCreate);
        }

        if (!empty($toUpdate)) {
            $this->updateEntities($toUpdate);
        }
    }

    /**
     * @return void
     */
    private function deleteEntities(): void
    {
        $idsToDelete = [];

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                $name = $rowData[self::COL_NAME];
                if (isset($this->cachedEntities[$name])) {
                    $idsToDelete[] = $this->cachedEntities[$name];
                }
            }
        }

        if (!empty($idsToDelete)) {
            $this->_connection->delete(
                $this->_resource->getTableName(self::TABLE),
                $this->_connection->quoteInto('entity_id IN (?)', $idsToDelete)
            );
        }
    }

    /**
     * @return void
     */
    private function replaceEntities(): void
    {
        $this->deleteEntities();
        $this->_dataSourceModel->getIterator()->rewind();
        $this->saveEntities();
    }

    /**
     * @param array $rowData
     * @return array
     */
    private function prepareDataForInsert(array $rowData): array
    {
        return [
            'name' => $rowData[self::COL_NAME],
            'status' => $this->normalizeStatus($rowData[self::COL_STATUS] ?? 'enabled'),
            'description' => $rowData[self::COL_DESCRIPTION] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array $rowData
     * @return array
     */
    private function prepareDataForUpdate(array $rowData): array
    {
        $name = $rowData[self::COL_NAME];
        $data = [
            'entity_id' => $this->cachedEntities[$name],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($rowData[self::COL_STATUS])) {
            $data['status'] = $this->normalizeStatus($rowData[self::COL_STATUS]);
        }

        if (isset($rowData[self::COL_DESCRIPTION])) {
            $data['description'] = $rowData[self::COL_DESCRIPTION];
        }

        return $data;
    }

    /**
     * @param string $status
     * @return int
     */
    private function normalizeStatus(string $status): int
    {
        return in_array($status, ['enabled', '1'], true) ? 1 : 0;
    }

    /**
     * @param array $entities
     * @return void
     */
    private function saveNewEntities(array $entities): void
    {
        $this->_connection->insertMultiple(
            $this->_resource->getTableName(self::TABLE),
            $entities
        );
    }

    /**
     * @param array $entities
     * @return void
     */
    private function updateEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            $entityId = $entity['entity_id'];
            unset($entity['entity_id']);

            $this->_connection->update(
                $this->_resource->getTableName(self::TABLE),
                $entity,
                ['entity_id = ?' => $entityId]
            );
        }
    }

    /**
     * @return void
     */
    private function initExistingEntities(): void
    {
        $select = $this->_connection->select()
            ->from($this->_resource->getTableName(self::TABLE), ['name', 'entity_id']);

        $this->cachedEntities = $this->_connection->fetchPairs($select);
    }

    /**
     * @param string $name
     * @param int $rowNum
     * @return bool
     */
    private function checkDuplicate(string $name, int $rowNum): bool
    {
        static $importedNames = [];

        if (isset($importedNames[$name])) {
            $this->addRowError(
                sprintf('Duplicate name "%s" (first occurrence: row %d)', $name, $importedNames[$name]),
                $rowNum
            );
            return true;
        }

        $importedNames[$name] = $rowNum;
        return false;
    }
}
```

### 4. Sample CSV File

```csv
name,status,description
"Entity One",enabled,"Description for entity one"
"Entity Two",disabled,"Description for entity two"
"Entity Three",1,"Another description"
```

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
