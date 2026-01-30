# Checkout Customization Skill

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
     * Process js layout of checkout page
     *
     * @param array $jsLayout
     * @return array
     */
    public function process($jsLayout): array
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

## Checkout Config Provider

### Config Provider Class

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CustomConfigProvider implements ConfigProviderInterface
{
    private const XML_PATH_ENABLED = 'vendor_module/checkout/enabled';
    private const XML_PATH_MESSAGE = 'vendor_module/checkout/message';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'customCheckout' => [
                'enabled' => $this->isEnabled(),
                'message' => $this->getMessage(),
                'additionalData' => $this->getAdditionalData(),
            ],
        ];
    }

    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getMessage(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_MESSAGE,
            ScopeInterface::SCOPE_STORE
        );
    }

    private function getAdditionalData(): array
    {
        return [
            'timestamp' => time(),
            'version' => '1.0.0',
        ];
    }
}
```

### Register Config Provider

```xml
<!-- etc/frontend/di.xml -->
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Checkout\Model\CompositeConfigProvider">
        <arguments>
            <argument name="configProviders" xsi:type="array">
                <item name="vendor_module_config_provider" xsi:type="object">
                    Vendor\Module\Model\CustomConfigProvider
                </item>
            </argument>
        </arguments>
    </type>

</config>
```

### Access Config in JavaScript

```javascript
define([
    'uiComponent',
    'Magento_Checkout/js/model/quote'
], function (Component, quote) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super();

            // Access custom config
            var customConfig = window.checkoutConfig.customCheckout;

            if (customConfig.enabled) {
                console.log(customConfig.message);
            }

            return this;
        }
    });
});
```

## JavaScript Mixins for Checkout

### Mixin Configuration

```javascript
// view/frontend/requirejs-config.js
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Vendor_Module/js/view/shipping-mixin': true
            },
            'Magento_Checkout/js/model/shipping-save-processor/default': {
                'Vendor_Module/js/model/shipping-save-processor-mixin': true
            }
        }
    }
};
```

### Shipping Mixin

```javascript
// view/frontend/web/js/view/shipping-mixin.js
define([
    'jquery',
    'mage/utils/wrapper'
], function ($, wrapper) {
    'use strict';

    return function (shippingView) {
        return shippingView.extend({
            /**
             * Override set shipping information action
             */
            setShippingInformation: function () {
                // Custom logic before
                this.beforeSetShipping();

                // Call original method
                this._super();

                // Custom logic after
                this.afterSetShipping();
            },

            beforeSetShipping: function () {
                console.log('Before shipping set');
            },

            afterSetShipping: function () {
                console.log('After shipping set');
            }
        });
    };
});
```

### Shipping Save Processor Mixin

```javascript
// view/frontend/web/js/model/shipping-save-processor-mixin.js
define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function ($, wrapper, quote) {
    'use strict';

    return function (processor) {
        processor.saveShippingInformation = wrapper.wrapSuper(
            processor.saveShippingInformation,
            function () {
                var payload = this._super();

                // Add custom data to shipping payload
                var shippingAddress = quote.shippingAddress();
                if (shippingAddress.customAttributes) {
                    payload.addressInformation.extension_attributes = {
                        custom_field: shippingAddress.customAttributes.custom_field
                    };
                }

                return payload;
            }
        );

        return processor;
    };
});
```

## Payment Method Modifications

### Adding Payment Method via Layout

```xml
<!-- view/frontend/layout/checkout_index_index.xml -->
<referenceBlock name="checkout.root">
    <arguments>
        <argument name="jsLayout" xsi:type="array">
            <item name="components" xsi:type="array">
                <item name="checkout" xsi:type="array">
                    <item name="children" xsi:type="array">
                        <item name="steps" xsi:type="array">
                            <item name="children" xsi:type="array">
                                <item name="billing-step" xsi:type="array">
                                    <item name="children" xsi:type="array">
                                        <item name="payment" xsi:type="array">
                                            <item name="children" xsi:type="array">
                                                <item name="renders" xsi:type="array">
                                                    <item name="children" xsi:type="array">
                                                        <item name="vendor-payment" xsi:type="array">
                                                            <item name="component" xsi:type="string">
                                                                Vendor_Module/js/view/payment/vendor-payments
                                                            </item>
                                                            <item name="methods" xsi:type="array">
                                                                <item name="vendor_payment" xsi:type="array">
                                                                    <item name="isBillingAddressRequired" xsi:type="boolean">true</item>
                                                                </item>
                                                            </item>
                                                        </item>
                                                    </item>
                                                </item>
                                            </item>
                                        </item>
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
```

## Order Summary Customization

### Adding Custom Summary Item

```xml
<!-- view/frontend/layout/checkout_index_index.xml -->
<referenceBlock name="checkout.root">
    <arguments>
        <argument name="jsLayout" xsi:type="array">
            <item name="components" xsi:type="array">
                <item name="checkout" xsi:type="array">
                    <item name="children" xsi:type="array">
                        <item name="sidebar" xsi:type="array">
                            <item name="children" xsi:type="array">
                                <item name="summary" xsi:type="array">
                                    <item name="children" xsi:type="array">
                                        <item name="totals" xsi:type="array">
                                            <item name="children" xsi:type="array">
                                                <item name="custom_fee" xsi:type="array">
                                                    <item name="component" xsi:type="string">
                                                        Vendor_Module/js/view/checkout/summary/custom-fee
                                                    </item>
                                                    <item name="sortOrder" xsi:type="string">50</item>
                                                    <item name="config" xsi:type="array">
                                                        <item name="title" xsi:type="string" translate="true">
                                                            Custom Fee
                                                        </item>
                                                    </item>
                                                </item>
                                            </item>
                                        </item>
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
```

### Custom Fee Component

```javascript
// view/frontend/web/js/view/checkout/summary/custom-fee.js
define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils'
], function (Component, quote, priceUtils) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Vendor_Module/checkout/summary/custom-fee'
        },

        totals: quote.getTotals(),

        isDisplayed: function () {
            return this.getValue() > 0;
        },

        getValue: function () {
            var totals = this.totals();
            if (totals && totals.extension_attributes) {
                return totals.extension_attributes.custom_fee || 0;
            }
            return 0;
        },

        getFormattedValue: function () {
            return priceUtils.formatPrice(this.getValue(), quote.getPriceFormat());
        }
    });
});
```

## Validation

### Custom Validator

```javascript
// view/frontend/web/js/model/custom-validator.js
define([
    'jquery',
    'mage/validation'
], function ($) {
    'use strict';

    return {
        /**
         * Validate checkout form
         *
         * @returns {Boolean}
         */
        validate: function () {
            var form = '#custom-checkout-form';

            return $(form).validation() && $(form).validation('isValid');
        }
    };
});
```

### Register Validator

```javascript
// view/frontend/web/js/view/custom-step.js
define([
    'uiComponent',
    'Vendor_Module/js/model/custom-validator',
    'Magento_Checkout/js/model/payment/additional-validators'
], function (Component, customValidator, additionalValidators) {
    'use strict';

    additionalValidators.registerValidator(customValidator);

    return Component.extend({
        // Component implementation
    });
});
```

## Saving Custom Data

### Extension Attributes (extension_attributes.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Api/etc/extension_attributes.xsd">

    <extension_attributes for="Magento\Quote\Api\Data\AddressInterface">
        <attribute code="custom_field" type="string"/>
    </extension_attributes>

    <extension_attributes for="Magento\Quote\Api\Data\PaymentInterface">
        <attribute code="custom_payment_data" type="string"/>
    </extension_attributes>

</config>
```

### Plugin to Save Extension Attributes

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\CartRepositoryInterface;

class ShippingInformationManagementPlugin
{
    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository
    ) {
    }

    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $shippingAddress = $addressInformation->getShippingAddress();
        $extensionAttributes = $shippingAddress->getExtensionAttributes();

        if ($extensionAttributes && $extensionAttributes->getCustomField()) {
            $quote = $this->quoteRepository->getActive($cartId);
            $quote->setData('custom_field', $extensionAttributes->getCustomField());
            $this->quoteRepository->save($quote);
        }

        return [$cartId, $addressInformation];
    }
}
```

## Debugging Checkout

### Enable JavaScript Logging

```javascript
// In browser console
window.checkoutConfig; // View checkout configuration
require('Magento_Checkout/js/model/quote').shippingAddress(); // Current shipping address
require('Magento_Checkout/js/model/quote').totals(); // Current totals
require('Magento_Checkout/js/model/step-navigator').steps(); // All steps
```

### Checkout API Endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /V1/carts/mine/estimate-shipping-methods` | Get shipping rates |
| `POST /V1/carts/mine/shipping-information` | Save shipping info |
| `POST /V1/carts/mine/payment-information` | Place order |
| `GET /V1/carts/mine/totals` | Get cart totals |

## Best Practices

1. **Use layout processors** for dynamic field additions
2. **Implement config providers** for server-to-client data transfer
3. **Use mixins** instead of overriding entire components
4. **Register validators** through the additional validators registry
5. **Leverage extension attributes** for custom data on entities
6. **Test checkout flow** thoroughly after modifications
7. **Cache configuration** data when possible
8. **Handle errors gracefully** with user-friendly messages
9. **Maintain step order** consistency with sortOrder values
10. **Profile JavaScript** performance for complex customizations

## Common Issues and Solutions

| Issue | Solution |
|-------|----------|
| Step not visible | Check sortOrder and isVisible observable |
| Custom field not saving | Verify extension attributes and plugin |
| JavaScript errors | Check RequireJS dependencies and module paths |
| Validation not triggering | Register validator with additional validators |
| Config not available | Clear cache after config provider changes |
