# Shipping Integration Skill

## Overview

Magento 2 shipping integrations enable custom shipping carriers to calculate rates, track shipments, and handle shipping labels. This skill covers creating carrier modules that integrate with the Magento shipping system.

## Carrier Types

| Type | Description | Example |
|------|-------------|---------|
| Online | Real-time rate calculation via API | UPS, FedEx, DHL |
| Offline | Fixed or table-based rates | Flat Rate, Table Rate |
| Hybrid | Cached rates with periodic updates | Custom implementations |

## Creating a Shipping Carrier

### 1. Module Setup

```
app/code/Vendor/Shipping/
├── etc/
│   ├── adminhtml/
│   │   └── system.xml
│   ├── config.xml
│   ├── di.xml
│   └── module.xml
├── Model/
│   └── Carrier/
│       └── CustomCarrier.php
└── registration.php
```

### 2. Carrier Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class CustomCarrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected string $_code = 'customcarrier';

    /**
     * @var bool
     */
    protected bool $_isFixed = false;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->getConfigData('active');
    }

    /**
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return [
            'standard' => $this->getConfigData('name') . ' - Standard',
            'express' => $this->getConfigData('name') . ' - Express',
        ];
    }

    /**
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request): \Magento\Shipping\Model\Rate\Result|bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $result = $this->rateResultFactory->create();

        // Validate request
        if (!$this->validateRequest($request)) {
            return $this->getErrorResult();
        }

        // Calculate and add methods
        $this->addStandardMethod($result, $request);
        $this->addExpressMethod($result, $request);

        return $result;
    }

    /**
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param RateRequest $request
     * @return void
     */
    private function addStandardMethod(
        \Magento\Shipping\Model\Rate\Result $result,
        RateRequest $request
    ): void {
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod('standard');
        $method->setMethodTitle(__('Standard Delivery (3-5 days)'));

        $price = $this->calculateStandardRate($request);
        $method->setPrice($price);
        $method->setCost($price);

        $result->append($method);
    }

    /**
     * @param \Magento\Shipping\Model\Rate\Result $result
     * @param RateRequest $request
     * @return void
     */
    private function addExpressMethod(
        \Magento\Shipping\Model\Rate\Result $result,
        RateRequest $request
    ): void {
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod('express');
        $method->setMethodTitle(__('Express Delivery (1-2 days)'));

        $price = $this->calculateExpressRate($request);
        $method->setPrice($price);
        $method->setCost($price);

        $result->append($method);
    }

    /**
     * @param RateRequest $request
     * @return float
     */
    private function calculateStandardRate(RateRequest $request): float
    {
        $baseRate = (float) $this->getConfigData('price');
        $weight = $request->getPackageWeight();

        // Weight-based calculation
        $weightRate = $weight * 0.50;

        // Free shipping threshold
        $freeShippingThreshold = (float) $this->getConfigData('free_shipping_threshold');
        if ($freeShippingThreshold > 0 && $request->getPackageValue() >= $freeShippingThreshold) {
            return 0;
        }

        return $baseRate + $weightRate;
    }

    /**
     * @param RateRequest $request
     * @return float
     */
    private function calculateExpressRate(RateRequest $request): float
    {
        return $this->calculateStandardRate($request) * 1.5;
    }

    /**
     * @param RateRequest $request
     * @return bool
     */
    private function validateRequest(RateRequest $request): bool
    {
        // Check allowed countries
        $allowedCountries = $this->getConfigData('specificcountry');
        if ($allowedCountries) {
            $countries = explode(',', $allowedCountries);
            if (!in_array($request->getDestCountryId(), $countries)) {
                return false;
            }
        }

        // Check maximum weight
        $maxWeight = (float) $this->getConfigData('max_weight');
        if ($maxWeight > 0 && $request->getPackageWeight() > $maxWeight) {
            return false;
        }

        return true;
    }

    /**
     * @return \Magento\Shipping\Model\Rate\Result
     */
    private function getErrorResult(): \Magento\Shipping\Model\Rate\Result
    {
        $result = $this->rateResultFactory->create();
        $error = $this->_rateErrorFactory->create();

        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setErrorMessage($this->getConfigData('specificerrmsg'));

        $result->append($error);
        return $result;
    }
}
```

### 3. Configuration (etc/config.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd">
    <default>
        <carriers>
            <customcarrier>
                <active>0</active>
                <title>Custom Carrier</title>
                <name>Custom Shipping</name>
                <price>5.00</price>
                <free_shipping_threshold>100</free_shipping_threshold>
                <max_weight>50</max_weight>
                <sallowspecific>0</sallowspecific>
                <specificerrmsg>This shipping method is not available.</specificerrmsg>
                <model>Vendor\Shipping\Model\Carrier\CustomCarrier</model>
            </customcarrier>
        </carriers>
    </default>
</config>
```

### 4. Admin Configuration (etc/adminhtml/system.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <system>
        <section id="carriers">
            <group id="customcarrier" translate="label" sortOrder="150" showInDefault="1"
                   showInWebsite="1" showInStore="1">
                <label>Custom Carrier</label>

                <field id="active" translate="label" type="select" sortOrder="10"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Enabled</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>

                <field id="title" translate="label" type="text" sortOrder="20"
                       showInDefault="1" showInWebsite="1" showInStore="1">
                    <label>Title</label>
                </field>

                <field id="name" translate="label" type="text" sortOrder="30"
                       showInDefault="1" showInWebsite="1" showInStore="1">
                    <label>Method Name</label>
                </field>

                <field id="price" translate="label" type="text" sortOrder="40"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Base Price</label>
                    <validate>validate-number validate-zero-or-greater</validate>
                </field>

                <field id="free_shipping_threshold" translate="label" type="text" sortOrder="50"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Free Shipping Threshold</label>
                    <comment>Order subtotal for free shipping (0 to disable)</comment>
                </field>

                <field id="max_weight" translate="label" type="text" sortOrder="60"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Maximum Package Weight</label>
                </field>

                <field id="sallowspecific" translate="label" type="select" sortOrder="70"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Ship to Applicable Countries</label>
                    <source_model>Magento\Shipping\Model\Config\Source\Allspecificcountries</source_model>
                </field>

                <field id="specificcountry" translate="label" type="multiselect" sortOrder="80"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Ship to Specific Countries</label>
                    <source_model>Magento\Directory\Model\Config\Source\Country</source_model>
                    <depends>
                        <field id="sallowspecific">1</field>
                    </depends>
                </field>

                <field id="specificerrmsg" translate="label" type="textarea" sortOrder="90"
                       showInDefault="1" showInWebsite="1" showInStore="1">
                    <label>Displayed Error Message</label>
                </field>

                <field id="sort_order" translate="label" type="text" sortOrder="100"
                       showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Sort Order</label>
                </field>
            </group>
        </section>
    </system>
</config>
```

## Online Carrier with API Integration

```php
<?php

declare(strict_types=1);

namespace Vendor\Shipping\Model\Carrier;

use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\DataObject;

class ApiCarrier extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * @var string
     */
    protected string $_code = 'apicarrier';

    /**
     * @param string $trackingNumber
     * @return DataObject
     */
    public function getTracking(string $trackingNumber): DataObject
    {
        $result = $this->_trackFactory->create();

        // Call external API
        $trackingInfo = $this->apiClient->getTrackingInfo($trackingNumber);

        $status = $this->_trackStatusFactory->create();
        $status->setCarrier($this->_code);
        $status->setCarrierTitle($this->getConfigData('title'));
        $status->setTracking($trackingNumber);
        $status->setTrackSummary($trackingInfo['status']);
        $status->setUrl($trackingInfo['url']);

        $result->append($status);

        return $result;
    }

    /**
     * @param DataObject $request
     * @return DataObject
     */
    protected function _doShipmentRequest(DataObject $request): DataObject
    {
        $result = new DataObject();

        try {
            // Call carrier API to create shipment
            $response = $this->apiClient->createShipment([
                'origin' => $this->getOriginAddress($request),
                'destination' => $this->getDestinationAddress($request),
                'packages' => $this->getPackagesData($request),
            ]);

            $result->setShippingLabelContent($response['label']);
            $result->setTrackingNumber($response['tracking_number']);

        } catch (\Exception $e) {
            $result->setErrors($e->getMessage());
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isShippingLabelsAvailable(): bool
    {
        return true;
    }

    /**
     * @param DataObject|null $params
     * @return array
     */
    public function getContainerTypes(?DataObject $params = null): array
    {
        return [
            'PACKAGE' => __('Package'),
            'ENVELOPE' => __('Envelope'),
            'TUBE' => __('Tube'),
        ];
    }
}
```

## Tracking Integration

```php
/**
 * @param int $shipmentId
 * @param string $carrierCode
 * @param string $title
 * @param string $trackNumber
 * @return void
 */
public function addTrackingToShipment(
    int $shipmentId,
    string $carrierCode,
    string $title,
    string $trackNumber
): void {
    $track = $this->trackFactory->create();
    $track->setCarrierCode($carrierCode)
        ->setTitle($title)
        ->setTrackNumber($trackNumber);

    $shipment = $this->shipmentRepository->get($shipmentId);
    $shipment->addTrack($track);
    $this->shipmentRepository->save($shipment);
}
```

## Multi-Warehouse Support

```php
/**
 * @param RateRequest $request
 * @return array
 */
public function getShippingOrigin(RateRequest $request): array
{
    $items = $request->getAllItems();
    $sourceCode = $this->sourceSelectionService->getSourceForItems($items);

    $source = $this->sourceRepository->get($sourceCode);

    return [
        'country' => $source->getCountryId(),
        'region' => $source->getRegionId(),
        'postcode' => $source->getPostcode(),
        'city' => $source->getCity(),
        'street' => $source->getStreet(),
    ];
}
```

## Best Practices

1. **Cache API responses** to reduce external calls
2. **Implement proper error handling** with meaningful messages
3. **Use asynchronous calls** for tracking updates
4. **Support multiple package types** where applicable
5. **Validate addresses** before rate calculation
6. **Log all API interactions** for debugging
7. **Handle rate limiting** from carrier APIs
8. **Test with realistic data** including edge cases
