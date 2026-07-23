---
name: hyva-checkout-magewire
description: Build and review Hyva Checkout Magewire components with version-correct lifecycle, events, Evaluation API participation, layout independence, and safe quote state handling for Magewire V1 and V3 checkout lines.
---

# Hyvä Checkout: Magewire Components

> Related: Start with [hyva-checkout](../hyva-checkout/SKILL.md) for primary navigation and order-placement architecture. See [magewire](../magewire/SKILL.md) for Magewire V1, [magewire-three](../magewire-three/SKILL.md) for Magewire V3, [hyva-checkout-configuration](../hyva-checkout-configuration/SKILL.md) for XML, and [hyva-checkout-apis](../hyva-checkout-apis/SKILL.md) for Evaluation and frontend APIs.

## Overview

Hyvä Checkout is a reactive, server-driven checkout for Magento 2 built on **Magewire** (a Magento 2 adaptation of Laravel Livewire). It replaces Magento's Luma/KnockoutJS checkout with PHP-based components that handle reactivity via AJAX round-trips. The checkout is composed of **steps** declared in `hyva_checkout.xml`, with **Magewire components** declared in Layout XML and rendered through `.phtml` templates using `wire:` directives.

The core `hyva-checkout` skill must resolve the compatibility profile before this resource is used.
Select one track and ignore examples from the other track:

| Compatibility profile | Use this track | Do not introduce |
|---|---|---|
| Checkout 1.0-1.3.* | V1 | `#[On]`, `dispatch()`, or the V3 BC attribute |
| Checkout 1.4+ new component | Native V3 | `$listeners`, `emit*()`, or V1 directive semantics |
| Checkout 1.4+ existing V1 component | Migration | Behavioral redesign while establishing BC |

Shared checkout APIs such as Evaluation and Place Order Services do not make component syntax
interchangeable. For directives, events, lifecycle, validation, and JavaScript, load only the
versioned Magewire resource selected by the compatibility profile.

### Track Syntax Matrix

| Concern | V1 on checkout 1.0-1.3.* | Native V3 on checkout 1.4+ | Existing V1 on checkout 1.4+ |
|---|---|---|---|
| PHP listener | `$listeners` | `#[On('event')]` | Keep `$listeners` until migrated |
| PHP dispatch | `emit*()` | `dispatch()`, `dispatch()->self()`, or `dispatch()->to()` | Keep `emit*()` until migrated |
| Live input | `wire:model` | `wire:model.live` | BC rewrites `wire:model` |
| Blur/change input | `wire:model.lazy` | `wire:model.blur` | BC rewrites `wire:model.lazy` |
| Deferred input | `wire:model.defer` | `wire:model` | BC rewrites `wire:model.defer` |
| Live entanglement | `$wire.entangle('x')` | `$wire.entangle('x').live` | BC restores V1 behavior |
| Component attribute | None | `#[HandleBackwardsCompatibility(enabled: false)]` | Inherited checkout BC or explicit enabled attribute |

Never copy a single row in isolation. Lifecycle, validation, JavaScript hooks, loading behavior,
and public-property handling must come from the same selected track.

## When to Use This Skill

| Scenario | Use This Skill? |
|----------|-----------------|
| Building a reactive component inside Hyvä Checkout | Yes |
| Adding reactive state to a payment or shipping view | Yes |
| Coordinating checkout components with events | Yes |
| Handling checkout component lifecycle and state | Yes |
| Implementing order placement logic | No (use the core architecture and a Place Order Service) |
| Changing checkout steps or block placement | No (use `hyva-checkout-configuration`) |
| Building forms or frontend validators | No (use `hyva-checkout-apis`) |
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

The primary Next/Place Order button owns checkout progression. A Magewire component exposes state,
persists user choices through services, and returns Evaluation results when it must gate that
progression. It must not create an order, add a competing Place Order button, or advance a step
directly.

## V1 Track: Checkout 1.0-1.3.* Only

Use this section only when the resolved checkout version is below 1.4. Load
[`magewire`](../magewire/SKILL.md) for the complete V1 API. Do not add V3 attributes or event syntax
to this track.

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

## V3 Track: Checkout 1.4+ New Components

For Hyvä Checkout 1.4+, use V3 syntax for new components and opt out of the V1 compatibility
layer explicitly. Keep compatibility enabled only while a component still uses V1 behavior.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Checkout;

use Magewirephp\Magewire\Attributes\On;
use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;

#[HandleBackwardsCompatibility(enabled: false)]
class DeliveryChoice extends Component
{
    public string $selectedOption = '';

    #[On('shipping-address-saved')]
    public function refreshOptions(): void
    {
        // Reload options from an injected service.
    }

    public function selectOption(string $code): void
    {
        // Validate and persist the choice through an injected service first.
        $this->selectedOption = $code;
        $this->dispatch('delivery-option-selected', code: $code);
    }
}
```

V3-native rules:

- Use `$this->dispatch()` and `#[On]`; events bubble without `->up()`.
- Do not mix `$listeners`/`emit*()` into a component that disables backwards compatibility.
- Treat public properties and action parameters as untrusted request input.
- Use `mount()` only for a reliable first render. Checkout step changes can introduce a component
  during an update request, so restore required active-request state in `boot()` or `booted()`.
- Follow neighboring checkout components when they inspect
  `store($this)->get('magewire:update', false)` to distinguish update requests.
- Use browser events for browser concerns, not component-to-component coordination.

## Migration Track: Existing V1 Components on Checkout 1.4+

Use this track only when preserving or migrating a component originally written for Magewire V1.
Do not use it to justify V1 syntax in a new component.

### How Checkout Backwards Compatibility Works

The Magewire 3 Hyvä Checkout compatibility package enables V1 behavior for the component tree
under `hyva-checkout-main`. Nested and dynamically inserted components inherit that decision, so
existing V1 checkout extensions can run without adding an attribute to every class.

The resolved server flag is stored as `magewire:bc` and transported in the component snapshot as
`memo.bc.enabled`. PHP adapts supported V1 hydration, lifecycle, state, and helper expectations;
the checkout compatibility JavaScript adapts directives, entanglement, hook names, and component
aliases. It does not rewrite source or repair custom application behavior.

An explicit `#[HandleBackwardsCompatibility(enabled: false)]` wins over the inherited checkout
default. Migrate and opt out one component at a time, then browser-test it. Load
`magento://skills/magewire-three/backwards-compatibility` for exact flag precedence, the V1-to-V3
mapping, limitations, and the site-wide Feature switch.

## Checkout-Specific Patterns

### Payment Method Component

A payment component manages the method's reactive state and may implement `EvaluationInterface`.
It never owns quote-to-order conversion and should not expose `placeOrder()` as a component action.

- Persist validated payment preferences to the quote through an injected service.
- Gate missing or invalid state with an Evaluation result.
- Use `AbstractPlaceOrderService` for server-side method-specific order placement.
- Use `hyvaCheckout.payment.registerMethod()` for browser SDK, wallet, iframe, or hosted flows.
- Render durable provider overlays outside selected payment and step-scoped markup.
- Trigger provider interaction from the primary Place Order flow, not method selection.

See [hyva-checkout](../hyva-checkout/SKILL.md) for the complete payment decision table and fallback
flow.

### Shipping Method Component (V1 Example)

This illustrates V1 event syntax. Verify the installed checkout event name and payload, validate
the selected option against current Magento rates, and persist it through an injected service.

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
        // Load available options for the current address through an injected service.
    }

    /**
     * @param string $optionCode
     * @return void
     */
    public function selectOption(string $optionCode): void
    {
        // Validate and persist the option before updating public state.
        $this->selectedOption = $optionCode;
        $this->emit('shipping_method_selected', ['method' => $optionCode]);
    }
}
```

### Participate in Progression

Do not dispatch a browser event to mark a step complete or call the navigation API from the
component. Implement `EvaluationInterface` when state must gate Next or Place Order. Return a
descriptive failure result and let the primary action decide navigation.

Persist custom form values when they change or through the checkout Form API. Do not depend on an
invented `checkout:order:place:before` component event: order placement may occur while this
component is not mounted. Transfer quote data to the order through Magento extension attributes,
observers, plugins, or the selected Place Order Service as appropriate.

## Review Checklist

- The installed Hyvä Checkout version selects V1 or V3 component syntax.
- V3-native components explicitly disable backwards compatibility.
- Required active-request state is not initialized only in `mount()`.
- Public actions validate ownership, allowed values, and current quote state.
- Quote persistence lives in an injected service, not direct component model mutations.
- Cross-component communication uses version-correct events rather than `Magewire.find()`.
- Evaluation participates in primary navigation without silently or directly advancing it.
- Payment components never convert the quote or expose their own Place Order action.
- The component works when its step is not the final or currently visible step.
- Loading, errors, repeated requests, and stale public state have explicit behavior.
