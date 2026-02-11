# Admin Panel: UI Component Grid & Form

> Related: See [adminhtml-routing.md](adminhtml-routing.md) for routing, ACL, menu configuration, and controllers.

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
