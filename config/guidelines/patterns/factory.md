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
    /**
     * @param EntityFactory $entityFactory
     */
    public function __construct(
        private readonly EntityFactory $entityFactory
    ) {
    }

    /**
     * @param array $data
     * @return EntityInterface
     */
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
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected \Magento\Framework\ObjectManagerInterface $objectManager;

    /**
     * @var string
     */
    protected string $instanceName;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        string $instanceName = \Vendor\Module\Model\Entity::class
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * @param array $data
     * @return Entity
     */
    public function create(array $data = []): Entity
    {
        return $this->objectManager->create($this->instanceName, $data);
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
    /**
     * @param ObjectManagerInterface $objectManager
     * @param EntityValidator $validator
     * @param ConfigProvider $config
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly EntityValidator $validator,
        private readonly ConfigProvider $config
    ) {
    }

    /**
     * @param array $data
     * @return EntityInterface
     */
    public function create(array $data = []): EntityInterface
    {
        // Apply defaults
        $data = array_merge($this->config->getDefaults(), $data);

        // Validate
        $this->validator->validate($data);

        // Create with processed data
        return $this->objectManager->create(Entity::class, ['data' => $data]);
    }

    /**
     * @param array $apiData
     * @return EntityInterface
     */
    public function createFromApi(array $apiData): EntityInterface
    {
        $data = $this->transformApiData($apiData);
        return $this->create($data);
    }

    /**
     * @param array $apiData
     * @return array
     */
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
    /**
     * @var array
     */
    private array $data = [];

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->data['name'] = $name;
        return $this;
    }

    /**
     * @param bool $status
     * @return self
     */
    public function setStatus(bool $status): self
    {
        $this->data['status'] = $status;
        return $this;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->data['description'] = $description;
        return $this;
    }

    /**
     * @return EntityInterface
     */
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
