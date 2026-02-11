# Payment Integration Skill

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

## Gateway Components

### Configuration Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Config\Config as GatewayConfig;

class Config extends GatewayConfig
{
    public const CODE = 'vendor_payment';
    public const KEY_ENVIRONMENT = 'environment';
    public const KEY_API_KEY = 'api_key';
    public const KEY_SECRET_KEY = 'secret_key';

    public const ENV_SANDBOX = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getEnvironment(?int $storeId = null): string
    {
        return $this->getValue(self::KEY_ENVIRONMENT, $storeId) ?? self::ENV_SANDBOX;
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey(?int $storeId = null): string
    {
        return (string) $this->getValue(self::KEY_API_KEY, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getSecretKey(?int $storeId = null): string
    {
        return (string) $this->getValue(self::KEY_SECRET_KEY, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isSandbox(?int $storeId = null): bool
    {
        return $this->getEnvironment($storeId) === self::ENV_SANDBOX;
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl(?int $storeId = null): string
    {
        return $this->isSandbox($storeId)
            ? 'https://sandbox.api.vendor.com/v1'
            : 'https://api.vendor.com/v1';
    }
}
```

### Subject Reader

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader as BaseSubjectReader;

class SubjectReader
{
    /**
     * @param array $subject
     * @return PaymentDataObjectInterface
     */
    public function readPayment(array $subject): PaymentDataObjectInterface
    {
        return BaseSubjectReader::readPayment($subject);
    }

    /**
     * @param array $subject
     * @return float
     */
    public function readAmount(array $subject): float
    {
        return (float) BaseSubjectReader::readAmount($subject);
    }

    /**
     * @param array $subject
     * @return string
     * @throws \InvalidArgumentException
     */
    public function readTransaction(array $subject): string
    {
        if (!isset($subject['transaction_id'])) {
            throw new \InvalidArgumentException('Transaction ID not found');
        }

        return (string) $subject['transaction_id'];
    }

    /**
     * @param array $subject
     * @return array
     * @throws \InvalidArgumentException
     */
    public function readResponse(array $subject): array
    {
        if (!isset($subject['response']) || !is_array($subject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        return $subject['response'];
    }
}
```

### Request Builders

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Vendor\Payment\Gateway\SubjectReader;

class TransactionDataBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader
    ) {
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        return [
            'amount' => $this->formatAmount($order->getGrandTotalAmount()),
            'currency' => $order->getCurrencyCode(),
            'order_id' => $order->getOrderIncrementId(),
            'description' => sprintf('Order #%s', $order->getOrderIncrementId()),
        ];
    }

    /**
     * @param float $amount
     * @return int
     */
    private function formatAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Vendor\Payment\Gateway\SubjectReader;

class CustomerDataBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader
    ) {
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $billingAddress = $order->getBillingAddress();

        return [
            'customer' => [
                'email' => $billingAddress->getEmail(),
                'name' => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
                'phone' => $billingAddress->getTelephone(),
            ],
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Vendor\Payment\Gateway\SubjectReader;

class CaptureDataBuilder implements BuilderInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader
    ) {
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        return [
            'transaction_id' => $payment->getParentTransactionId()
                ?? $payment->getLastTransId(),
            'amount' => $this->formatAmount(
                $this->subjectReader->readAmount($buildSubject)
            ),
        ];
    }

    /**
     * @param float $amount
     * @return int
     */
    private function formatAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
```

### HTTP Client

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferInterface;
use Vendor\Payment\Gateway\Config\Config;

class TransferFactory implements TransferFactoryInterface
{
    /**
     * @param TransferBuilder $transferBuilder
     * @param Config $config
     */
    public function __construct(
        private readonly TransferBuilder $transferBuilder,
        private readonly Config $config
    ) {
    }

    /**
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request): TransferInterface
    {
        return $this->transferBuilder
            ->setMethod('POST')
            ->setHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->config->getApiKey(),
            ])
            ->setBody(json_encode($request))
            ->setUri($this->config->getApiUrl() . '/transactions')
            ->build();
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class TransactionAuthorize implements ClientInterface
{
    /**
     * @param Curl $curl
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $this->curl->setHeaders($transferObject->getHeaders());

        try {
            $this->curl->post(
                $transferObject->getUri(),
                $transferObject->getBody()
            );

            $response = json_decode($this->curl->getBody(), true);

            $this->logger->debug('Payment API Response', [
                'status' => $this->curl->getStatus(),
                'response' => $response,
            ]);

            return [
                'status_code' => $this->curl->getStatus(),
                'response' => $response ?? [],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Payment API Error: ' . $e->getMessage());

            return [
                'status_code' => 500,
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

### Response Handlers

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Vendor\Payment\Gateway\SubjectReader;

class TransactionIdHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader
    ) {
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();

        $transactionId = $response['response']['transaction_id'] ?? null;

        if ($transactionId) {
            $payment->setTransactionId($transactionId);
            $payment->setIsTransactionClosed(false);
        }
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Vendor\Payment\Gateway\SubjectReader;

class PaymentDetailsHandler implements HandlerInterface
{
    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        private readonly SubjectReader $subjectReader
    ) {
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();

        $apiResponse = $response['response'] ?? [];

        // Store additional info
        $payment->setAdditionalInformation('gateway_status', $apiResponse['status'] ?? '');
        $payment->setAdditionalInformation('authorization_code', $apiResponse['auth_code'] ?? '');

        // Store card details (masked)
        if (isset($apiResponse['card'])) {
            $payment->setCcType($apiResponse['card']['brand'] ?? '');
            $payment->setCcLast4($apiResponse['card']['last4'] ?? '');
            $payment->setCcExpMonth($apiResponse['card']['exp_month'] ?? '');
            $payment->setCcExpYear($apiResponse['card']['exp_year'] ?? '');
        }
    }
}
```

### Validators

```php
<?php

declare(strict_types=1);

namespace Vendor\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Vendor\Payment\Gateway\SubjectReader;

class ResponseValidator extends AbstractValidator
{
    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly SubjectReader $subjectReader
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = $validationSubject['response'] ?? [];
        $isValid = true;
        $errorMessages = [];
        $errorCodes = [];

        // Check HTTP status
        $statusCode = $response['status_code'] ?? 0;
        if ($statusCode < 200 || $statusCode >= 300) {
            $isValid = false;
            $errorMessages[] = __('Payment gateway returned an error.');
            $errorCodes[] = $statusCode;
        }

        // Check API response
        $apiResponse = $response['response'] ?? [];
        if (isset($apiResponse['error'])) {
            $isValid = false;
            $errorMessages[] = $apiResponse['error']['message'] ?? __('Transaction failed.');
            $errorCodes[] = $apiResponse['error']['code'] ?? 'UNKNOWN';
        }

        // Check transaction status
        $status = $apiResponse['status'] ?? '';
        if (!in_array($status, ['approved', 'authorized', 'captured', 'refunded'])) {
            $isValid = false;
            $errorMessages[] = __('Transaction was declined.');
            $errorCodes[] = $status;
        }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
```

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
