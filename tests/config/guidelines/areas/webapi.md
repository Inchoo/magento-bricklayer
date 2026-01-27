# Magento 2 WebAPI Development Guidelines

## Overview

Magento's WebAPI layer provides REST and SOAP endpoints. Use service contracts (interfaces) to expose functionality through APIs.

## Area Codes

```php
\Magento\Framework\App\Area::AREA_WEBAPI_REST // 'webapi_rest'
\Magento\Framework\App\Area::AREA_WEBAPI_SOAP // 'webapi_soap'
```

## API Configuration (webapi.xml)

```xml
<!-- etc/webapi.xml -->
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">

    <!-- GET single item -->
    <route url="/V1/custom/items/:id" method="GET">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="getById"/>
        <resources>
            <resource ref="Vendor_Module::item_view"/>
        </resources>
    </route>

    <!-- GET list with search criteria -->
    <route url="/V1/custom/items" method="GET">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="getList"/>
        <resources>
            <resource ref="Vendor_Module::item_view"/>
        </resources>
    </route>

    <!-- POST create -->
    <route url="/V1/custom/items" method="POST">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="save"/>
        <resources>
            <resource ref="Vendor_Module::item_save"/>
        </resources>
    </route>

    <!-- PUT update -->
    <route url="/V1/custom/items/:id" method="PUT">
        <service class="Vendor\Module\Api\ItemManagementInterface" method="update"/>
        <resources>
            <resource ref="Vendor_Module::item_save"/>
        </resources>
    </route>

    <!-- DELETE -->
    <route url="/V1/custom/items/:id" method="DELETE">
        <service class="Vendor\Module\Api\ItemRepositoryInterface" method="deleteById"/>
        <resources>
            <resource ref="Vendor_Module::item_delete"/>
        </resources>
    </route>

    <!-- Anonymous endpoint -->
    <route url="/V1/custom/public-data" method="GET">
        <service class="Vendor\Module\Api\PublicDataInterface" method="getData"/>
        <resources>
            <resource ref="anonymous"/>
        </resources>
    </route>

    <!-- Customer self-service endpoint -->
    <route url="/V1/custom/my-data" method="GET">
        <service class="Vendor\Module\Api\CustomerDataInterface" method="getMyData"/>
        <resources>
            <resource ref="self"/>
        </resources>
    </route>

</routes>
```

## Service Contract Interface

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Api;

use Vendor\Module\Api\Data\ItemInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;

/**
 * Item Repository Interface
 *
 * @api
 */
interface ItemRepositoryInterface
{
    /**
     * Get item by ID
     *
     * @param int $id
     * @return \Vendor\Module\Api\Data\ItemInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): ItemInterface;

    /**
     * Get item list
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Vendor\Module\Api\Data\ItemSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): Data\ItemSearchResultsInterface;

    /**
     * Save item
     *
     * @param \Vendor\Module\Api\Data\ItemInterface $item
     * @return \Vendor\Module\Api\Data\ItemInterface
     * @throws CouldNotSaveException
     */
    public function save(ItemInterface $item): ItemInterface;

    /**
     * Delete item by ID
     *
     * @param int $id
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $id): bool;
}
```

## Data Interface

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Item Data Interface
 *
 * @api
 */
interface ItemInterface extends ExtensibleDataInterface
{
    public const ID = 'entity_id';
    public const NAME = 'name';
    public const STATUS = 'status';

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     */
    public function setId(int $id): self;

    /**
     * Get name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Get status
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Set status
     *
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self;

    /**
     * Get extension attributes
     *
     * @return \Vendor\Module\Api\Data\ItemExtensionInterface|null
     */
    public function getExtensionAttributes(): ?ItemExtensionInterface;

    /**
     * Set extension attributes
     *
     * @param \Vendor\Module\Api\Data\ItemExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(ItemExtensionInterface $extensionAttributes): self;
}
```

## Search Results Interface

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Item Search Results Interface
 *
 * @api
 */
interface ItemSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get items
     *
     * @return \Vendor\Module\Api\Data\ItemInterface[]
     */
    public function getItems(): array;

    /**
     * Set items
     *
     * @param \Vendor\Module\Api\Data\ItemInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
```

## Authentication Types

### Token-Based Authentication

```bash
# Admin token
curl -X POST "https://magento.test/rest/V1/integration/admin/token" \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"admin123"}'

# Customer token
curl -X POST "https://magento.test/rest/V1/integration/customer/token" \
    -H "Content-Type: application/json" \
    -d '{"username":"customer@example.com","password":"password123"}'

# Use token
curl -X GET "https://magento.test/rest/V1/custom/items/1" \
    -H "Authorization: Bearer <token>"
```

### OAuth-Based Authentication

For third-party integrations, configure in:
System > Integrations

## Resource Authorization

| Resource | Description |
|----------|-------------|
| `anonymous` | No authentication required |
| `self` | Customer token required, access own data |
| `Vendor_Module::resource` | Admin token with specific ACL |

## API Response Formats

### Success Response

```json
{
    "entity_id": 1,
    "name": "Item Name",
    "status": 1,
    "extension_attributes": {}
}
```

### Error Response

```json
{
    "message": "No such entity with id = %1",
    "parameters": ["999"]
}
```

## Input/Output Processing

### Transforming Request Data

```php
// The WebAPI framework automatically:
// 1. Deserializes JSON/XML to arrays
// 2. Converts arrays to Data Objects via setters
// 3. Passes to service method

// Input: {"item": {"name": "Test", "status": 1}}
// Becomes: $item with name="Test", status=1
```

### Complex Types

For arrays of objects, use proper PHPDoc:

```php
/**
 * @param \Vendor\Module\Api\Data\ItemInterface[] $items
 * @return \Vendor\Module\Api\Data\ResultInterface[]
 */
public function processItems(array $items): array;
```

## Versioning

Use version prefix in URLs:
- `/V1/` - Version 1
- `/V2/` - Version 2 (breaking changes)

## Rate Limiting

Configure in `env.php`:

```php
'webapi' => [
    'throttle' => [
        'enabled' => true,
        'rate' => 10,  // requests
        'time' => 60,  // seconds
    ]
]
```

## Best Practices

1. **Use service contracts** - Never expose models directly
2. **Version your API** - Use /V1/, /V2/ prefixes
3. **Document with PHPDoc** - Required for type conversion
4. **Use `@api` annotation** - Mark stable public interfaces
5. **Return proper types** - Always return typed data interfaces
6. **Handle exceptions** - Use Magento exception classes
7. **Implement proper ACL** - Never use `anonymous` for sensitive data
8. **Test with integration tests** - Use WebapiAbstract
