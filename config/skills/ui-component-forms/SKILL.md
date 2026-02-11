# UI Components: Admin Forms

> Related: See [ui-component-grids](../ui-component-grids/SKILL.md) for admin grid development, data providers, and action columns.

## Creating an Admin Form

### UI Component XML (view/adminhtml/ui_component/vendor_module_entity_form.xml)

```xml
<?xml version="1.0"?>
<form xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd">

    <argument name="data" xsi:type="array">
        <item name="js_config" xsi:type="array">
            <item name="provider" xsi:type="string">
                vendor_module_entity_form.vendor_module_entity_form_data_source
            </item>
        </item>
        <item name="label" xsi:type="string" translate="true">Entity Information</item>
        <item name="template" xsi:type="string">templates/form/collapsible</item>
    </argument>

    <settings>
        <buttons>
            <button name="save" class="Vendor\Module\Block\Adminhtml\Entity\Edit\SaveButton"/>
            <button name="delete" class="Vendor\Module\Block\Adminhtml\Entity\Edit\DeleteButton"/>
            <button name="back" class="Vendor\Module\Block\Adminhtml\Entity\Edit\BackButton"/>
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
        <dataProvider class="Vendor\Module\Ui\DataProvider\Entity\FormDataProvider"
                      name="vendor_module_entity_form_data_source">
            <settings>
                <requestFieldName>id</requestFieldName>
                <primaryFieldName>entity_id</primaryFieldName>
            </settings>
        </dataProvider>
    </dataSource>

    <fieldset name="general">
        <settings>
            <label translate="true">General Information</label>
        </settings>

        <field name="entity_id" formElement="input">
            <settings>
                <dataType>text</dataType>
                <visible>false</visible>
            </settings>
        </field>

        <field name="name" sortOrder="10" formElement="input">
            <settings>
                <validation>
                    <rule name="required-entry" xsi:type="boolean">true</rule>
                </validation>
                <dataType>text</dataType>
                <label translate="true">Name</label>
            </settings>
        </field>

        <field name="status" sortOrder="20" formElement="select">
            <settings>
                <dataType>int</dataType>
                <label translate="true">Status</label>
            </settings>
            <formElements>
                <select>
                    <settings>
                        <options class="Vendor\Module\Model\Entity\Source\Status"/>
                    </settings>
                </select>
            </formElements>
        </field>

        <field name="description" sortOrder="30" formElement="wysiwyg">
            <settings>
                <label translate="true">Description</label>
            </settings>
            <formElements>
                <wysiwyg>
                    <settings>
                        <rows>8</rows>
                        <wysiwyg>true</wysiwyg>
                    </settings>
                </wysiwyg>
            </formElements>
        </field>

        <field name="image" sortOrder="40" formElement="imageUploader">
            <settings>
                <label translate="true">Image</label>
                <componentType>imageUploader</componentType>
            </settings>
            <formElements>
                <imageUploader>
                    <settings>
                        <allowedExtensions>jpg jpeg gif png</allowedExtensions>
                        <maxFileSize>4194304</maxFileSize>
                        <uploaderConfig>
                            <param xsi:type="string" name="url">vendor_module/entity/upload</param>
                        </uploaderConfig>
                    </settings>
                </imageUploader>
            </formElements>
        </field>
    </fieldset>

</form>
```

### Form Data Provider

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Ui\DataProvider\Entity;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Vendor\Module\Model\ResourceModel\Entity\CollectionFactory;

class FormDataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $entity) {
            $this->loadedData[$entity->getId()] = $entity->getData();
        }

        $data = $this->dataPersistor->get('vendor_module_entity');
        if (!empty($data)) {
            $entity = $this->collection->getNewEmptyItem();
            $entity->setData($data);
            $this->loadedData[$entity->getId()] = $entity->getData();
            $this->dataPersistor->clear('vendor_module_entity');
        }

        return $this->loadedData;
    }
}
```

## Common Field Types

| formElement | Use Case |
|-------------|----------|
| `input` | Text input |
| `textarea` | Multi-line text |
| `select` | Dropdown |
| `multiselect` | Multiple selection |
| `checkbox` | Boolean |
| `date` | Date picker |
| `wysiwyg` | Rich text editor |
| `imageUploader` | Image upload |
| `fileUploader` | File upload |

## Data Modifiers

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Ui\DataProvider\Entity\Form\Modifier;

use Magento\Ui\DataProvider\Modifier\ModifierInterface;

class CustomModifier implements ModifierInterface
{
    /**
     * @param array $meta
     * @return array
     */
    public function modifyMeta(array $meta): array
    {
        // Add dynamic fields
        $meta['custom_fieldset'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Custom Section'),
                        'sortOrder' => 50,
                        'collapsible' => true,
                    ],
                ],
            ],
            'children' => [
                'dynamic_field' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'formElement' => 'input',
                                'componentType' => 'field',
                                'label' => __('Dynamic Field'),
                                'dataScope' => 'dynamic_field',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $meta;
    }

    /**
     * @param array $data
     * @return array
     */
    public function modifyData(array $data): array
    {
        // Transform data
        foreach ($data as $entityId => &$entityData) {
            $entityData['computed_field'] = $this->compute($entityData);
        }

        return $data;
    }
}
```

## JavaScript Components

### Custom Component

```javascript
define([
    'Magento_Ui/js/form/element/abstract'
], function (Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            customOption: null
        },

        initObservable: function () {
            this._super();
            this.observe(['customOption']);
            return this;
        },

        onUpdate: function (value) {
            this._super();
            // Custom logic on value change
            this.customOption(this.processValue(value));
        },

        processValue: function (value) {
            return value ? value.toUpperCase() : '';
        }
    });
});
```

## Best Practices

1. **Use virtual types** for data providers when possible
2. **Keep UI components modular** - separate concerns
3. **Use modifiers** for dynamic changes, not hardcoded XML
4. **Leverage existing components** before creating custom ones
5. **Test JavaScript** components with proper mocking
6. **Use ACL resources** to control access to grids and forms
7. **Enable sticky toolbar** for better UX on large datasets
