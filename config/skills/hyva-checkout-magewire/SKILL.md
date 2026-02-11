# Hyvä Checkout: Magewire Components

> Related: See [hyva-checkout-configuration](../hyva-checkout-configuration/SKILL.md) for checkout XML and layout config, and [hyva-checkout-apis](../hyva-checkout-apis/SKILL.md) for evaluation, form, and frontend APIs.

## Overview

Hyva Checkout is a reactive, server-driven checkout for Magento 2 built on **Magewire** (a Magento 2 adaptation of Laravel Livewire). It replaces Magento's Luma/KnockoutJS checkout with PHP-based components that handle reactivity via AJAX round-trips. The checkout is composed of **steps** declared in `hyva_checkout.xml`, with **Magewire components** declared in Layout XML and rendered through `.phtml` templates using `wire:` directives.

## When to Use This Skill

| Scenario | Use This Skill? |
|----------|-----------------|
| Customizing Hyva Checkout steps or layout | Yes |
| Adding a custom payment method integration | Yes |
| Adding a custom shipping method view | Yes |
| Building Magewire components (reactive PHP) | Yes |
| Creating custom checkout form fields | Yes |
| Implementing custom order placement logic | Yes |
| Extending existing checkout Magewire components | Yes |
| Building evaluation/validation for checkout | Yes |
| Standard Magento checkout (Luma/KnockoutJS) | No (use checkout-customization) |
| Hyva theme setup / child themes | No (use hyva-theme-development) |
| Non-checkout Hyva UI components | No (use hyva-ui-component-development) |

## Architecture Overview

```
┌──────────────────────────────────────────────────────────┐
│  hyva_checkout.xml  (checkout configs, steps, conditions)  │
├──────────────────────────────────────────────────────────┤
│  Layout XML         (component declarations, move/arrange)  │
├──────────────────────────────────────────────────────────┤
│  Magewire PHP       (server-side reactive components)       │
├──────────────────────────────────────────────────────────┤
│  .phtml templates   (wire: directives, ViewModels)          │
├──────────────────────────────────────────────────────────┤
│  Frontend API JS    (evaluation, validation, navigation)    │
├──────────────────────────────────────────────────────────┤
│  Alpine.js + Livewire.js  (client-side reactivity)          │
└──────────────────────────────────────────────────────────┘
```

**Key module:** `Hyva_Checkout` (depends on `Magewirephp_Magewire` and `Magento_Checkout`)

**Route:** `/hyva_checkout/`

## Magewire Component Fundamentals

### Basic Component Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire;

use Magewirephp\Magewire\Component;

class MyComponent extends Component
{
    // Public properties are reactive (synced with frontend)

    /**
     * @var string
     */
    public string $name = '';

    /**
     * @var int
     */
    public int $count = 0;

    /**
     * @return void
     */
    public function increment(): void
    {
        $this->count++;
    }
}
```

### Registering in Layout XML

```xml
<!-- Simple object reference -->
<block name="my.component"
       template="Vendor_Module::magewire/my-component.phtml">
    <arguments>
        <argument name="magewire" xsi:type="object">
            \Vendor\Module\Magewire\MyComponent
        </argument>
    </arguments>
</block>

<!-- With initial property values -->
<block name="my.component"
       template="Vendor_Module::magewire/my-component.phtml">
    <arguments>
        <argument name="magewire" xsi:type="array">
            <item name="type" xsi:type="object">\Vendor\Module\Magewire\MyComponent</item>
            <item name="name" xsi:type="string">Default Name</item>
        </argument>
    </arguments>
</block>
```

### Template with `wire:` Directives

```php
<?php

declare(strict_types=1);

use Vendor\Module\Magewire\MyComponent;
use Magento\Framework\Escaper;

/** @var MyComponent $magewire */
/** @var Escaper $escaper */
?>

<div>
    <!-- Two-way data binding -->
    <input type="text" wire:model="name" />

    <!-- Lazy binding (on blur) -->
    <input type="text" wire:model.lazy="name" />

    <!-- Call method on click -->
    <button wire:click="increment">
        Count: <?= $escaper->escapeHtml((string) $magewire->count) ?>
    </button>

    <!-- Magic actions -->
    <button wire:click="$set('count', 0)">Reset</button>
    <button wire:click="$toggle('active')">Toggle</button>
    <button wire:click="$refresh()">Refresh</button>

    <!-- Prevent DOM re-rendering for an element -->
    <div wire:ignore>
        <!-- Third-party JS widget here -->
    </div>
</div>
```

### Component Lifecycle Hooks

```php
class MyComponent extends Component
{
    /**
     * @return void
     */
    public function boot(): void {}

    /**
     * @return void
     */
    public function mount(): void {}

    /**
     * @return void
     */
    public function booted(): void {}

    /**
     * @return void
     */
    public function hydrate(): void {}

    /**
     * @param mixed $value
     * @param string $name
     * @return void
     */
    public function updating(mixed $value, string $name): void {}

    /**
     * @param mixed $value
     * @param string $name
     * @return void
     */
    public function updated(mixed $value, string $name): void {}

    /**
     * @param mixed $value
     * @return void
     */
    public function updatingMethod(mixed $value): void {}

    /**
     * @param mixed $value
     * @return string
     */
    public function updatedMethod(mixed $value): string { return $value; }

    /**
     * @param mixed $value
     * @return void
     */
    public function updatedDataEmailAddress(mixed $value): void {}

    /**
     * @return void
     */
    public function dehydrate(): void {}
}
```

### Events Between Components

```php
class ShippingComponent extends Component
{
    // Declare listeners: event name => method name

    /**
     * @var array
     */
    protected array $listeners = [
        'shipping_address_saved' => 'refresh',
        'coupon_code_applied' => 'refresh',
    ];

    /**
     * @param string $code
     * @return void
     */
    public function selectMethod(string $code): void
    {
        // Save method...

        // Emit to ALL components listening for this event
        $this->emit('shipping_method_selected', ['method' => $code]);

        // Emit to a SPECIFIC component by block name
        $this->emitTo('checkout.payment.methods', 'refresh');

        // Emit only to self
        $this->emitSelf('method_updated');
    }
}
```

### Flash Messages

```php
/**
 * @return void
 */
public function save(): void
{
    try {
        // Save logic...
        $this->dispatchSuccessMessage('Your changes were saved.');
    } catch (LocalizedException $e) {
        $this->dispatchErrorMessage($e->getMessage());
        // Also available:
        // $this->dispatchWarningMessage('Warning text');
        // $this->dispatchNoticeMessage('Notice text');
    }
}
```

### Browser Events from PHP

```php
/**
 * @return void
 */
public function onComplete(): void
{
    $this->dispatchBrowserEvent('checkout:step:complete', [
        'step' => 'shipping'
    ]);
}
```

### Loader Configuration

```php
class PaymentMethodList extends Component
{
    // Show loader text while specific properties/methods are processing

    /**
     * @var array
     */
    protected array $loader = [
        'method' => 'Saving method',         // Property update
        'placeOrder' => 'Processing order',   // Method call
    ];
}
```

## Alpine.js Integration with Magewire

### Accessing Component via `$wire`

```html
<div x-data>
    <h1 x-text="$wire.name"></h1>
    <button x-on:click="$wire.set('name', 'New Value')">Update</button>
    <button x-on:click="$wire.increment()">Call Method</button>
</div>
```

### Entangle: Two-Way Sync Between Alpine and Magewire

```html
<div x-data="{ localValue: $wire.entangle('serverValue') }">
    <!-- Changes to localValue sync to PHP $serverValue and vice versa -->
    <input type="text" x-model="localValue" />
</div>
```

### Emitting Events from JavaScript

```javascript
// Emit to all listening components
Magewire.emit('shipping_address_saved', { addressId: 123 });

// Emit to a specific component
Magewire.emitTo('checkout.payment.methods', 'refresh');
```
