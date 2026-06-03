# Context Object Pattern in Magento 2

## Overview

A Context Object bundles multiple related parameters that are repeatedly passed together across service methods. Instead of threading the same 4-5 arguments through every call, package them in a single immutable object.

## When to Use

| Scenario | Use Context Object? |
|----------|:-------------------:|
| 3+ parameters passed together to multiple methods (customerId, storeId, listId) | Yes |
| Request-scoped data needed across a processing chain (store, currency, locale) | Yes |
| Export context (store, URLs, taxonomy maps, profile) | Yes |
| A single method needs two parameters | No — pass them directly |
| Data that should be persisted in database | No — use a Model or Value Object |

## Implementation

### 1. Define the Context

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

class OperationContext
{
    public function __construct(
        public readonly int $customerId,
        public readonly int $storeId,
        public readonly int $listId,
        public readonly string $currencyCode
    ) {
    }
}
```

### 2. Create a Builder

The Builder encapsulates the logic for gathering context data from session, store manager, etc.:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;

class OperationContextBuilder
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function build(int $listId): OperationContext
    {
        $store = $this->storeManager->getStore();

        return new OperationContext(
            customerId: (int) $this->customerSession->getCustomerId(),
            storeId: (int) $store->getId(),
            listId: $listId,
            currencyCode: $store->getCurrentCurrencyCode()
        );
    }
}
```

### 3. Use in Service Methods

```php
// Without Context — same parameters repeated everywhere:
$this->service1->addProduct($customerId, $storeId, $listId, $currencyCode, $productId);
$this->service2->addToCart($customerId, $storeId, $listId, $currencyCode, $itemIds);
$this->service3->copyItems($customerId, $storeId, $listId, $currencyCode, $targetId);

// With Context — build once, pass everywhere:
$context = $this->contextBuilder->build($listId);

$this->service1->addProduct($context, $productId);
$this->service2->addToCart($context, $itemIds);
$this->service3->copyItems($context, $targetId);
```

## Adding New Fields

When a new field is needed (e.g., `customerGroupId` for price calculation):

1. Add property to `OperationContext`
2. Add data gathering to `OperationContextBuilder::build()`
3. All services automatically have access via `$context->customerGroupId`

No method signatures change. No call sites change.

## Context Object vs Value Object

| Aspect | Context Object | Value Object |
|--------|---------------|-------------|
| Purpose | Carries request/execution state | Carries business/entity data |
| Lifecycle | One per request or action | One per entity or record |
| Persisted? | No | Often yes (JSON column, etc.) |
| Created by | Builder (from session, store, config) | Factory or `fromJson()` |
| Example | customerId + storeId + currencyCode | superAttribute + bundleOption |

Both use `public readonly` properties and are immutable.

## Best Practices

### DO

- Use a Builder to construct the Context — don't build it manually in every controller/component
- Cache the Context within a request (lazy `$this->context ??= $this->builder->build()`)
- Keep Context properties to data that multiple services actually need
- Use `public readonly` — no getters necessary

### DON'T

- Don't put business logic in the Context — it's a data carrier
- Don't use Context for data that only one method needs — pass it as a direct parameter
- Don't persist Context objects — they represent transient request state
- Don't make Context mutable — if you need different state, create a new Context
