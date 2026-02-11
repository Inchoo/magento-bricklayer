# UI Components: Admin Grids

> Related: See [ui-component-forms](../ui-component-forms/SKILL.md) for admin form development, data providers, modifiers, and field types.

## Overview

Magento 2 UI Components provide a flexible, modular system for building admin interfaces. They combine XML configuration, JavaScript components, and PHP data providers to create interactive grids, forms, and custom UI elements.

## Architecture

```
UI Component
├── XML Configuration (definition)
├── JavaScript Component (behavior)
├── PHP Data Provider (data source)
├── PHP Modifier (data transformation)
└── Template (rendering)
```

## Core Component Types

| Type | Purpose | Common Use |
|------|---------|------------|
| `listing` | Data grids | Product/order grids |
| `form` | Edit forms | Entity edit pages |
| `modal` | Popup dialogs | Confirmations |
| `insertListing` | Embedded grids | Related entities |
| `insertForm` | Embedded forms | Inline editing |

## Creating an Admin Grid

### 1. Data Provider

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Ui\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class EntityListingDataProvider extends DataProvider
{
    /**
     * @return array
     */
    public function getData(): array
    {
        $data = parent::getData();

        // Transform data if needed
        foreach ($data['items'] as &$item) {
            $item['custom_field'] = $this->processField($item);
        }

        return $data;
    }
}
```

### 2. Collection Factory (di.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <virtualType name="Vendor\Module\Ui\DataProvider\Entity\ListingDataProvider"
                 type="Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider">
        <arguments>
            <argument name="collection" xsi:type="object" shared="false">
                Vendor\Module\Model\ResourceModel\Entity\Collection
            </argument>
            <argument name="filterPool" xsi:type="object" shared="false">
                Vendor\Module\Ui\DataProvider\Entity\FilterPool
            </argument>
        </arguments>
    </virtualType>

    <type name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory">
        <arguments>
            <argument name="collections" xsi:type="array">
                <item name="vendor_module_entity_listing_data_source" xsi:type="string">
                    Vendor\Module\Model\ResourceModel\Entity\Grid\Collection
                </item>
            </argument>
        </arguments>
    </type>

</config>
```

### 3. UI Component XML (view/adminhtml/ui_component/vendor_module_entity_listing.xml)

```xml
<?xml version="1.0"?>
<listing xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">

    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">
                vendor_module_entity_listing.vendor_module_entity_listing_data_source
            </item>
        </item>
    </argument>

    <settings>
        <buttons>
            <button name="add">
                <url path="*/*/new"/>
                <class>primary</class>
                <label translate="true">Add New Entity</label>
            </button>
        </buttons>
        <spinner>vendor_module_entity_columns</spinner>
        <deps>
            <dep>vendor_module_entity_listing.vendor_module_entity_listing_data_source</dep>
        </deps>
    </settings>

    <dataSource name="vendor_module_entity_listing_data_source" component="Magento_Ui/js/grid/provider">
        <settings>
            <storageConfig>
                <param name="indexField" xsi:type="string">entity_id</param>
            </storageConfig>
            <updateUrl path="mui/index/render"/>
        </settings>
        <aclResource>Vendor_Module::entity</aclResource>
        <dataProvider class="Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider"
                      name="vendor_module_entity_listing_data_source">
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
        <filters name="listing_filters">
            <settings>
                <templates>
                    <filters>
                        <select>
                            <param name="template" xsi:type="string">ui/grid/filters/elements/ui-select</param>
                            <param name="component" xsi:type="string">Magento_Ui/js/form/element/ui-select</param>
                        </select>
                    </filters>
                </templates>
            </settings>
        </filters>
        <massaction name="listing_massaction">
            <action name="delete">
                <settings>
                    <confirm>
                        <message translate="true">Are you sure you want to delete selected items?</message>
                        <title translate="true">Delete items</title>
                    </confirm>
                    <url path="vendor_module/entity/massDelete"/>
                    <type>delete</type>
                    <label translate="true">Delete</label>
                </settings>
            </action>
        </massaction>
        <paging name="listing_paging"/>
    </listingToolbar>

    <columns name="vendor_module_entity_columns">
        <selectionsColumn name="ids" sortOrder="10">
            <settings>
                <indexField>entity_id</indexField>
            </settings>
        </selectionsColumn>

        <column name="entity_id" sortOrder="20">
            <settings>
                <filter>textRange</filter>
                <label translate="true">ID</label>
                <sorting>asc</sorting>
            </settings>
        </column>

        <column name="name" sortOrder="30">
            <settings>
                <filter>text</filter>
                <label translate="true">Name</label>
            </settings>
        </column>

        <column name="status" component="Magento_Ui/js/grid/columns/select" sortOrder="40">
            <settings>
                <options class="Vendor\Module\Model\Entity\Source\Status"/>
                <filter>select</filter>
                <dataType>select</dataType>
                <label translate="true">Status</label>
            </settings>
        </column>

        <column name="created_at" class="Magento\Ui\Component\Listing\Columns\Date"
                component="Magento_Ui/js/grid/columns/date" sortOrder="50">
            <settings>
                <filter>dateRange</filter>
                <dataType>date</dataType>
                <label translate="true">Created</label>
            </settings>
        </column>

        <actionsColumn name="actions" class="Vendor\Module\Ui\Component\Listing\Column\EntityActions" sortOrder="100">
            <settings>
                <indexField>entity_id</indexField>
            </settings>
        </actionsColumn>
    </columns>

</listing>
```

### 4. Actions Column Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class EntityActions extends Column
{
    private const URL_PATH_EDIT = 'vendor_module/entity/edit';
    private const URL_PATH_DELETE = 'vendor_module/entity/delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }

            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_EDIT,
                        ['id' => $item['entity_id']]
                    ),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_DELETE,
                        ['id' => $item['entity_id']]
                    ),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete'),
                        'message' => __('Are you sure you want to delete this item?'),
                    ],
                ],
            ];
        }

        return $dataSource;
    }
}
```
