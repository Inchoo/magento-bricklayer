# Payment Integration: Gateway Components

> Related: See [payment-integration-core](../payment-integration-core/SKILL.md) for module setup and DI config, and [payment-integration-checkout](../payment-integration-checkout/SKILL.md) for checkout UI.

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
