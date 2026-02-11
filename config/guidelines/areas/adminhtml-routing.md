# Admin Panel: Routing, ACL & Controllers

> Related: See [adminhtml-ui.md](adminhtml-ui.md) for UI Component grids, forms, and admin best practices.

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
     * @return \Magento\Framework\View\Result\Page
     */
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

    /**
     * @param Context $context
     * @param RepositoryInterface $repository
     * @param EntityInterfaceFactory $entityFactory
     */
    public function __construct(
        Context $context,
        private readonly RepositoryInterface $repository,
        private readonly EntityInterfaceFactory $entityFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
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
