# REST API Development Skill

## Overview

Magento 2's REST API enables external systems and frontends to interact with the store. This skill covers creating, securing, and optimizing REST endpoints.

## Key Concepts

| Concept | Description |
|---------|-------------|
| Service Contract | PHP interfaces defining API operations |
| webapi.xml | Route configuration for API endpoints |
| Data Interfaces | DTOs for request/response data |
| ACL Resources | Access control for API endpoints |

## Creating a REST API

### 1. Define Data Interface

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api\Data;

/**
 * @api
 */
interface ItemInterface
{
    public const string ID = 'entity_id';
    public const string NAME = 'name';

    /**
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * @param int $id
     * @return $this
     */
    public function setId(int $id): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;
}
```

### 2. Define Repository Interface

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api;

use Vendor\Module\Api\Data\ItemInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * @api
 */
interface ItemRepositoryInterface
{
    /**
     * @param int $id
     * @return \Vendor\Module\Api\Data\ItemInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): ItemInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Vendor\Module\Api\Data\ItemSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): Data\ItemSearchResultsInterface;

    /**
     * @param \Vendor\Module\Api\Data\ItemInterface $item
     * @return \Vendor\Module\Api\Data\ItemInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(ItemInterface $item): ItemInterface;

    /**
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;
}
```

### 3. Configure Routes (webapi.xml)

```xml
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">

    <route url="/V1/items/:id" method="GET">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="getById"/>
        <resources>
            <resource ref="Vendor_Module::item_read"/>
        </resources>
    </route>

    <route url="/V1/items" method="GET">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="getList"/>
        <resources>
            <resource ref="Vendor_Module::item_read"/>
        </resources>
    </route>

    <route url="/V1/items" method="POST">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="save"/>
        <resources>
            <resource ref="Vendor_Module::item_write"/>
        </resources>
    </route>

    <route url="/V1/items/:id" method="DELETE">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="deleteById"/>
        <resources>
            <resource ref="Vendor_Module::item_delete"/>
        </resources>
    </route>

</routes>
```

### 4. Define ACL (acl.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Vendor_Module::items" title="Items API">
                    <resource id="Vendor_Module::item_read" title="Read Items"/>
                    <resource id="Vendor_Module::item_write" title="Write Items"/>
                    <resource id="Vendor_Module::item_delete" title="Delete Items"/>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
```

## Authentication Methods

### Admin Token

```bash
# Get token
TOKEN=$(curl -s -X POST "https://magento.test/rest/V1/integration/admin/token" \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"password123"}')

# Use token
curl -X GET "https://magento.test/rest/V1/items/1" \
    -H "Authorization: Bearer $TOKEN"
```

### Customer Token

```bash
# Get token
TOKEN=$(curl -s -X POST "https://magento.test/rest/V1/integration/customer/token" \
    -H "Content-Type: application/json" \
    -d '{"username":"customer@example.com","password":"password123"}')
```

### Anonymous Access

```xml
<route url="/V1/public/info" method="GET">
    <service class="Vendor\Module\Api\PublicInterface" method="getInfo"/>
    <resources>
        <resource ref="anonymous"/>
    </resources>
</route>
```

### Self Resource (Customer's Own Data)

```xml
<route url="/V1/customers/me/items" method="GET">
    <service class="Vendor\Module\Api\CustomerItemInterface" method="getMyItems"/>
    <resources>
        <resource ref="self"/>
    </resources>
</route>
```

## Request/Response Examples

### GET Single Item

```bash
curl -X GET "https://magento.test/rest/V1/items/1" \
    -H "Authorization: Bearer $TOKEN"

# Response
{
    "entity_id": 1,
    "name": "Item Name",
    "status": 1
}
```

### GET List with Search Criteria

```bash
curl -X GET "https://magento.test/rest/V1/items?searchCriteria[filterGroups][0][filters][0][field]=status&searchCriteria[filterGroups][0][filters][0][value]=1&searchCriteria[pageSize]=10" \
    -H "Authorization: Bearer $TOKEN"

# Response
{
    "items": [...],
    "search_criteria": {...},
    "total_count": 42
}
```

### POST Create

```bash
curl -X POST "https://magento.test/rest/V1/items" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"item":{"name":"New Item","status":1}}'
```

### PUT Update

```bash
curl -X PUT "https://magento.test/rest/V1/items/1" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"item":{"name":"Updated Name"}}'
```

## Error Handling

```php
// In your service implementation
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;

/**
 * @param int $id
 * @return ItemInterface
 * @throws NoSuchEntityException
 */
public function getById(int $id): ItemInterface
{
    $item = $this->itemFactory->create();
    $this->resource->load($item, $id);

    if (!$item->getId()) {
        throw new NoSuchEntityException(
            __('Item with id "%1" does not exist.', $id)
        );
    }

    return $item;
}
```

## Search Criteria Builder

```php
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\FilterBuilder;

/**
 * @param SearchCriteriaBuilder $searchCriteriaBuilder
 * @param SortOrderBuilder $sortOrderBuilder
 * @param FilterBuilder $filterBuilder
 */
public function __construct(
    private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    private readonly SortOrderBuilder $sortOrderBuilder,
    private readonly FilterBuilder $filterBuilder
) {
}

/**
 * @return array
 */
public function getActiveItems(): array
{
    $filter = $this->filterBuilder
        ->setField('status')
        ->setValue(1)
        ->setConditionType('eq')
        ->create();

    $sortOrder = $this->sortOrderBuilder
        ->setField('created_at')
        ->setDirection('DESC')
        ->create();

    $searchCriteria = $this->searchCriteriaBuilder
        ->addFilters([$filter])
        ->addSortOrder($sortOrder)
        ->setPageSize(20)
        ->create();

    return $this->repository->getList($searchCriteria)->getItems();
}
```

## Extension Attributes

Add custom data to existing APIs:

```xml
<!-- etc/extension_attributes.xml -->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Api/etc/extension_attributes.xsd">
    <extension_attributes for="Magento\Catalog\Api\Data\ProductInterface">
        <attribute code="custom_data" type="string"/>
    </extension_attributes>
</config>
```

## Best Practices

1. **Use @api annotation** on stable interfaces
2. **Document parameters** with @param and @return PHPDoc
3. **Version your API** with /V1/, /V2/ prefixes
4. **Use proper exceptions** (NoSuchEntityException, etc.)
5. **Implement pagination** for list endpoints
6. **Validate input** before processing
7. **Use data interfaces** for DTOs
8. **Test with WebapiAbstract** test class
