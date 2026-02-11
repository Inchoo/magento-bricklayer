# Observer Guidelines

## Overview

Observers react to events dispatched throughout Magento. They're ideal for decoupled functionality that responds to system changes.

## When to Use Observers

| Use Case | Recommended |
|----------|-------------|
| React to state changes | Observer |
| Log/audit actions | Observer |
| Sync to external systems | Observer |
| Modify method behavior | Plugin (not observer) |
| Replace functionality | Preference (not observer) |

## Observer Configuration

### etc/events.xml (Global)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">

    <event name="sales_order_place_after">
        <observer name="vendor_module_order_placed"
                  instance="Vendor\Module\Observer\OrderPlacedObserver"/>
    </event>

</config>
```

### Area-Specific Events

| File | Area |
|------|------|
| `etc/events.xml` | Global |
| `etc/frontend/events.xml` | Frontend |
| `etc/adminhtml/events.xml` | Admin |
| `etc/webapi_rest/events.xml` | REST API |
| `etc/graphql/events.xml` | GraphQL |

## Observer Implementation

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class OrderPlacedObserver implements ObserverInterface
{
    /**
     * @param LoggerInterface $logger
     * @param OrderSyncService $syncService
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly OrderSyncService $syncService
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getData('order');

        if ($order === null) {
            return;
        }

        try {
            $this->syncService->syncOrder($order);

            $this->logger->info('Order synced', [
                'increment_id' => $order->getIncrementId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Order sync failed', [
                'increment_id' => $order->getIncrementId(),
                'error' => $e->getMessage()
            ]);
            // Don't throw - let other observers execute
        }
    }
}
```

## Accessing Event Data

```php
/**
 * @param Observer $observer
 * @return void
 */
public function execute(Observer $observer): void
{
    // Get event object
    $event = $observer->getEvent();

    // Get specific data
    $product = $event->getData('product');
    $customer = $event->getData('customer');

    // Alternative syntax
    $order = $event->getOrder();

    // Get all data
    $allData = $event->getData();
}
```

## Observer Attributes

| Attribute | Purpose |
|-----------|---------|
| `name` | Unique identifier for the observer |
| `instance` | Observer class |
| `disabled` | Set to "true" to disable |
| `shared` | Whether to share instance (default: true) |

## Disabling an Observer

```xml
<event name="catalog_product_save_after">
    <observer name="vendor_module_product_save" disabled="true"/>
</event>
```

## Common Mistakes

### Don't Modify $observer

```php
// WRONG
/**
 * @param Observer $observer
 * @return void
 */
public function execute(Observer $observer): void
{
    $order = $observer->getEvent()->getOrder();
    $order->setCustomNote('Modified');  // May not persist
}

// RIGHT - Use repository or model save
/**
 * @param Observer $observer
 * @return void
 */
public function execute(Observer $observer): void
{
    $order = $observer->getEvent()->getOrder();
    $orderId = $order->getId();

    $orderModel = $this->orderRepository->get($orderId);
    $orderModel->setCustomNote('Modified');
    $this->orderRepository->save($orderModel);
}
```

### Don't Throw Exceptions (Usually)

```php
// WRONG - Breaks event chain
/**
 * @param Observer $observer
 * @return void
 */
public function execute(Observer $observer): void
{
    throw new \Exception('Error occurred');
}

// RIGHT - Log and continue
/**
 * @param Observer $observer
 * @return void
 */
public function execute(Observer $observer): void
{
    try {
        // Process
    } catch (\Exception $e) {
        $this->logger->error($e->getMessage());
    }
}
```

## Best Practices

1. **Use specific areas** - Don't use global for frontend-only events
2. **Handle exceptions** - Don't break the event chain
3. **Keep lightweight** - Offload heavy processing to services
4. **Use unique names** - Include vendor/module prefix
5. **Don't modify core data** - Observers are for reactions
6. **Prefer async processing** - Use message queue for heavy tasks
