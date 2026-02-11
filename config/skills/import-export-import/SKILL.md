# Import/Export: Custom Import Entities

> Related: See [import-export-export](../import-export-export/SKILL.md) for custom export entities, programmatic import, and large dataset handling.

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
