# Payment Integration: Checkout & Frontend

> Related: See [payment-integration-core](../payment-integration-core/SKILL.md) for module setup, and [payment-integration-gateway](../payment-integration-gateway/SKILL.md) for gateway components.

## Checkout Integration

### Config Provider

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Vendor\Payment\Gateway\Config\Config;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'vendor_payment';

    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive(),
                    'title' => $this->config->getValue('title'),
                    'environment' => $this->config->getEnvironment(),
                    'publicKey' => $this->config->getValue('public_key'),
                ],
            ],
        ];
    }
}
```

### Register Config Provider (etc/frontend/di.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <type name="Magento\Checkout\Model\CompositeConfigProvider">
        <arguments>
            <argument name="configProviders" xsi:type="array">
                <item name="vendor_payment_config_provider" xsi:type="object">
                    Vendor\Payment\Model\Ui\ConfigProvider
                </item>
            </argument>
        </arguments>
    </type>

</config>
```

### JavaScript Component

```javascript
// view/frontend/web/js/view/payment/method-renderer/vendor-payment.js
define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/payment/additional-validators',
    'jquery'
], function (Component, quote, placeOrderAction, additionalValidators, $) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Vendor_Payment/payment/form',
            cardNumber: '',
            cardExpiry: '',
            cardCvc: ''
        },

        initObservable: function () {
            this._super()
                .observe([
                    'cardNumber',
                    'cardExpiry',
                    'cardCvc'
                ]);

            return this;
        },

        getCode: function () {
            return 'vendor_payment';
        },

        isActive: function () {
            return window.checkoutConfig.payment.vendor_payment.isActive;
        },

        getTitle: function () {
            return window.checkoutConfig.payment.vendor_payment.title;
        },

        getData: function () {
            return {
                'method': this.getCode(),
                'additional_data': {
                    'card_number': this.cardNumber(),
                    'card_expiry': this.cardExpiry(),
                    'card_cvc': this.cardCvc()
                }
            };
        },

        validate: function () {
            var form = '#vendor-payment-form';
            return $(form).validation() && $(form).validation('isValid');
        },

        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() && additionalValidators.validate()) {
                this.isPlaceOrderActionAllowed(false);

                return this.getPlaceOrderDeferredObject()
                    .fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    })
                    .done(function () {
                        self.afterPlaceOrder();
                    });
            }

            return false;
        }
    });
});
```

### Payment Template

```html
<!-- view/frontend/web/template/payment/form.html -->
<div class="payment-method" data-bind="css: {'_active': (getCode() == isChecked())}">
    <div class="payment-method-title field choice">
        <input type="radio"
               name="payment[method]"
               class="radio"
               data-bind="attr: {'id': getCode()},
                          value: getCode(),
                          checked: isChecked,
                          click: selectPaymentMethod,
                          visible: isRadioButtonVisible()"/>
        <label data-bind="attr: {'for': getCode()}" class="label">
            <span data-bind="text: getTitle()"></span>
        </label>
    </div>

    <div class="payment-method-content">
        <!-- ko if: (isChecked() == getCode()) -->
        <form id="vendor-payment-form" class="form" data-bind="submit: placeOrder">
            <fieldset class="fieldset">
                <div class="field required">
                    <label class="label" for="card-number">
                        <span data-bind="i18n: 'Card Number'"></span>
                    </label>
                    <div class="control">
                        <input type="text"
                               id="card-number"
                               class="input-text"
                               data-bind="value: cardNumber,
                                          valueUpdate: 'keyup'"
                               data-validate="{required:true, 'validate-cc-number':true}"/>
                    </div>
                </div>

                <div class="field required">
                    <label class="label" for="card-expiry">
                        <span data-bind="i18n: 'Expiry Date'"></span>
                    </label>
                    <div class="control">
                        <input type="text"
                               id="card-expiry"
                               class="input-text"
                               placeholder="MM/YY"
                               data-bind="value: cardExpiry"
                               data-validate="{required:true}"/>
                    </div>
                </div>

                <div class="field required">
                    <label class="label" for="card-cvc">
                        <span data-bind="i18n: 'CVC'"></span>
                    </label>
                    <div class="control">
                        <input type="text"
                               id="card-cvc"
                               class="input-text"
                               data-bind="value: cardCvc"
                               data-validate="{required:true, 'validate-cc-cvn':true}"/>
                    </div>
                </div>
            </fieldset>

            <div class="actions-toolbar">
                <div class="primary">
                    <button class="action primary checkout"
                            type="submit"
                            data-bind="click: placeOrder,
                                       enable: isPlaceOrderActionAllowed()">
                        <span data-bind="i18n: 'Place Order'"></span>
                    </button>
                </div>
            </div>
        </form>
        <!-- /ko -->
    </div>
</div>
```

### Payment Renderer

```javascript
// view/frontend/web/js/view/payment/vendor-payment.js
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'vendor_payment',
        component: 'Vendor_Payment/js/view/payment/method-renderer/vendor-payment'
    });

    return Component.extend({});
});
```

### Checkout Layout

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
                                        <item name="billing-step" xsi:type="array">
                                            <item name="children" xsi:type="array">
                                                <item name="payment" xsi:type="array">
                                                    <item name="children" xsi:type="array">
                                                        <item name="renders" xsi:type="array">
                                                            <item name="children" xsi:type="array">
                                                                <item name="vendor-payment" xsi:type="array">
                                                                    <item name="component" xsi:type="string">
                                                                        Vendor_Payment/js/view/payment/vendor-payment
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
    </body>
</page>
```

## Vault Integration (Saved Cards)

### Enable Vault (config.xml)

```xml
<vendor_payment>
    <!-- ... existing config ... -->
    <can_save_cc>1</can_save_cc>
    <vault_code>vendor_payment_vault</vault_code>
</vendor_payment>
<vendor_payment_vault>
    <model>VendorPaymentVaultFacade</model>
    <title>Stored Cards (Vendor Payment)</title>
</vendor_payment_vault>
```

### Vault Facade (di.xml)

```xml
<virtualType name="VendorPaymentVaultFacade" type="Magento\Vault\Model\Method\Vault">
    <arguments>
        <argument name="config" xsi:type="object">VendorPaymentVaultConfig</argument>
        <argument name="valueHandlerPool" xsi:type="object">VendorPaymentVaultValueHandlerPool</argument>
        <argument name="vaultProvider" xsi:type="object">VendorPaymentFacade</argument>
        <argument name="code" xsi:type="const">Vendor\Payment\Gateway\Config\Config::VAULT_CODE</argument>
    </arguments>
</virtualType>
```

## Testing Payment Methods

### Unit Test Example

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Test\Unit\Gateway\Request;

use PHPUnit\Framework\TestCase;
use Vendor\Payment\Gateway\Request\TransactionDataBuilder;
use Vendor\Payment\Gateway\SubjectReader;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;

class TransactionDataBuilderTest extends TestCase
{
    /**
     * @var TransactionDataBuilder
     */
    private TransactionDataBuilder $builder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->builder = new TransactionDataBuilder(new SubjectReader());
    }

    /**
     * @return void
     */
    public function testBuildReturnsCorrectData(): void
    {
        $orderMock = $this->createMock(OrderAdapterInterface::class);
        $orderMock->method('getGrandTotalAmount')->willReturn(100.00);
        $orderMock->method('getCurrencyCode')->willReturn('USD');
        $orderMock->method('getOrderIncrementId')->willReturn('000000001');

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getOrder')->willReturn($orderMock);

        $result = $this->builder->build(['payment' => $paymentDO]);

        $this->assertEquals(10000, $result['amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('000000001', $result['order_id']);
    }
}
```

## Best Practices

1. **Use Gateway Command pattern** for all payment operations
2. **Implement proper error handling** with meaningful messages
3. **Log all API transactions** for debugging
4. **Never log sensitive data** (full card numbers, CVV)
5. **Use encrypted config** for API credentials
6. **Implement idempotency** for transaction requests
7. **Handle webhook notifications** for async status updates
8. **Test with sandbox** before production deployment
9. **Follow PCI compliance** guidelines
10. **Implement proper void/refund** logic for order cancellations

## Security Considerations

| Requirement | Implementation |
|-------------|----------------|
| Never store CVV | Only process, never persist |
| Encrypt credentials | Use `Magento\Config\Model\Config\Backend\Encrypted` |
| Mask card numbers | Store only last 4 digits |
| Use HTTPS | All API calls must use TLS |
| Tokenization | Use gateway tokens instead of card data |
| PCI DSS Scope | Minimize by using hosted payment forms |

## Debugging

```bash
# Check payment method availability
bin/magento dev:query:payment-method vendor_payment

# View payment logs
tail -f var/log/payment.log

# Debug mode logs full requests/responses
# Enable in admin: Stores > Configuration > Sales > Payment Methods > Debug
```
