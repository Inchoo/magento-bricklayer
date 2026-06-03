# Value Objects and DTOs in Magento 2

## Overview

A Value Object (or DTO — Data Transfer Object) is an immutable class with `readonly` properties that replaces raw associative arrays for structured data. It provides IDE autocomplete, compile-time type safety, and clear API boundaries.

This is distinct from Magento Data Interfaces (`Api/Data/*Interface`), which are mutable model contracts with getters and setters. Value Objects are for internal data transfer where immutability is preferred.

## When to Use

| Scenario | Use Value Object? |
|----------|:-----------------:|
| Serialized options stored in a JSON column (product options, config) | Yes |
| Passing structured data between services (not persisted directly) | Yes |
| Connection credentials (host, port, user, password) | Yes |
| Column mapping definitions | Yes |
| Simple key-value pair used once | No — use an array |
| Entity data exposed via REST API | No — use Data Interface |

## Implementation

### Basic Value Object

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

class ConnectionConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $username,
        public readonly string $password,
        public readonly bool $passive = true,
        public readonly int $timeout = 30
    ) {
    }
}
```

Usage:

```php
$config = new ConnectionConfig(
    host: 'ftp.example.com',
    port: 21,
    username: 'user',
    password: $decryptedPassword,
);

$config->host;     // IDE autocomplete, typed, immutable
$config->port;     // No getter needed — public readonly
```

### Value Object with JSON Serialization

For data stored in a database JSON column:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model;

class ItemOptions
{
    public function __construct(
        public readonly ?int $productId = null,
        public readonly ?int $simpleProductId = null,
        public readonly ?array $superAttribute = null,
        public readonly ?array $bundleOption = null,
        public readonly ?array $bundleOptionQty = null,
        public readonly ?array $customOptions = null
    ) {
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true) ?: [];

        return new self(
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            simpleProductId: isset($data['simple_product_id']) ? (int) $data['simple_product_id'] : null,
            superAttribute: $data['super_attribute'] ?? null,
            bundleOption: $data['bundle_option'] ?? null,
            bundleOptionQty: $data['bundle_option_qty'] ?? null,
            customOptions: $data['options'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->productId !== null) {
            $data['product_id'] = $this->productId;
        }
        if ($this->simpleProductId !== null) {
            $data['simple_product_id'] = $this->simpleProductId;
        }
        if ($this->superAttribute !== null) {
            $data['super_attribute'] = $this->superAttribute;
        }
        if ($this->bundleOption !== null) {
            $data['bundle_option'] = $this->bundleOption;
        }
        if ($this->bundleOptionQty !== null) {
            $data['bundle_option_qty'] = $this->bundleOptionQty;
        }
        if ($this->customOptions !== null) {
            $data['options'] = $this->customOptions;
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function isEmpty(): bool
    {
        return empty($this->toArray());
    }

    public function isConfigurable(): bool
    {
        return $this->superAttribute !== null;
    }

    public function isBundle(): bool
    {
        return $this->bundleOption !== null;
    }
}
```

Usage:

```php
// Reading from database
$options = ItemOptions::fromJson($item->getOptions());
$options->superAttribute;     // IDE autocomplete, typed
$options->isConfigurable();   // Semantic check

// Writing to database
$options = new ItemOptions(
    productId: (int) $product->getId(),
    superAttribute: $requestData['super_attribute'],
);
$item->setOptions($options->toJson());
```

## Value Object vs Array

| Aspect | Raw Array | Value Object |
|--------|-----------|-------------|
| Typo in key | Silent bug (`$arr['super_atribute']` returns null) | Compile error (`$vo->superAtribute` undefined) |
| IDE support | No autocomplete | Full autocomplete + type info |
| Validation | Must check manually | Constructor enforces types |
| Immutability | Anyone can modify `$arr['key'] = 'new'` | `readonly` prevents modification |
| Documentation | Must read code to know structure | Properties document themselves |

## Value Object vs Data Interface

| Aspect | Value Object | Data Interface |
|--------|-------------|----------------|
| Mutability | Immutable (`readonly`) | Mutable (has setters) |
| Purpose | Internal data transfer | Public API contract |
| Persistence | Not directly persisted | Backed by Model + ResourceModel |
| DI registration | Not needed | Preference in `di.xml` |
| Extension attributes | No | Yes (`ExtensibleDataInterface`) |
| REST/GraphQL exposure | No | Yes (via `webapi.xml`) |

## Best Practices

### DO

- Use `public readonly` properties — no getters needed
- Use named arguments when constructing: `new ItemOptions(productId: 42)`
- Make all properties nullable with defaults for optional fields
- Provide `fromJson()` / `toJson()` for database boundary conversion
- Add semantic helper methods (`isConfigurable()`, `isEmpty()`)

### DON'T

- Don't add setters — Value Objects are immutable
- Don't extend `AbstractModel` — Value Objects are not Magento models
- Don't use Value Objects for REST API responses — use Data Interfaces
- Don't create Value Objects for trivial one-field data — a typed parameter is simpler
