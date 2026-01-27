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

    public function getEntityId(): ?int;
    public function setEntityId(?int $entityId): self;
    public function getName(): ?string;
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
    public function save(CustomEntityInterface $entity): CustomEntityInterface;
    public function get(int $entityId): CustomEntityInterface;
    public function delete(CustomEntityInterface $entity): bool;
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
    public function __construct(
        private readonly ResourceCustomEntity $resource,
        private readonly CustomEntityFactory $factory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

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
public function beforeSetName(ProductInterface $subject, string $name): array
{
    $name = trim($name);
    return [$name]; // Return array of modified arguments
}
```

### After Plugin

```php
public function afterGetName(ProductInterface $subject, ?string $result): ?string
{
    return $result ? strtoupper($result) : null;
}
```

### Around Plugin

```php
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
