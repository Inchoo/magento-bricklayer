# Service Contract Guidelines

## Overview

Service contracts are interfaces that define the public API for Magento modules. They provide stable, well-defined extension points.

## Contract Types

| Type | Purpose | Location |
|------|---------|----------|
| Service Interface | Business logic operations | `Api/` |
| Data Interface | Data structures | `Api/Data/` |
| Repository Interface | CRUD operations | `Api/` |

## Service Interface

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api;

/**
 * Service interface for custom operations
 * @api
 */
interface CustomServiceInterface
{
    /**
     * Process entity by ID
     *
     * @param int $entityId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process(int $entityId): bool;

    /**
     * Validate entity data
     *
     * @param \Vendor\Module\Api\Data\EntityInterface $entity
     * @return \Magento\Framework\Validation\ValidationResult
     */
    public function validate(Data\EntityInterface $entity): \Magento\Framework\Validation\ValidationResult;
}
```

## Data Interface

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api\Data;

/**
 * Entity data interface
 * @api
 */
interface EntityInterface
{
    public const ENTITY_ID = 'entity_id';
    public const NAME = 'name';
    public const STATUS = 'status';

    /**
     * Get entity ID
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Set entity ID
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId(int $entityId): self;

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;
}
```

## Repository Interface

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Vendor\Module\Api\Data\EntityInterface;
use Vendor\Module\Api\Data\EntitySearchResultsInterface;

/**
 * Entity repository interface
 * @api
 */
interface EntityRepositoryInterface
{
    /**
     * Save entity
     *
     * @param \Vendor\Module\Api\Data\EntityInterface $entity
     * @return \Vendor\Module\Api\Data\EntityInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(EntityInterface $entity): EntityInterface;

    /**
     * Get entity by ID
     *
     * @param int $entityId
     * @return \Vendor\Module\Api\Data\EntityInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): EntityInterface;

    /**
     * Delete entity
     *
     * @param \Vendor\Module\Api\Data\EntityInterface $entity
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(EntityInterface $entity): bool;

    /**
     * Delete entity by ID
     *
     * @param int $entityId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;

    /**
     * Get list of entities
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Vendor\Module\Api\Data\EntitySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): EntitySearchResultsInterface;
}
```

## Search Results Interface

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Entity search results interface
 * @api
 */
interface EntitySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get entities
     *
     * @return \Vendor\Module\Api\Data\EntityInterface[]
     */
    public function getItems();

    /**
     * Set entities
     *
     * @param \Vendor\Module\Api\Data\EntityInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
```

## The @api Annotation

Mark interfaces with `@api` to indicate they are:
- Public API for the module
- Guaranteed to be stable
- Subject to semantic versioning

## DI Configuration

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <preference for="Vendor\Module\Api\Data\EntityInterface"
                type="Vendor\Module\Model\Entity"/>

    <preference for="Vendor\Module\Api\EntityRepositoryInterface"
                type="Vendor\Module\Model\EntityRepository"/>

    <preference for="Vendor\Module\Api\Data\EntitySearchResultsInterface"
                type="Magento\Framework\Api\SearchResults"/>

</config>
```

## Best Practices

1. **Use `@api` annotation** - Mark public interfaces
2. **Document all methods** - PHPDoc with types and exceptions
3. **Use explicit return types** - Type hints on all methods
4. **Define constants** - For field names in data interfaces
5. **Follow naming conventions** - `*Interface` suffix
6. **Don't change public API** - Without major version bump
