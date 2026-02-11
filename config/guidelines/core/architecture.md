# Magento 2 Architecture Guidelines

## Service Contracts

Service contracts define the public API of a module through PHP interfaces.

### Data Interfaces

Located in `Api/Data/`, data interfaces define the structure of entities:

```php
namespace Vendor\Module\Api\Data;

/**
 * @api
 */
interface CustomEntityInterface extends ExtensibleDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const NAME = 'name';

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
}
```

### Service Interfaces

Located in `Api/`, service interfaces define business operations:

```php
namespace Vendor\Module\Api;

/**
 * @api
 */
interface CustomEntityRepositoryInterface
{
    /**
     * @param CustomEntityInterface $entity
     * @return CustomEntityInterface
     */
    public function save(CustomEntityInterface $entity): CustomEntityInterface;

    /**
     * @param int $entityId
     * @return CustomEntityInterface
     */
    public function get(int $entityId): CustomEntityInterface;

    /**
     * @param CustomEntityInterface $entity
     * @return bool
     */
    public function delete(CustomEntityInterface $entity): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
```

## Repository Pattern

Repositories provide a clean abstraction for data access.

### Implementation

```php
namespace Vendor\Module\Model;

class CustomEntityRepository implements CustomEntityRepositoryInterface
{
    /**
     * @param ResourceCustomEntity $resource
     * @param CustomEntityFactory $factory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        private readonly ResourceCustomEntity $resource,
        private readonly CustomEntityFactory $factory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
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
            throw new CouldNotSaveException(__('Could not save entity: %1', $e->getMessage()));
        }
        return $entity;
    }
}
```

## Plugins (Interceptors)

Plugins modify method behavior without changing the original class.

### Plugin Types

| Type | Method Prefix | Purpose |
|------|---------------|---------|
| Before | `before` | Modify input arguments |
| After | `after` | Modify return value |
| Around | `around` | Full control over execution |

### Before Plugin

```php
/**
 * @param ProductInterface $subject
 * @param string $name
 * @return array
 */
public function beforeSetName(ProductInterface $subject, string $name): array
{
    $name = trim($name);
    return [$name]; // Return array of modified arguments
}
```

### After Plugin

```php
/**
 * @param ProductInterface $subject
 * @param string|null $result
 * @return string|null
 */
public function afterGetName(ProductInterface $subject, ?string $result): ?string
{
    return $result ? strtoupper($result) : null;
}
```

### Around Plugin

```php
/**
 * @param ProductRepositoryInterface $subject
 * @param callable $proceed
 * @param ProductInterface $product
 * @return ProductInterface
 */
public function aroundSave(
    ProductRepositoryInterface $subject,
    callable $proceed,
    ProductInterface $product
): ProductInterface {
    // Before logic
    $result = $proceed($product);
    // After logic
    return $result;
}
```

### Plugin Best Practices

- Prefer before/after over around plugins
- Keep plugin logic focused and minimal
- Don't use plugins on final methods/classes
- Specify sort order when multiple plugins exist

## Event-Observer Pattern

React to events without modifying source code.

### Dispatching Events

```php
$this->eventManager->dispatch(
    'custom_entity_save_after',
    ['entity' => $entity, 'original_data' => $originalData]
);
```

### Observing Events

```php
namespace Vendor\Module\Observer;

class EntitySaveObserver implements ObserverInterface
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $entity = $observer->getData('entity');
        // React to the event
    }
}
```

### events.xml Configuration

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="custom_entity_save_after">
        <observer name="vendor_module_entity_save"
                  instance="Vendor\Module\Observer\EntitySaveObserver"/>
    </event>
</config>
```

## Module Areas

| Area | Purpose | Directory |
|------|---------|-----------|
| `global` | Applied everywhere | `etc/` |
| `frontend` | Storefront | `etc/frontend/` |
| `adminhtml` | Admin panel | `etc/adminhtml/` |
| `webapi_rest` | REST API | `etc/webapi_rest/` |
| `webapi_soap` | SOAP API | `etc/webapi_soap/` |
| `graphql` | GraphQL API | `etc/graphql/` |
| `crontab` | Cron execution | `etc/crontab/` |

## Dependency Injection

### Constructor Injection (Preferred)

```php
/**
 * @param ProductRepositoryInterface $productRepository
 * @param LoggerInterface $logger
 */
public function __construct(
    private readonly ProductRepositoryInterface $productRepository,
    private readonly LoggerInterface $logger
) {
}
```

### di.xml Configuration

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <!-- Preference for interface -->
    <preference for="Vendor\Module\Api\ServiceInterface"
                type="Vendor\Module\Model\Service"/>

    <!-- Virtual type -->
    <virtualType name="CustomLogger" type="Monolog\Logger">
        <arguments>
            <argument name="name" xsi:type="string">custom</argument>
        </arguments>
    </virtualType>

    <!-- Plugin registration -->
    <type name="Magento\Catalog\Api\ProductRepositoryInterface">
        <plugin name="vendor_custom_product"
                type="Vendor\Module\Plugin\ProductPlugin"/>
    </type>
</config>
```
