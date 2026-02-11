# Payment Integration: Core Module & Configuration

> Related: See [payment-integration-gateway](../payment-integration-gateway/SKILL.md) for gateway components, and [payment-integration-checkout](../payment-integration-checkout/SKILL.md) for checkout integration.

## Overview

Magento 2 payment integrations enable custom payment methods to process transactions, handle authorizations, captures, refunds, and voids. This skill covers creating payment method modules using the Payment Gateway architecture, which provides a standardized approach for implementing payment integrations.

## Payment Architecture

```
Payment Method
├── Method Adapter (configuration)
├── Gateway Commands (operations)
│   ├── Authorize
│   ├── Capture
│   ├── Void
│   ├── Refund
│   └── Cancel
├── Request Builders (API requests)
├── Response Handlers (API responses)
├── Validators (request/response validation)
└── HTTP Client (API communication)
```

## Payment Method Types

| Type | Description | Example |
|------|-------------|---------|
| Offline | No external API calls | Check/Money Order, COD |
| Redirect | Customer redirected to gateway | PayPal Standard |
| Hosted | Embedded payment form from gateway | Stripe Elements |
| Direct | Direct API integration | Braintree Direct |

## Module Structure

```
app/code/Vendor/Payment/
├── etc/
│   ├── adminhtml/
│   │   └── system.xml              # Admin configuration
│   ├── config.xml                   # Default configuration
│   ├── di.xml                       # Dependency injection
│   ├── module.xml                   # Module declaration
│   └── payment.xml                  # Payment method declaration (optional)
├── Gateway/
│   ├── Command/                     # Gateway commands
│   ├── Config/                      # Configuration
│   ├── Http/                        # HTTP client
│   ├── Request/                     # Request builders
│   ├── Response/                    # Response handlers
│   ├── Validator/                   # Validators
│   └── SubjectReader.php            # Helper for reading subject
├── Model/
│   └── Ui/
│       └── ConfigProvider.php       # Checkout config
├── view/
│   └── frontend/
│       ├── layout/
│       │   └── checkout_index_index.xml
│       └── web/
│           ├── js/view/payment/
│           └── template/payment/
├── registration.php
└── composer.json
```

## Creating a Payment Method

### 1. Module Registration

```php
<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Vendor_Payment',
    __DIR__
);
```

### 2. Module Declaration (etc/module.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_Payment">
        <sequence>
            <module name="Magento_Payment"/>
            <module name="Magento_Checkout"/>
            <module name="Magento_Sales"/>
        </sequence>
    </module>
</config>
```

### 3. Default Configuration (etc/config.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd">
    <default>
        <payment>
            <vendor_payment>
                <active>0</active>
                <model>VendorPaymentFacade</model>
                <title>Vendor Payment</title>
                <payment_action>authorize</payment_action>
                <order_status>pending</order_status>
                <currency>USD</currency>
                <can_authorize>1</can_authorize>
                <can_capture>1</can_capture>
                <can_capture_partial>1</can_capture_partial>
                <can_refund>1</can_refund>
                <can_refund_partial_per_invoice>1</can_refund_partial_per_invoice>
                <can_void>1</can_void>
                <can_cancel>1</can_cancel>
                <can_use_checkout>1</can_use_checkout>
                <can_use_internal>1</can_use_internal>
                <is_gateway>1</is_gateway>
                <debug>0</debug>
                <debugReplaceKeys>api_key,secret_key</debugReplaceKeys>
            </vendor_payment>
        </payment>
    </default>
</config>
```

### 4. Admin Configuration (etc/adminhtml/system.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <system>
        <section id="payment">
            <group id="vendor_payment" translate="label" sortOrder="50"
                   showInDefault="1" showInWebsite="1" showInStore="1">
                <label>Vendor Payment</label>
                <comment>Accept payments via Vendor Payment Gateway</comment>

                <field id="active" translate="label" type="select" sortOrder="10"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Enabled</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>

                <field id="title" translate="label" type="text" sortOrder="20"
                       showInDefault="1" showInWebsite="1" showInStore="1">
                    <label>Title</label>
                </field>

                <field id="environment" translate="label" type="select" sortOrder="30"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Environment</label>
                    <source_model>Vendor\Payment\Model\Source\Environment</source_model>
                </field>

                <field id="api_key" translate="label" type="obscure" sortOrder="40"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>API Key</label>
                    <backend_model>Magento\Config\Model\Config\Backend\Encrypted</backend_model>
                </field>

                <field id="secret_key" translate="label" type="obscure" sortOrder="50"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Secret Key</label>
                    <backend_model>Magento\Config\Model\Config\Backend\Encrypted</backend_model>
                </field>

                <field id="payment_action" translate="label" type="select" sortOrder="60"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Payment Action</label>
                    <source_model>Vendor\Payment\Model\Source\PaymentAction</source_model>
                </field>

                <field id="allowspecific" translate="label" type="allowspecific" sortOrder="70"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Payment from Applicable Countries</label>
                    <source_model>Magento\Payment\Model\Config\Source\Allspecificcountries</source_model>
                </field>

                <field id="specificcountry" translate="label" type="multiselect" sortOrder="80"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Payment from Specific Countries</label>
                    <source_model>Magento\Directory\Model\Config\Source\Country</source_model>
                </field>

                <field id="min_order_total" translate="label" type="text" sortOrder="90"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Minimum Order Total</label>
                </field>

                <field id="max_order_total" translate="label" type="text" sortOrder="100"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Maximum Order Total</label>
                </field>

                <field id="sort_order" translate="label" type="text" sortOrder="110"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Sort Order</label>
                </field>

                <field id="debug" translate="label" type="select" sortOrder="120"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Debug</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>
            </group>
        </section>
    </system>
</config>
```

### 5. Dependency Injection (etc/di.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">

    <!-- Payment Method Facade -->
    <virtualType name="VendorPaymentFacade" type="Magento\Payment\Model\Method\Adapter">
        <arguments>
            <argument name="code" xsi:type="const">Vendor\Payment\Gateway\Config\Config::CODE</argument>
            <argument name="formBlockType" xsi:type="string">Magento\Payment\Block\Form</argument>
            <argument name="infoBlockType" xsi:type="string">Magento\Payment\Block\Info</argument>
            <argument name="valueHandlerPool" xsi:type="object">VendorPaymentValueHandlerPool</argument>
            <argument name="commandPool" xsi:type="object">VendorPaymentCommandPool</argument>
        </arguments>
    </virtualType>

    <!-- Value Handler Pool -->
    <virtualType name="VendorPaymentValueHandlerPool" type="Magento\Payment\Gateway\Config\ValueHandlerPool">
        <arguments>
            <argument name="handlers" xsi:type="array">
                <item name="default" xsi:type="string">VendorPaymentConfigValueHandler</item>
            </argument>
        </arguments>
    </virtualType>

    <virtualType name="VendorPaymentConfigValueHandler" type="Magento\Payment\Gateway\Config\ConfigValueHandler">
        <arguments>
            <argument name="configInterface" xsi:type="object">VendorPaymentConfig</argument>
        </arguments>
    </virtualType>

    <!-- Configuration -->
    <virtualType name="VendorPaymentConfig" type="Vendor\Payment\Gateway\Config\Config">
        <arguments>
            <argument name="methodCode" xsi:type="const">Vendor\Payment\Gateway\Config\Config::CODE</argument>
        </arguments>
    </virtualType>

    <!-- Command Pool -->
    <virtualType name="VendorPaymentCommandPool" type="Magento\Payment\Gateway\Command\CommandPool">
        <arguments>
            <argument name="commands" xsi:type="array">
                <item name="authorize" xsi:type="string">VendorPaymentAuthorizeCommand</item>
                <item name="capture" xsi:type="string">VendorPaymentCaptureCommand</item>
                <item name="void" xsi:type="string">VendorPaymentVoidCommand</item>
                <item name="refund" xsi:type="string">VendorPaymentRefundCommand</item>
                <item name="cancel" xsi:type="string">VendorPaymentVoidCommand</item>
            </argument>
        </arguments>
    </virtualType>

    <!-- Authorize Command -->
    <virtualType name="VendorPaymentAuthorizeCommand" type="Magento\Payment\Gateway\Command\GatewayCommand">
        <arguments>
            <argument name="requestBuilder" xsi:type="object">VendorPaymentAuthorizeRequest</argument>
            <argument name="transferFactory" xsi:type="object">Vendor\Payment\Gateway\Http\TransferFactory</argument>
            <argument name="client" xsi:type="object">Vendor\Payment\Gateway\Http\Client\TransactionAuthorize</argument>
            <argument name="handler" xsi:type="object">VendorPaymentAuthorizeHandler</argument>
            <argument name="validator" xsi:type="object">Vendor\Payment\Gateway\Validator\ResponseValidator</argument>
        </arguments>
    </virtualType>

    <!-- Authorize Request Builder -->
    <virtualType name="VendorPaymentAuthorizeRequest" type="Magento\Payment\Gateway\Request\BuilderComposite">
        <arguments>
            <argument name="builders" xsi:type="array">
                <item name="transaction" xsi:type="string">Vendor\Payment\Gateway\Request\TransactionDataBuilder</item>
                <item name="customer" xsi:type="string">Vendor\Payment\Gateway\Request\CustomerDataBuilder</item>
                <item name="address" xsi:type="string">Vendor\Payment\Gateway\Request\AddressDataBuilder</item>
            </argument>
        </arguments>
    </virtualType>

    <!-- Authorize Handler -->
    <virtualType name="VendorPaymentAuthorizeHandler" type="Magento\Payment\Gateway\Response\HandlerChain">
        <arguments>
            <argument name="handlers" xsi:type="array">
                <item name="txn_id" xsi:type="string">Vendor\Payment\Gateway\Response\TransactionIdHandler</item>
                <item name="payment_details" xsi:type="string">Vendor\Payment\Gateway\Response\PaymentDetailsHandler</item>
            </argument>
        </arguments>
    </virtualType>

    <!-- Capture Command -->
    <virtualType name="VendorPaymentCaptureCommand" type="Magento\Payment\Gateway\Command\GatewayCommand">
        <arguments>
            <argument name="requestBuilder" xsi:type="object">VendorPaymentCaptureRequest</argument>
            <argument name="transferFactory" xsi:type="object">Vendor\Payment\Gateway\Http\TransferFactory</argument>
            <argument name="client" xsi:type="object">Vendor\Payment\Gateway\Http\Client\TransactionCapture</argument>
            <argument name="handler" xsi:type="object">Vendor\Payment\Gateway\Response\TransactionIdHandler</argument>
            <argument name="validator" xsi:type="object">Vendor\Payment\Gateway\Validator\ResponseValidator</argument>
        </arguments>
    </virtualType>

    <virtualType name="VendorPaymentCaptureRequest" type="Magento\Payment\Gateway\Request\BuilderComposite">
        <arguments>
            <argument name="builders" xsi:type="array">
                <item name="capture" xsi:type="string">Vendor\Payment\Gateway\Request\CaptureDataBuilder</item>
            </argument>
        </arguments>
    </virtualType>

    <!-- Void Command -->
    <virtualType name="VendorPaymentVoidCommand" type="Magento\Payment\Gateway\Command\GatewayCommand">
        <arguments>
            <argument name="requestBuilder" xsi:type="object">Vendor\Payment\Gateway\Request\VoidDataBuilder</argument>
            <argument name="transferFactory" xsi:type="object">Vendor\Payment\Gateway\Http\TransferFactory</argument>
            <argument name="client" xsi:type="object">Vendor\Payment\Gateway\Http\Client\TransactionVoid</argument>
            <argument name="handler" xsi:type="object">Vendor\Payment\Gateway\Response\TransactionIdHandler</argument>
            <argument name="validator" xsi:type="object">Vendor\Payment\Gateway\Validator\ResponseValidator</argument>
        </arguments>
    </virtualType>

    <!-- Refund Command -->
    <virtualType name="VendorPaymentRefundCommand" type="Magento\Payment\Gateway\Command\GatewayCommand">
        <arguments>
            <argument name="requestBuilder" xsi:type="object">VendorPaymentRefundRequest</argument>
            <argument name="transferFactory" xsi:type="object">Vendor\Payment\Gateway\Http\TransferFactory</argument>
            <argument name="client" xsi:type="object">Vendor\Payment\Gateway\Http\Client\TransactionRefund</argument>
            <argument name="handler" xsi:type="object">Vendor\Payment\Gateway\Response\TransactionIdHandler</argument>
            <argument name="validator" xsi:type="object">Vendor\Payment\Gateway\Validator\ResponseValidator</argument>
        </arguments>
    </virtualType>

    <virtualType name="VendorPaymentRefundRequest" type="Magento\Payment\Gateway\Request\BuilderComposite">
        <arguments>
            <argument name="builders" xsi:type="array">
                <item name="refund" xsi:type="string">Vendor\Payment\Gateway\Request\RefundDataBuilder</item>
            </argument>
        </arguments>
    </virtualType>

    <!-- Logger -->
    <virtualType name="VendorPaymentLogger" type="Magento\Payment\Model\Method\Logger">
        <arguments>
            <argument name="config" xsi:type="object">VendorPaymentConfig</argument>
        </arguments>
    </virtualType>

</config>
```
