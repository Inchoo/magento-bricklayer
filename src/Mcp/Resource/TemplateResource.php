<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;

/**
 * Template Resource
 *
 * Provides code templates for common Magento 2 structures.
 */
class TemplateResource
{
    /**
     * Returns module structure template.
     *
     * @return string Markdown content with module template
     */
    #[McpResource(
        uri: 'magento://templates/module',
        name: 'module_template',
        description: 'Magento 2 module structure template with all required files',
        mimeType: 'text/markdown'
    )]
    public function getModuleTemplate(): string
    {
        return <<<'MARKDOWN'
# Module Structure Template

## Directory Structure

```
app/code/Vendor/Module/
├── Api/
│   └── Data/
│       └── EntityInterface.php
├── Block/
│   └── Example.php
├── Controller/
│   └── Index/
│       └── Index.php
├── etc/
│   ├── adminhtml/
│   │   ├── routes.xml
│   │   └── system.xml
│   ├── frontend/
│   │   └── routes.xml
│   ├── acl.xml
│   ├── config.xml
│   ├── di.xml
│   ├── events.xml
│   └── module.xml
├── Model/
│   ├── Entity.php
│   └── ResourceModel/
│       ├── Entity.php
│       └── Entity/
│           └── Collection.php
├── Setup/
│   └── Patch/
│       └── Data/
│           └── InitialData.php
├── view/
│   ├── adminhtml/
│   │   ├── layout/
│   │   └── templates/
│   └── frontend/
│       ├── layout/
│       │   └── vendor_module_index_index.xml
│       └── templates/
│           └── example.phtml
├── composer.json
└── registration.php
```

## Required Files

### registration.php

```php
<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Vendor_Module',
    __DIR__
);
```

### etc/module.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_Module">
        <sequence>
            <module name="Magento_Store"/>
            <module name="Magento_Catalog"/>
        </sequence>
    </module>
</config>
```

### composer.json

```json
{
    "name": "vendor/module",
    "description": "Module description",
    "type": "magento2-module",
    "license": "proprietary",
    "version": "1.0.0",
    "require": {
        "php": "^8.1",
        "magento/framework": "*"
    },
    "autoload": {
        "files": ["registration.php"],
        "psr-4": {
            "Vendor\\Module\\": ""
        }
    }
}
```

### etc/di.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <!-- Preference for interface -->
    <preference for="Vendor\Module\Api\Data\EntityInterface"
                type="Vendor\Module\Model\Entity"/>

</config>
```

### etc/acl.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Vendor_Module::main" title="Module Name" sortOrder="100">
                    <resource id="Vendor_Module::config" title="Configuration" sortOrder="10"/>
                    <resource id="Vendor_Module::manage" title="Manage Entities" sortOrder="20"/>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
```
MARKDOWN;
    }

    /**
     * Returns controller template.
     *
     * @return string Markdown content with controller template
     */
    #[McpResource(
        uri: 'magento://templates/controller',
        name: 'controller_template',
        description: 'Magento 2 controller templates for frontend and admin',
        mimeType: 'text/markdown'
    )]
    public function getControllerTemplate(): string
    {
        return <<<'MARKDOWN'
# Controller Templates

## Frontend Controller

### etc/frontend/routes.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
    <router id="standard">
        <route id="vendor_module" frontName="custom">
            <module name="Vendor_Module"/>
        </route>
    </router>
</config>
```

### Controller/Index/Index.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Result\Page;

class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        private readonly PageFactory $resultPageFactory
    ) {
    }

    /**
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Page Title'));
        return $resultPage;
    }
}
```

## Admin Controller

### etc/adminhtml/routes.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:App/etc/routes.xsd">
    <router id="admin">
        <route id="vendor_module" frontName="vendor_module">
            <module name="Vendor_Module"/>
        </route>
    </router>
</config>
```

### Controller/Adminhtml/Entity/Index.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Controller\Adminhtml\Entity;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Vendor_Module::manage';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Module::main');
        $resultPage->getConfig()->getTitle()->prepend(__('Manage Entities'));
        return $resultPage;
    }
}
```

## AJAX Controller

### Controller/Ajax/Process.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Controller\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;

class Process implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            $data = $this->request->getParam('data');
            // Process data...

            return $result->setData([
                'success' => true,
                'message' => __('Operation completed successfully.')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
```

## Result Types

| Class | Use Case |
|-------|----------|
| `PageFactory` | Full HTML page |
| `JsonFactory` | JSON response |
| `RedirectFactory` | Redirect to URL |
| `ForwardFactory` | Internal forward |
| `RawFactory` | Raw content |
MARKDOWN;
    }

    /**
     * Returns API endpoint template.
     *
     * @return string Markdown content with API template
     */
    #[McpResource(
        uri: 'magento://templates/api',
        name: 'api_template',
        description: 'Magento 2 REST API endpoint template',
        mimeType: 'text/markdown'
    )]
    public function getApiTemplate(): string
    {
        return <<<'MARKDOWN'
# REST API Endpoint Template

## etc/webapi.xml

```xml
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">

    <route url="/V1/vendor/entities" method="GET">
        <service class="Vendor\Module\Api\EntityRepositoryInterface" method="getList"/>
        <resources>
            <resource ref="Vendor_Module::manage"/>
        </resources>
    </route>

    <route url="/V1/vendor/entities/:id" method="GET">
        <service class="Vendor\Module\Api\EntityRepositoryInterface" method="getById"/>
        <resources>
            <resource ref="Vendor_Module::manage"/>
        </resources>
    </route>

    <route url="/V1/vendor/entities" method="POST">
        <service class="Vendor\Module\Api\EntityRepositoryInterface" method="save"/>
        <resources>
            <resource ref="Vendor_Module::manage"/>
        </resources>
    </route>

    <route url="/V1/vendor/entities/:id" method="DELETE">
        <service class="Vendor\Module\Api\EntityRepositoryInterface" method="deleteById"/>
        <resources>
            <resource ref="Vendor_Module::manage"/>
        </resources>
    </route>

</routes>
```

## Api/EntityRepositoryInterface.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api;

use Vendor\Module\Api\Data\EntityInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * @api
 */
interface EntityRepositoryInterface
{
    /**
     * @param int $id
     * @return \Vendor\Module\Api\Data\EntityInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): EntityInterface;

    /**
     * @param \Vendor\Module\Api\Data\EntityInterface $entity
     * @return \Vendor\Module\Api\Data\EntityInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(EntityInterface $entity): EntityInterface;

    /**
     * @param int $id
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $id): bool;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Vendor\Module\Api\Data\EntitySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
```

## Api/Data/EntityInterface.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Api\Data;

/**
 * @api
 */
interface EntityInterface
{
    public const ENTITY_ID = 'entity_id';
    public const NAME = 'name';
    public const STATUS = 'status';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return self
     */
    public function setEntityId(int $entityId): self;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): self;

    /**
     * @return bool
     */
    public function getStatus(): bool;

    /**
     * @param bool $status
     * @return self
     */
    public function setStatus(bool $status): self;
}
```
MARKDOWN;
    }

    /**
     * Returns model template.
     *
     * @return string Markdown content with model template
     */
    #[McpResource(
        uri: 'magento://templates/model',
        name: 'model_template',
        description: 'Magento 2 model, resource model, and collection template',
        mimeType: 'text/markdown'
    )]
    public function getModelTemplate(): string
    {
        return <<<'MARKDOWN'
# Model Template

## Model/Entity.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Framework\Model\AbstractModel;
use Vendor\Module\Api\Data\EntityInterface;
use Vendor\Module\Model\ResourceModel\Entity as EntityResource;

class Entity extends AbstractModel implements EntityInterface
{
    /**
     * @var string
     */
    protected string $_eventPrefix = 'vendor_module_entity';

    /**
     * @var string
     */
    protected string $_eventObject = 'entity';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(EntityResource::class);
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
     * @param int $entityId
     * @return self
     */
    public function setEntityId(int $entityId): self
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @return bool
     */
    public function getStatus(): bool
    {
        return (bool) $this->getData(self::STATUS);
    }

    /**
     * @param bool $status
     * @return self
     */
    public function setStatus(bool $status): self
    {
        return $this->setData(self::STATUS, $status);
    }
}
```

## Model/ResourceModel/Entity.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Entity extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('vendor_module_entity', 'entity_id');
    }
}
```

## Model/ResourceModel/Entity/Collection.php

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\ResourceModel\Entity;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Vendor\Module\Model\Entity;
use Vendor\Module\Model\ResourceModel\Entity as EntityResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected string $_idFieldName = 'entity_id';

    /**
     * @var string
     */
    protected string $_eventPrefix = 'vendor_module_entity_collection';

    /**
     * @var string
     */
    protected string $_eventObject = 'entity_collection';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Entity::class, EntityResource::class);
    }
}
```

## etc/db_schema.xml

```xml
<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">

    <table name="vendor_module_entity" resource="default" engine="innodb" comment="Custom Entity Table">
        <column xsi:type="int" name="entity_id" unsigned="true" nullable="false" identity="true"
                comment="Entity ID"/>
        <column xsi:type="varchar" name="name" nullable="false" length="255" comment="Name"/>
        <column xsi:type="smallint" name="status" unsigned="true" nullable="false" default="1"
                comment="Status"/>
        <column xsi:type="timestamp" name="created_at" on_update="false" nullable="false"
                default="CURRENT_TIMESTAMP" comment="Created At"/>
        <column xsi:type="timestamp" name="updated_at" on_update="true" nullable="false"
                default="CURRENT_TIMESTAMP" comment="Updated At"/>
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="entity_id"/>
        </constraint>
        <index referenceId="VENDOR_MODULE_ENTITY_NAME" indexType="btree">
            <column name="name"/>
        </index>
    </table>

</schema>
```
MARKDOWN;
    }
}
