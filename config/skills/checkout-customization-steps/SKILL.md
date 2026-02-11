# Checkout Customization: Steps & Layout Processors

> Related: See [checkout-customization-advanced](../checkout-customization-advanced/SKILL.md) for config providers, mixins, payment modifications, validation, and data saving.

## Overview

Magento 2 checkout is a complex, JavaScript-driven application built on UI Components, KnockoutJS, and RequireJS. This skill covers customizing the checkout flow, adding custom steps, modifying existing components, and integrating with the checkout API.

## Checkout Architecture

```
Checkout Application
├── checkout_index_index.xml (Layout)
├── UI Components (XML Configuration)
├── JavaScript Components (KnockoutJS)
├── Data Providers (PHP)
├── Layout Processors (PHP)
└── REST API Endpoints
```

## Checkout Steps

| Step | Component | Description |
|------|-----------|-------------|
| Shipping | `checkout.steps.shipping-step` | Address and method selection |
| Payment | `checkout.steps.billing-step` | Payment method and place order |

## Layout Structure

### checkout_index_index.xml

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      layout="checkout"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="Magento\Checkout\Block\Onepage" name="checkout.root"
                   template="Magento_Checkout::onepage.phtml">
                <arguments>
                    <argument name="jsLayout" xsi:type="array">
                        <!-- Checkout component configuration -->
                    </argument>
                </arguments>
            </block>
        </referenceContainer>
    </body>
</page>
```

## Adding a Custom Checkout Step

### 1. Layout Configuration

```xml
<!-- view/frontend/layout/checkout_index_index.xml -->
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceBlock name="checkout.root">
            <arguments>
                <argument name="jsLayout" xsi:type="array">
                    <item name="components" xsi:type="array">
                        <item name="checkout" xsi:type="array">
                            <item name="children" xsi:type="array">
                                <item name="steps" xsi:type="array">
                                    <item name="children" xsi:type="array">
                                        <!-- Custom step between shipping and payment -->
                                        <item name="custom-step" xsi:type="array">
                                            <item name="component" xsi:type="string">
                                                Vendor_Module/js/view/custom-step
                                            </item>
                                            <item name="sortOrder" xsi:type="string">2</item>
                                            <item name="children" xsi:type="array">
                                                <!-- Step children components -->
                                            </item>
                                        </item>
                                    </item>
                                </item>
                            </item>
                        </item>
                    </item>
                </argument>
            </arguments>
        </referenceBlock>
    </body>
</page>
```

### 2. JavaScript Step Component

```javascript
// view/frontend/web/js/view/custom-step.js
define([
    'jquery',
    'ko',
    'uiComponent',
    'underscore',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Customer/js/model/customer'
], function ($, ko, Component, _, stepNavigator, customer) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Vendor_Module/custom-step'
        },

        isVisible: ko.observable(true),
        isLogedIn: customer.isLoggedIn(),
        stepCode: 'custom-step',
        stepTitle: 'Custom Step',

        initialize: function () {
            this._super();

            // Register this step
            stepNavigator.registerStep(
                this.stepCode,
                null,
                this.stepTitle,
                this.isVisible,
                _.bind(this.navigate, this),
                this.sortOrder
            );

            return this;
        },

        /**
         * Step navigation handler
         */
        navigate: function () {
            this.isVisible(true);
        },

        /**
         * Navigate to next step
         */
        navigateToNextStep: function () {
            stepNavigator.next();
        },

        /**
         * Custom validation before proceeding
         */
        validateStep: function () {
            // Implement validation logic
            return true;
        },

        /**
         * Button click handler
         */
        proceedToNextStep: function () {
            if (this.validateStep()) {
                this.navigateToNextStep();
            }
        }
    });
});
```

### 3. Step Template

```html
<!-- view/frontend/web/template/custom-step.html -->
<li id="custom-step" data-bind="fadeVisible: isVisible">
    <div class="step-title" data-bind="i18n: 'Custom Step'" data-role="title"></div>
    <div id="checkout-step-custom"
         class="step-content"
         data-role="content">

        <form class="form" id="custom-step-form">
            <div class="custom-step-content">
                <!-- Custom step content -->
                <div class="field required">
                    <label class="label" for="custom-field">
                        <span data-bind="i18n: 'Custom Field'"></span>
                    </label>
                    <div class="control">
                        <input type="text"
                               id="custom-field"
                               name="custom_field"
                               class="input-text"
                               data-bind="value: customField"
                               data-validate="{required:true}"/>
                    </div>
                </div>
            </div>

            <div class="actions-toolbar">
                <div class="primary">
                    <button type="submit"
                            class="button action continue primary"
                            data-bind="click: proceedToNextStep">
                        <span data-bind="i18n: 'Continue'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</li>
```

## Modifying Shipping Step

### Adding Fields via Layout Processor

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Block\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;

class ShippingLayoutProcessor implements LayoutProcessorInterface
{
    /**
     * @param array $jsLayout
     * @return array
     */
    public function process(array $jsLayout): array
    {
        $customField = [
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes',
                'customEntry' => null,
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
            ],
            'dataScope' => 'shippingAddress.custom_attributes.custom_field',
            'label' => __('Custom Field'),
            'provider' => 'checkoutProvider',
            'sortOrder' => 200,
            'validation' => [
                'required-entry' => true,
            ],
            'options' => [],
            'filterBy' => null,
            'customEntry' => null,
            'visible' => true,
        ];

        $jsLayout['components']['checkout']['children']['steps']['children']
            ['shipping-step']['children']['shippingAddress']['children']
            ['shipping-address-fieldset']['children']['custom_field'] = $customField;

        return $jsLayout;
    }
}
```

### Register Layout Processor (di.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Checkout\Block\Checkout\LayoutProcessor">
        <plugin name="vendor_module_shipping_processor"
                type="Vendor\Module\Plugin\Checkout\LayoutProcessorPlugin"
                sortOrder="10"/>
    </type>

    <!-- Alternative: using composite processor -->
    <type name="Magento\Checkout\Block\Onepage">
        <arguments>
            <argument name="layoutProcessors" xsi:type="array">
                <item name="custom_processor" xsi:type="object">
                    Vendor\Module\Block\Checkout\ShippingLayoutProcessor
                </item>
            </argument>
        </arguments>
    </type>

</config>
```
