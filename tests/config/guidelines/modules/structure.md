# Magento 2 Module Structure

## Standard Module Layout

```
app/code/Vendor/Module/
├── Api/                           # Service contracts
│   ├── Data/                      # Data interfaces
│   │   └── EntityInterface.php
│   ├── EntityRepositoryInterface.php
│   └── ServiceInterface.php
├── Block/                         # View blocks
│   ├── Adminhtml/                 # Admin blocks
│   │   └── Entity/
│   │       ├── Edit.php
│   │       └── Grid.php
│   └── Entity/                    # Frontend blocks
│       ├── View.php
│       └── ListBlock.php
├── Console/                       # CLI commands
│   └── Command/
│       └── ProcessCommand.php
├── Controller/                    # Controllers
│   ├── Adminhtml/                 # Admin controllers
│   │   └── Entity/
│   │       ├── Index.php
│   │       ├── Edit.php
│   │       └── Save.php
│   └── Entity/                    # Frontend controllers
│       ├── Index.php
│       └── View.php
├── Cron/                          # Cron jobs
│   └── ProcessEntities.php
├── etc/                           # Configuration
│   ├── adminhtml/
│   │   ├── di.xml
│   │   ├── menu.xml
│   │   ├── routes.xml
│   │   └── system.xml
│   ├── frontend/
│   │   ├── di.xml
│   │   ├── routes.xml
│   │   └── sections.xml
│   ├── acl.xml
│   ├── config.xml
│   ├── crontab.xml
│   ├── db_schema.xml
│   ├── di.xml
│   ├── events.xml
│   ├── module.xml
│   └── webapi.xml
├── Helper/                        # Helper classes
│   └── Data.php
├── Model/                         # Business logic
│   ├── ResourceModel/             # Database operations
│   │   ├── Entity/
│   │   │   └── Collection.php
│   │   └── Entity.php
│   ├── Config/
│   │   └── Source/
│   │       └── Status.php
│   ├── Entity.php
│   └── EntityRepository.php
├── Observer/                      # Event observers
│   └── ProcessObserver.php
├── Plugin/                        # Plugins (interceptors)
│   └── ProductPlugin.php
├── Setup/                         # Installation/upgrade
│   └── Patch/
│       ├── Data/
│       │   └── AddDefaultData.php
│       └── Schema/
│           └── AddNewColumn.php
├── Test/                          # Tests
│   ├── Integration/
│   └── Unit/
├── Ui/                            # UI components
│   └── Component/
│       ├── Form/
│       └── Listing/
├── view/                          # View files
│   ├── adminhtml/
│   │   ├── layout/
│   │   ├── templates/
│   │   ├── ui_component/
│   │   └── web/
│   └── frontend/
│       ├── layout/
│       ├── templates/
│       └── web/
├── composer.json
└── registration.php
```

## Required Files

### registration.php

```php
<?php
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
            <module name="Magento_Catalog"/>
        </sequence>
    </module>
</config>
```

### composer.json

```json
{
    "name": "vendor/module-name",
    "description": "Module description",
    "type": "magento2-module",
    "require": {
        "php": "^8.1",
        "magento/framework": "^103.0"
    },
    "autoload": {
        "files": ["registration.php"],
        "psr-4": {
            "Vendor\\Module\\": ""
        }
    }
}
```

## Configuration Files Reference

| File | Purpose | Location |
|------|---------|----------|
| `module.xml` | Module declaration, dependencies | `etc/` |
| `di.xml` | Dependency injection config | `etc/`, `etc/{area}/` |
| `routes.xml` | URL routing | `etc/{area}/` |
| `acl.xml` | Access control resources | `etc/` |
| `config.xml` | Default configuration values | `etc/` |
| `system.xml` | Admin configuration UI | `etc/adminhtml/` |
| `events.xml` | Event observer registration | `etc/`, `etc/{area}/` |
| `crontab.xml` | Cron job definitions | `etc/` |
| `webapi.xml` | REST/SOAP API routes | `etc/` |
| `db_schema.xml` | Database schema | `etc/` |
| `menu.xml` | Admin menu items | `etc/adminhtml/` |

## View Directory Structure

### Layout Files

Located in `view/{area}/layout/`:

```
layout/
├── default.xml                    # Applied to all pages
├── vendor_module_entity_index.xml # Route-specific layout
└── vendor_module_entity_view.xml
```

### Templates

Located in `view/{area}/templates/`:

```
templates/
├── entity/
│   ├── list.phtml
│   └── view.phtml
└── widget/
    └── custom.phtml
```

### Static Files

Located in `view/{area}/web/`:

```
web/
├── css/
│   └── module.css
├── js/
│   └── custom.js
├── images/
│   └── icon.png
└── template/                      # Knockout.js templates
    └── component.html
```
