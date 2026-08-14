<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;

/**
 * Provides Magento 2 reference documentation for events, DI patterns, and ACL.
 *
 * Layout handle reference is intentionally not provided here: the `layout-inspect`
 * tool (list mode) enumerates the registered handles at runtime, so there is no
 * hand-maintained handle catalogue to drift out of date.
 */
class ReferenceResource
{
    /**
     * Returns events reference.
     *
     * @return string Markdown content with events reference
     */
    #[McpResource(
        uri: 'magento://reference/events',
        name: 'events_reference',
        description: 'Magento 2 events reference - common events and observer patterns',
        mimeType: 'text/markdown'
    )]
    public function getEventsReference(): string
    {
        // phpcs:disable Generic.Files.LineLength -- markdown nowdoc content
        return <<<'MARKDOWN'
# Magento Events Reference

## Event System Overview

Events allow modules to react to actions without modifying core code. Observers are triggered when events are dispatched.

## Commonly Used Events

### Catalog Events

| Event | Trigger | Typical Use |
|-------|---------|-------------|
| `catalog_product_save_before` | Before product save | Validate/modify data |
| `catalog_product_save_after` | After product save | Index, cache, external sync |
| `catalog_product_load_after` | After product load | Add dynamic data |
| `catalog_category_save_after` | After category save | Update navigation cache |
| `catalog_product_view` | Product page view | Analytics, recently viewed |

### Sales Events

| Event | Trigger | Typical Use |
|-------|---------|-------------|
| `sales_order_place_before` | Before order placement | Validation, inventory check |
| `sales_order_place_after` | After order placement | Notifications, integrations |
| `checkout_submit_all_after` | After checkout complete | Post-order processing |
| `sales_order_invoice_pay` | When invoice is paid | Payment processing |
| `sales_order_shipment_save_after` | After shipment created | Tracking, notifications |

### Customer Events

| Event | Trigger | Typical Use |
|-------|---------|-------------|
| `customer_register_success` | After registration | Welcome email, integrations |
| `customer_login` | After login | Session setup, logging |
| `customer_logout` | After logout | Session cleanup |
| `customer_save_after` | After customer save | CRM sync, validation |

### Cart Events

| Event | Trigger | Typical Use |
|-------|---------|-------------|
| `checkout_cart_add_product_complete` | After add to cart | Tracking, recommendations |
| `checkout_cart_update_items_after` | After cart update | Recalculate totals |
| `sales_quote_save_after` | After quote save | Cart abandonment tracking |

## Observer Configuration

### etc/events.xml (Global)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">

    <event name="catalog_product_save_after">
        <observer name="vendor_module_product_save"
                  instance="Vendor\Module\Observer\ProductSaveObserver"/>
    </event>

</config>
```

### etc/frontend/events.xml (Frontend Only)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">

    <event name="customer_login">
        <observer name="vendor_module_customer_login"
                  instance="Vendor\Module\Observer\CustomerLoginObserver"/>
    </event>

</config>
```

## Observer Implementation

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class ProductSaveObserver implements ObserverInterface
{
    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getData('product');

        if ($product === null) {
            return;
        }

        $this->logger->info('Product saved', [
            'sku' => $product->getSku(),
            'id' => $product->getId(),
        ]);

        // Your logic here
    }
}
```

## Dispatching Custom Events

```php
<?php
use Magento\Framework\Event\ManagerInterface;

class CustomService
{
    /**
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        private readonly ManagerInterface $eventManager
    ) {
    }

    /**
     * @param array $data
     * @return void
     */
    public function process(array $data): void
    {
        // Dispatch before event
        $this->eventManager->dispatch(
            'vendor_module_custom_process_before',
            ['data' => $data]
        );

        // Process...

        // Dispatch after event
        $this->eventManager->dispatch(
            'vendor_module_custom_process_after',
            ['data' => $data, 'result' => $result]
        );
    }
}
```

## Best Practices

1. **Use specific event names** - Include module prefix
2. **Keep observers lightweight** - Offload heavy work to services
3. **Don't modify $observer directly** - Get data, process, set back
4. **Handle exceptions** - Don't break the event chain
5. **Use area-specific events** - frontend, adminhtml, webapi
MARKDOWN;
        // phpcs:enable Generic.Files.LineLength
    }

    /**
     * Returns DI patterns reference.
     *
     * @return string Markdown content with DI patterns reference
     */
    #[McpResource(
        uri: 'magento://reference/di-patterns',
        name: 'di_patterns_reference',
        description: 'Magento 2 dependency injection patterns and configuration',
        mimeType: 'text/markdown'
    )]
    public function getDiPatternsReference(): string
    {
        return <<<'MARKDOWN'
# Dependency Injection Patterns Reference

## Core Concepts

| Pattern | Purpose |
|---------|---------|
| Preference | Replace implementation class |
| Plugin | Intercept method calls |
| Virtual Type | Configure class without creating new file |
| Type Arguments | Pass constructor arguments |

## Preference

Replace interface implementation or rewrite class.

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <!-- Interface implementation -->
    <preference for="Vendor\Module\Api\ServiceInterface"
                type="Vendor\Module\Model\Service"/>

    <!-- Class rewrite (use sparingly) -->
    <preference for="Magento\Catalog\Model\Product"
                type="Vendor\Module\Model\Product"/>

</config>
```

## Plugin

Modify method behavior without replacing class.

```xml
<type name="Magento\Catalog\Api\ProductRepositoryInterface">
    <plugin name="vendor_module_product_repo_plugin"
            type="Vendor\Module\Plugin\ProductRepositoryPlugin"
            sortOrder="10"
            disabled="false"/>
</type>
```

## Virtual Type

Create configured variant without new PHP class.

```xml
<!-- Custom logger -->
<virtualType name="Vendor\Module\Logger\Virtual" type="Magento\Framework\Logger\Monolog">
    <arguments>
        <argument name="name" xsi:type="string">vendor_module</argument>
        <argument name="handlers" xsi:type="array">
            <item name="system" xsi:type="object">Vendor\Module\Logger\Handler</item>
        </argument>
    </arguments>
</virtualType>

<!-- Use virtual type -->
<type name="Vendor\Module\Model\Service">
    <arguments>
        <argument name="logger" xsi:type="object">Vendor\Module\Logger\Virtual</argument>
    </arguments>
</type>
```

## Type Arguments

### Scalar Types

```xml
<type name="Vendor\Module\Model\Config">
    <arguments>
        <argument name="apiKey" xsi:type="string">default_key</argument>
        <argument name="timeout" xsi:type="number">30</argument>
        <argument name="enabled" xsi:type="boolean">true</argument>
        <argument name="nullValue" xsi:type="null"/>
    </arguments>
</type>
```

### Object Types

```xml
<type name="Vendor\Module\Model\Service">
    <arguments>
        <!-- Direct class reference -->
        <argument name="helper" xsi:type="object">Vendor\Module\Helper\Data</argument>

        <!-- Non-shared (new instance each time) -->
        <argument name="factory" xsi:type="object" shared="false">
            Vendor\Module\Model\EntityFactory
        </argument>
    </arguments>
</type>
```

### Array Types

```xml
<type name="Vendor\Module\Model\Processor">
    <arguments>
        <argument name="processors" xsi:type="array">
            <item name="first" xsi:type="object">Vendor\Module\Model\Processor\First</item>
            <item name="second" xsi:type="object">Vendor\Module\Model\Processor\Second</item>
        </argument>
        <argument name="config" xsi:type="array">
            <item name="key1" xsi:type="string">value1</item>
            <item name="key2" xsi:type="number">100</item>
        </argument>
    </arguments>
</type>
```

### Init Parameters

```xml
<type name="Vendor\Module\Model\Config">
    <arguments>
        <argument name="data" xsi:type="init_parameter">
            Vendor\Module\Model\Config::DEFAULT_CONFIG
        </argument>
    </arguments>
</type>
```

## Area-Specific Configuration

```
etc/di.xml           # Global
etc/frontend/di.xml  # Frontend only
etc/adminhtml/di.xml # Admin only
etc/webapi_rest/di.xml # REST API only
etc/graphql/di.xml   # GraphQL only
```

## Factories

Auto-generated factories for creating instances.

```php
<?php
namespace Vendor\Module\Model;

class Service
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
     * @return Entity
     */
    public function createEntity(array $data): Entity
    {
        return $this->entityFactory->create(['data' => $data]);
    }
}
```

## Proxies

Lazy-load heavy dependencies.

```xml
<type name="Vendor\Module\Model\Service">
    <arguments>
        <argument name="heavyDependency" xsi:type="object">
            Vendor\Module\Model\Heavy\Proxy
        </argument>
    </arguments>
</type>
```

## Best Practices

1. **Inject interfaces** - Not concrete classes
2. **Use constructor injection** - Not ObjectManager
3. **Keep DI config minimal** - Configure only what's necessary
4. **Use virtual types** - Instead of empty subclasses
5. **Prefer plugins over preferences** - More maintainable
6. **Use proxies for heavy dependencies** - Improves startup time
MARKDOWN;
    }

    /**
     * Returns ACL reference.
     *
     * @return string Markdown content with ACL reference
     */
    #[McpResource(
        uri: 'magento://reference/acl',
        name: 'acl_reference',
        description: 'Magento 2 ACL (Access Control List) configuration reference',
        mimeType: 'text/markdown'
    )]
    public function getAclReference(): string
    {
        return <<<'MARKDOWN'
# ACL (Access Control List) Reference

## ACL Configuration

### etc/acl.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Vendor_Module::main" title="Module Name" sortOrder="100">
                    <resource id="Vendor_Module::manage" title="Manage Entities" sortOrder="10">
                        <resource id="Vendor_Module::create" title="Create" sortOrder="10"/>
                        <resource id="Vendor_Module::edit" title="Edit" sortOrder="20"/>
                        <resource id="Vendor_Module::delete" title="Delete" sortOrder="30"/>
                    </resource>
                    <resource id="Vendor_Module::config" title="Configuration" sortOrder="20"/>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
```

## Common Parent Resources

| Resource ID | Description |
|------------|-------------|
| `Magento_Backend::admin` | Top-level admin access |
| `Magento_Backend::stores` | Stores configuration |
| `Magento_Backend::system` | System configuration |
| `Magento_Backend::content` | Content management |
| `Magento_Catalog::catalog` | Catalog management |
| `Magento_Sales::sales` | Sales management |
| `Magento_Customer::customer` | Customer management |

## Using ACL in Controllers

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Controller\Adminhtml\Entity;

use Magento\Backend\App\Action;

class Index extends Action
{
    /**
     * ACL resource for this controller
     */
    public const ADMIN_RESOURCE = 'Vendor_Module::manage';

    /**
     * @return mixed
     */
    public function execute()
    {
        // Controller logic
    }
}
```

## Using ACL in System Configuration

### etc/adminhtml/system.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <system>
        <section id="vendor_module" translate="label" sortOrder="100"
                 showInDefault="1" showInWebsite="1" showInStore="1">
            <label>Module Settings</label>
            <tab>general</tab>
            <resource>Vendor_Module::config</resource>
            <group id="general" translate="label" sortOrder="10"
                   showInDefault="1" showInWebsite="1" showInStore="1">
                <label>General Settings</label>
                <field id="enabled" translate="label" type="select" sortOrder="10"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Enabled</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>
            </group>
        </section>
    </system>
</config>
```

## Using ACL in Menu

### etc/adminhtml/menu.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend:etc/menu.xsd">
    <menu>
        <add id="Vendor_Module::main"
             title="Module Name"
             module="Vendor_Module"
             sortOrder="100"
             resource="Vendor_Module::main"/>

        <add id="Vendor_Module::manage"
             title="Manage Entities"
             module="Vendor_Module"
             sortOrder="10"
             parent="Vendor_Module::main"
             action="vendor_module/entity/index"
             resource="Vendor_Module::manage"/>

        <add id="Vendor_Module::config"
             title="Configuration"
             module="Vendor_Module"
             sortOrder="20"
             parent="Vendor_Module::main"
             action="adminhtml/system_config/edit/section/vendor_module"
             resource="Vendor_Module::config"/>
    </menu>
</config>
```

## Using ACL in Web API

### etc/webapi.xml

```xml
<?xml version="1.0"?>
<routes xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Webapi:etc/webapi.xsd">

    <!-- Admin only -->
    <route url="/V1/vendor/entities" method="GET">
        <service class="Vendor\Module\Api\EntityRepositoryInterface" method="getList"/>
        <resources>
            <resource ref="Vendor_Module::manage"/>
        </resources>
    </route>

    <!-- Anonymous access -->
    <route url="/V1/vendor/public" method="GET">
        <service class="Vendor\Module\Api\PublicInterface" method="get"/>
        <resources>
            <resource ref="anonymous"/>
        </resources>
    </route>

    <!-- Customer self-service -->
    <route url="/V1/vendor/customer/me" method="GET">
        <service class="Vendor\Module\Api\CustomerInterface" method="getMyData"/>
        <resources>
            <resource ref="self"/>
        </resources>
    </route>

</routes>
```

## Checking ACL Programmatically

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\AuthorizationInterface;

class EntityBlock extends Template
{
    /**
     * @param Template\Context $context
     * @param AuthorizationInterface $authorization
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        private readonly AuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return bool
     */
    public function canEdit(): bool
    {
        return $this->authorization->isAllowed('Vendor_Module::edit');
    }

    /**
     * @return bool
     */
    public function canDelete(): bool
    {
        return $this->authorization->isAllowed('Vendor_Module::delete');
    }
}
```

## Best Practices

1. **Hierarchical structure** - Use parent-child relationships
2. **Granular permissions** - Separate create/edit/delete
3. **Use descriptive titles** - Clear for admin users
4. **Test all roles** - Verify with different permissions
5. **Document requirements** - Specify needed ACL in API docs
MARKDOWN;
    }
}
