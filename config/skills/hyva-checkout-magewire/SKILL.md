# Hyvä Checkout: Magewire Components

> Related: See [magewire](../magewire/SKILL.md) for Magewire V1 fundamentals, [magewire-three](../magewire-three/SKILL.md) for Magewire V3 fundamentals and migrations, [hyva-checkout-configuration](../hyva-checkout-configuration/SKILL.md) for checkout XML and layout config, and [hyva-checkout-apis](../hyva-checkout-apis/SKILL.md) for evaluation, form, and frontend APIs.

## Overview

Hyvä Checkout is a reactive, server-driven checkout for Magento 2 built on **Magewire** (a Magento 2 adaptation of Laravel Livewire). It replaces Magento's Luma/KnockoutJS checkout with PHP-based components that handle reactivity via AJAX round-trips. The checkout is composed of **steps** declared in `hyva_checkout.xml`, with **Magewire components** declared in Layout XML and rendered through `.phtml` templates using `wire:` directives.

Check the installed Hyvä Checkout version before applying Magewire APIs:

| Hyvä Checkout version | Magewire generation | Fundamentals |
|---|---|---|
| 1.0-1.3 | Magewire V1 | Use `magewire` |
| 1.4+ | Magewire V3 | Use `magewire-three` |

Use this skill for checkout-specific structure and APIs in either range. For component directives,
events, lifecycle hooks, and state behavior, use the matching versioned Magewire skill.

## When to Use This Skill

| Scenario | Use This Skill? |
|----------|-----------------|
| Customizing Hyvä Checkout steps or layout | Yes |
| Adding a custom payment method integration | Yes |
| Adding a custom shipping method view | Yes |
| Creating custom checkout form fields | Yes |
| Implementing custom order placement logic | Yes |
| Extending existing checkout Magewire components | Yes |
| Building evaluation/validation for checkout | Yes |
| Building Magewire components outside checkout | No (use `magewire`) |
| Standard Magento checkout (Luma/KnockoutJS) | No (use `checkout`) |
| Hyvä theme setup / child themes | No (use `hyva-theme`) |
| Non-checkout Hyvä UI components | No (use `hyva-ui-component`) |

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

## Magewire Quick Reference

This quick reference reflects the Magewire V1 API used by Hyvä Checkout 1.0-1.3. For Hyvä Checkout 1.4+, use [`magewire-three`](../magewire-three/SKILL.md) for component syntax while retaining the checkout-specific patterns from this skill.

### Basic Component Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire;

use Magewirephp\Magewire\Component;

class MyCheckoutComponent extends Component
{
    /**
     * @var string
     */
    public string $selectedMethod = '';

    /**
     * @var bool
     */
    public bool $isComplete = false;

    /**
     * @param string $code
     * @return void
     */
    public function selectMethod(string $code): void
    {
        $this->selectedMethod = $code;
    }
}
```

### `wire:` Directives Summary

| Directive | Purpose |
|-----------|---------|
| `wire:model` | Two-way bind to property (every keystroke) |
| `wire:model.lazy` | Two-way bind on blur/change (preferred for form inputs) |
| `wire:click` | Call method on click |
| `wire:submit` | Call method on form submit |
| `wire:ignore` | Exclude subtree from DOM diffing |
| `$set('prop', val)` | Magic action: set property |
| `$toggle('prop')` | Magic action: toggle boolean |
| `$refresh()` | Magic action: re-render component |

### Events (Component Coordination)

Events are critical in checkout — components must coordinate across steps (e.g., shipping selection triggers payment refresh).

```php
class ShippingComponent extends Component
{
    /**
     * @var array<string, string>
     */
    protected $listeners = [
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

        // Emit to ALL listening components
        $this->emit('shipping_method_selected', ['method' => $code]);

        // Emit to a SPECIFIC component by block name
        $this->emitTo('checkout.payment.methods', 'refresh');

        // Emit only to self
        $this->emitSelf('method_updated');
    }
}
```

### Alpine.js Integration (`$wire` and `entangle`)

```html
<div x-data>
    <!-- Read Magewire property from Alpine -->
    <span x-text="$wire.selectedMethod"></span>

    <!-- Call PHP method from Alpine -->
    <button x-on:click="$wire.selectMethod('flatrate')">Select</button>

    <!-- Set property from Alpine -->
    <button x-on:click="$wire.set('isComplete', true)">Complete</button>
</div>

<!-- Two-way sync between Alpine local state and Magewire server state -->
<div x-data="{ localMethod: $wire.entangle('selectedMethod') }">
    <select x-model="localMethod">
        <option value="flatrate">Flat Rate</option>
        <option value="freeshipping">Free Shipping</option>
    </select>
</div>
```

### Emitting Events from JavaScript

```javascript
// Emit to all listening components
Magewire.emit('shipping_address_saved', { addressId: 123 });

// Emit to a specific component
Magewire.emitTo('checkout.payment.methods', 'refresh');
```

## Checkout-Specific Patterns

### Payment Method Component

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Checkout\Payment\Method;

use Hyva\Checkout\Magewire\Checkout\Payment\AbstractPaymentMethod;

class CustomGateway extends AbstractPaymentMethod
{
    /**
     * Payment method code — must match etc/payment.xml and di.xml config.
     *
     * @var string
     */
    protected string $methodCode = 'custom_gateway';

    /**
     * @var string
     */
    public string $cardToken = '';

    /**
     * Called by Hyvä Checkout when this payment method is submitted.
     * Use to set additional payment information on the quote.
     *
     * @return void
     */
    public function placeOrder(): void
    {
        try {
            $quote = $this->getQuote();
            $payment = $quote->getPayment();
            $payment->setAdditionalInformation('card_token', $this->cardToken);

            parent::placeOrder();
        } catch (\Exception $e) {
            $this->dispatchErrorMessage($e->getMessage());
        }
    }
}
```

### Shipping Method Component

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Checkout\Shipping\Method;

use Magewirephp\Magewire\Component;

class CustomCarrier extends Component
{
    /**
     * @var string
     */
    public string $selectedOption = '';

    /**
     * @var array<string, string>
     */
    protected $listeners = [
        'shipping_address_saved' => 'loadOptions',
    ];

    /**
     * @return void
     */
    public function loadOptions(): void
    {
        // Load available options for the current address
    }

    /**
     * @param string $optionCode
     * @return void
     */
    public function selectOption(string $optionCode): void
    {
        $this->selectedOption = $optionCode;
        $this->emit('shipping_method_selected', ['method' => $optionCode]);
    }
}
```

### Step Completion via Browser Events

Checkout step navigation is driven by browser events. Dispatch from PHP when a step is complete:

```php
/**
 * @return void
 */
public function onStepComplete(): void
{
    $this->dispatchBrowserEvent('checkout:step:complete', [
        'step' => 'shipping',
    ]);
}
```

### Checkout Form Integration

Embed custom form fields within a checkout step:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Checkout;

use Magewirephp\Magewire\Component;

class CustomFields extends Component
{
    /**
     * @var string
     */
    public string $deliveryNote = '';

    /**
     * @var string
     */
    public string $preferredDate = '';

    /**
     * @var array<string, string>
     */
    protected $listeners = [
        'checkout:order:place:before' => 'saveFields',
    ];

    /**
     * Save custom fields to the quote before order placement.
     *
     * @return void
     */
    public function saveFields(): void
    {
        $quote = $this->getSessionQuote();
        $quote->setData('delivery_note', $this->deliveryNote);
        $quote->setData('preferred_date', $this->preferredDate);
    }
}
```

Template:

```php
<?php

declare(strict_types=1);

use Vendor\Module\Magewire\Checkout\CustomFields;
use Magento\Framework\Escaper;

/** @var CustomFields $magewire */
/** @var Escaper $escaper */
?>

<div>
    <div class="field">
        <label for="delivery-note">
            <?= $escaper->escapeHtml(__('Delivery Note')) ?>
        </label>
        <textarea id="delivery-note"
                  wire:model.lazy="deliveryNote"
                  rows="3"></textarea>
    </div>
    <div class="field">
        <label for="preferred-date">
            <?= $escaper->escapeHtml(__('Preferred Delivery Date')) ?>
        </label>
        <input type="date"
               id="preferred-date"
               wire:model.lazy="preferredDate" />
    </div>
</div>
```
