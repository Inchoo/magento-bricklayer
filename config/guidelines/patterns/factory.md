# Factory Pattern Guidelines

## Overview

Factories create new instances of objects. Magento auto-generates factory classes for any class with the `Factory` suffix.

## Factory Types

| Type | Purpose | Example |
|------|---------|---------|
| Auto-generated | Create model instances | `ProductFactory` |
| Custom | Complex object creation | `CompositeFactory` |
| Builder | Fluent object construction | `SearchCriteriaBuilder` |

## Auto-Generated Factories

Magento generates factories automatically when you inject a class with `Factory` suffix:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model;

use Vendor\Module\Model\EntityFactory;
use Vendor\Module\Api\Data\EntityInterface;

class EntityService
{
    public function __construct(
        private readonly EntityFactory $entityFactory
    ) {
    }

    public function createEntity(array $data): EntityInterface
    {
        /** @var Entity $entity */
        $entity = $this->entityFactory->create(['data' => $data]);
        return $entity;
    }
}
```

## Generated Factory Structure

Auto-generated in `generated/code/`:

```php
<?php
namespace Vendor\Module\Model;

class EntityFactory
{
    protected $_objectManager;
    protected $_instanceName;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        $instanceName = \Vendor\Module\Model\Entity::class
    ) {
        $this->_objectManager = $objectManager;
        $this->_instanceName = $instanceName;
    }

    public function create(array $data = []): Entity
    {
        return $this->_objectManager->create($this->_instanceName, $data);
    }
}
```

## Factory Methods

| Method | Purpose | When to Use |
|--------|---------|-------------|
| `create()` | New instance each call | Data objects, entities |
| `get()` (Singleton) | Same instance | Services, helpers |

## Constructor Arguments

Pass constructor arguments via the `create()` method:

```php
// Entity constructor: __construct(array $data = [])
$entity = $this->entityFactory->create(['data' => ['name' => 'Test']]);

// Entity constructor: __construct(string $name, int $id)
$entity = $this->entityFactory->create(['name' => 'Test', 'id' => 1]);
```

## Interface Factories

For interface-based creation, configure in di.xml:

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <preference for="Vendor\Module\Api\Data\EntityInterfaceFactory"
                type="Vendor\Module\Model\EntityFactory"/>

</config>
```

Or use virtual type:

```xml
<virtualType name="Vendor\Module\Api\Data\EntityInterfaceFactory"
             type="Magento\Framework\ObjectManager\TMap\Factory">
    <arguments>
        <argument name="instanceName" xsi:type="string">
            Vendor\Module\Model\Entity
        </argument>
    </arguments>
</virtualType>
```

## Custom Factory

For complex object creation logic:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Framework\ObjectManagerInterface;
use Vendor\Module\Api\Data\EntityInterface;
use Vendor\Module\Model\Validator\EntityValidator;

class EntityFactory
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly EntityValidator $validator,
        private readonly ConfigProvider $config
    ) {
    }

    public function create(array $data = []): EntityInterface
    {
        // Apply defaults
        $data = array_merge($this->config->getDefaults(), $data);

        // Validate
        $this->validator->validate($data);

        // Create with processed data
        return $this->objectManager->create(Entity::class, ['data' => $data]);
    }

    public function createFromApi(array $apiData): EntityInterface
    {
        $data = $this->transformApiData($apiData);
        return $this->create($data);
    }

    private function transformApiData(array $apiData): array
    {
        // Transform external format to internal
        return [
            'name' => $apiData['title'] ?? '',
            'status' => $apiData['active'] ?? false,
        ];
    }
}
```

## Builder Pattern

For complex objects with many optional parameters:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model;

class EntityBuilder
{
    private array $data = [];

    public function setName(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function setStatus(bool $status): self
    {
        $this->data['status'] = $status;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->data['description'] = $description;
        return $this;
    }

    public function build(): EntityInterface
    {
        $entity = new Entity($this->data);
        $this->data = []; // Reset for next build
        return $entity;
    }
}

// Usage
$entity = $builder
    ->setName('Test')
    ->setStatus(true)
    ->build();
```

## Best Practices

1. **Inject factories** - Never use ObjectManager directly
2. **Use for data objects** - Models, DTOs, value objects
3. **Type hint the result** - `@return EntityInterface`
4. **Keep factories simple** - Complex logic in services
5. **Consider builders** - For objects with many parameters
6. **Document expected data** - PHPDoc the `$data` parameter
