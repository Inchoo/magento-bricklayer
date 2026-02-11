# Repository Pattern in Magento 2

## Overview

The repository pattern provides a clean abstraction for data access, hiding the details of data storage from the business logic.

## Implementation Steps

### 1. Data Interface

Define the entity structure:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * @api
 */
interface CustomEntityInterface extends ExtensibleDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const NAME = 'name';
    public const STATUS = 'status';
    public const CREATED_AT = 'created_at';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int|null $entityId
     * @return self
     */
    public function setEntityId(?int $entityId): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): self;

    /**
     * @return int|null
     */
    public function getStatus(): ?int;

    /**
     * @param int|null $status
     * @return self
     */
    public function setStatus(?int $status): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string|null $createdAt
     * @return self
     */
    public function setCreatedAt(?string $createdAt): self;

    /**
     * @return CustomEntityExtensionInterface|null
     */
    public function getExtensionAttributes(): ?CustomEntityExtensionInterface;

    /**
     * @param CustomEntityExtensionInterface $attributes
     * @return self
     */
    public function setExtensionAttributes(CustomEntityExtensionInterface $attributes): self;
}
```

### 2. Repository Interface

Define CRUD operations:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Vendor\Module\Api\Data\CustomEntityInterface;
use Vendor\Module\Api\Data\CustomEntitySearchResultsInterface;

/**
 * @api
 */
interface CustomEntityRepositoryInterface
{
    /**
     * @param int $entityId
     * @return CustomEntityInterface
     * @throws NoSuchEntityException
     */
    public function get(int $entityId): CustomEntityInterface;

    /**
     * @param CustomEntityInterface $entity
     * @return CustomEntityInterface
     * @throws CouldNotSaveException
     */
    public function save(CustomEntityInterface $entity): CustomEntityInterface;

    /**
     * @param CustomEntityInterface $entity
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(CustomEntityInterface $entity): bool;

    /**
     * @param int $entityId
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return CustomEntitySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CustomEntitySearchResultsInterface;
}
```

### 3. Model Implementation

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use Vendor\Module\Api\Data\CustomEntityInterface;
use Vendor\Module\Model\ResourceModel\CustomEntity as ResourceModel;

class CustomEntity extends AbstractExtensibleModel implements CustomEntityInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);
        return $id !== null ? (int) $id : null;
    }

    /**
     * @param int|null $entityId
     * @return CustomEntityInterface
     */
    public function setEntityId(?int $entityId): CustomEntityInterface
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @param string|null $name
     * @return CustomEntityInterface
     */
    public function setName(?string $name): CustomEntityInterface
    {
        return $this->setData(self::NAME, $name);
    }

    // ... other getters/setters
}
```

### 4. Resource Model

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomEntity extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('vendor_custom_entity', 'entity_id');
    }
}
```

### 5. Collection

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\ResourceModel\CustomEntity;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Module\Model\CustomEntity;
use Vendor\Module\Model\ResourceModel\CustomEntity as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(CustomEntity::class, ResourceModel::class);
    }
}
```

### 6. Repository Implementation

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Vendor\Module\Api\CustomEntityRepositoryInterface;
use Vendor\Module\Api\Data\CustomEntityInterface;
use Vendor\Module\Api\Data\CustomEntitySearchResultsInterface;
use Vendor\Module\Api\Data\CustomEntitySearchResultsInterfaceFactory;
use Vendor\Module\Model\ResourceModel\CustomEntity as ResourceModel;
use Vendor\Module\Model\ResourceModel\CustomEntity\CollectionFactory;

class CustomEntityRepository implements CustomEntityRepositoryInterface
{
    /**
     * @param ResourceModel $resource
     * @param CustomEntityFactory $entityFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CustomEntitySearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        private readonly ResourceModel $resource,
        private readonly CustomEntityFactory $entityFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly CustomEntitySearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * @param int $entityId
     * @return CustomEntityInterface
     * @throws NoSuchEntityException
     */
    public function get(int $entityId): CustomEntityInterface
    {
        $entity = $this->entityFactory->create();
        $this->resource->load($entity, $entityId);

        if (!$entity->getEntityId()) {
            throw new NoSuchEntityException(
                __('Entity with ID "%1" does not exist.', $entityId)
            );
        }

        return $entity;
    }

    /**
     * @param CustomEntityInterface $entity
     * @return CustomEntityInterface
     * @throws CouldNotSaveException
     */
    public function save(CustomEntityInterface $entity): CustomEntityInterface
    {
        try {
            $this->resource->save($entity);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save entity: %1', $e->getMessage()),
                $e
            );
        }

        return $entity;
    }

    /**
     * @param CustomEntityInterface $entity
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(CustomEntityInterface $entity): bool
    {
        try {
            $this->resource->delete($entity);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete entity: %1', $e->getMessage()),
                $e
            );
        }

        return true;
    }

    /**
     * @param int $entityId
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->get($entityId));
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return CustomEntitySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CustomEntitySearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
```

### 7. DI Configuration

```xml
<!-- etc/di.xml -->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <preference for="Vendor\Module\Api\Data\CustomEntityInterface"
                type="Vendor\Module\Model\CustomEntity"/>

    <preference for="Vendor\Module\Api\CustomEntityRepositoryInterface"
                type="Vendor\Module\Model\CustomEntityRepository"/>

    <preference for="Vendor\Module\Api\Data\CustomEntitySearchResultsInterface"
                type="Magento\Framework\Api\SearchResults"/>
</config>
```

## Usage

```php
// Get by ID
$entity = $this->repository->get(123);

// Save
$entity->setName('Updated Name');
$this->repository->save($entity);

// List with criteria
$searchCriteria = $this->searchCriteriaBuilder
    ->addFilter('status', 1)
    ->setPageSize(10)
    ->create();

$results = $this->repository->getList($searchCriteria);
foreach ($results->getItems() as $entity) {
    // Process entity
}
```
