# Magento 2 Admin Panel Development Guidelines

## Overview

The adminhtml area handles the Magento Admin Panel interface. It uses UI Components for grids and forms, provides ACL for access control, and follows specific conventions for admin functionality.

## Area Code

```php
\Magento\Framework\App\Area::AREA_ADMINHTML // 'adminhtml'
```

## Directory Structure

```
app/code/Vendor/Module/
├── Controller/
│   └── Adminhtml/
│       └── Entity/
│           ├── Index.php
│           ├── Edit.php
│           ├── Save.php
│           └── Delete.php
├── view/
│   └── adminhtml/
│       ├── layout/
│       │   └── vendor_module_entity_index.xml
│       ├── templates/
│       ├── ui_component/
│       │   ├── vendor_module_entity_listing.xml
│       │   └── vendor_module_entity_form.xml
│       └── web/
└── etc/
    └── adminhtml/
        ├── routes.xml
        ├── menu.xml
        └── di.xml
```

## Routes Configuration

```xml
<!-- etc/adminhtml/routes.xml -->
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

## Admin Menu

```xml
<!-- etc/adminhtml/menu.xml -->
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Backend:etc/menu.xsd">
    <menu>
        <!-- Parent menu item -->
        <add id="Vendor_Module::main"
             title="Custom Module"
             module="Vendor_Module"
             resource="Vendor_Module::main"
             sortOrder="50"/>

        <!-- Child menu item -->
        <add id="Vendor_Module::entity"
             title="Manage Entities"
             module="Vendor_Module"
             parent="Vendor_Module::main"
             action="vendor_module/entity/index"
             resource="Vendor_Module::entity"
             sortOrder="10"/>
    </menu>
</config>
```

## ACL Configuration

```xml
<!-- etc/acl.xml -->
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Acl/etc/acl.xsd">
    <acl>
        <resources>
            <resource id="Magento_Backend::admin">
                <resource id="Vendor_Module::main" title="Custom Module" sortOrder="50">
                    <resource id="Vendor_Module::entity" title="Manage Entities" sortOrder="10">
                        <resource id="Vendor_Module::entity_view" title="View Entities"/>
                        <resource id="Vendor_Module::entity_save" title="Save Entities"/>
                        <resource id="Vendor_Module::entity_delete" title="Delete Entities"/>
                    </resource>
                </resource>
            </resource>
        </resources>
    </acl>
</config>
```

## Admin Controller

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Controller\Adminhtml\Entity;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Vendor_Module::entity_view';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Vendor_Module::entity');
        $resultPage->getConfig()->getTitle()->prepend(__('Manage Entities'));

        return $resultPage;
    }
}
```

### Save Controller

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Controller\Adminhtml\Entity;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Vendor\Module\Api\RepositoryInterface;
use Vendor\Module\Api\Data\EntityInterfaceFactory;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Vendor_Module::entity_save';

    public function __construct(
        Context $context,
        private readonly RepositoryInterface $repository,
        private readonly EntityInterfaceFactory $entityFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $entityId = $data['entity_id'] ?? null;

            if ($entityId) {
                $entity = $this->repository->getById((int) $entityId);
            } else {
                $entity = $this->entityFactory->create();
            }

            $entity->setData($data);
            $this->repository->save($entity);

            $this->messageManager->addSuccessMessage(__('Entity saved successfully.'));

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['id' => $entity->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/edit', ['id' => $entityId]);
        }
    }
}
```

## UI Component Grid

```xml
<!-- view/adminhtml/ui_component/vendor_module_entity_listing.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">

    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">vendor_module_entity_listing.vendor_module_entity_listing_data_source</item>
        </item>
    </argument>

    <settings>
        <spinner>vendor_module_entity_columns</spinner>
        <deps>
            <dep>vendor_module_entity_listing.vendor_module_entity_listing_data_source</dep>
        </deps>
        <buttons>
            <button name="add">
                <url path="*/*/new"/>
                <class>primary</class>
                <label translate="true">Add New Entity</label>
            </button>
        </buttons>
    </settings>

    <dataSource name="vendor_module_entity_listing_data_source" component="Magento_Ui/js/grid/provider">
        <settings>
            <storageConfig>
                <param name="indexField" xsi:type="string">entity_id</param>
            </storageConfig>
            <updateUrl path="mui/index/render"/>
        </settings>
        <aclResource>Vendor_Module::entity_view</aclResource>
        <dataProvider class="Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider" name="vendor_module_entity_listing_data_source">
            <settings>
                <requestFieldName>id</requestFieldName>
                <primaryFieldName>entity_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>

    <listingToolbar name="listing_top">
        <settings>
            <sticky>true</sticky>
        </settings>
        <bookmark name="bookmarks"/>
        <columnsControls name="columns_controls"/>
        <filterSearch name="fulltext"/>
        <filters name="listing_filters"/>
        <massaction name="listing_massaction">
            <action name="delete">
                <settings>
                    <url path="vendor_module/entity/massDelete"/>
                    <type>delete</type>
                    <label translate="true">Delete</label>
                    <confirm>
                        <message translate="true">Are you sure you want to delete selected items?</message>
                        <title translate="true">Delete items</title>
                    </confirm>
                </settings>
            </action>
        </massaction>
        <paging name="listing_paging"/>
    </listingToolbar>

    <columns name="vendor_module_entity_columns">
        <selectionsColumn name="ids">
            <settings>
                <indexField>entity_id</indexField>
            </settings>
        </selectionsColumn>
        <column name="entity_id">
            <settings>
                <filter>textRange</filter>
                <label translate="true">ID</label>
                <sorting>asc</sorting>
            </settings>
        </column>
        <column name="name">
            <settings>
                <filter>text</filter>
                <label translate="true">Name</label>
            </settings>
        </column>
        <column name="status" component="Magento_Ui/js/grid/columns/select">
            <settings>
                <filter>select</filter>
                <options class="Vendor\Module\Model\Source\Status"/>
                <dataType>select</dataType>
                <label translate="true">Status</label>
            </settings>
        </column>
        <actionsColumn name="actions" class="Vendor\Module\Ui\Component\Listing\Column\Actions">
            <settings>
                <indexField>entity_id</indexField>
            </settings>
        </actionsColumn>
    </columns>

</listing>
```

## UI Component Form

```xml
<!-- view/adminhtml/ui_component/vendor_module_entity_form.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<form xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">

    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">vendor_module_entity_form.vendor_module_entity_form_data_source</item>
        </item>
        <item name="label" xsi:type="string" translate="true">Entity Information</item>
        <item name="template" xsi:type="string">templates/form/collapsible</item>
    </argument>

    <settings>
        <buttons>
            <button name="back" class="Vendor\Module\Block\Adminhtml\Entity\Edit\BackButton"/>
            <button name="delete" class="Vendor\Module\Block\Adminhtml\Entity\Edit\DeleteButton"/>
            <button name="save" class="Vendor\Module\Block\Adminhtml\Entity\Edit\SaveButton"/>
            <button name="save_and_continue" class="Vendor\Module\Block\Adminhtml\Entity\Edit\SaveAndContinueButton"/>
        </buttons>
        <namespace>vendor_module_entity_form</namespace>
        <dataScope>data</dataScope>
        <deps>
            <dep>vendor_module_entity_form.vendor_module_entity_form_data_source</dep>
        </deps>
    </settings>

    <dataSource name="vendor_module_entity_form_data_source">
        <argument name="data" xsi:type="array">
            <item name="js_config" xsi:type="array">
                <item name="component" xsi:type="string">Magento_Ui/js/form/provider</item>
            </item>
        </argument>
        <settings>
            <submitUrl path="vendor_module/entity/save"/>
        </settings>
        <dataProvider class="Vendor\Module\Model\EntityDataProvider" name="vendor_module_entity_form_data_source">
            <settings>
                <requestFieldName>id</requestFieldName>
                <primaryFieldName>entity_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>

    <fieldset name="general">
        <settings>
            <label translate="true">General</label>
        </settings>
        <field name="entity_id" formElement="hidden">
            <settings>
                <dataType>text</dataType>
            </settings>
        </field>
        <field name="name" formElement="input">
            <settings>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
                <dataType>text</dataType>
                <label translate="true">Name</label>
            </settings>
        </field>
        <field name="status" formElement="select">
            <settings>
                <dataType>int</dataType>
                <label translate="true">Status</label>
            </settings>
            <formElements>
                <select>
                    <settings>
                        <options class="Vendor\Module\Model\Source\Status"/>
                    </settings>
                </select>
            </formElements>
        </field>
    </fieldset>

</form>
```

## Best Practices

1. **Always use ACL** - Protect every admin action
2. **Use UI Components** - Prefer over block-based grids/forms
3. **Validate form key** - For all POST requests
4. **Use message manager** - For user feedback
5. **Follow naming conventions** - Consistent route/layout/component names
6. **Keep controllers thin** - Move logic to services
7. **Use data providers** - For UI component data handling
