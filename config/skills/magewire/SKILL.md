# Magewire: Reactive PHP Components for Magento 2

> Related: See [hyva-checkout-magewire](../hyva-checkout-magewire/SKILL.md) for Hyvä Checkout-specific Magewire patterns, and [hyva-theme-setup](../hyva-theme-setup/SKILL.md) for Hyvä theme Alpine.js components.

## Overview

Magewire (`Magewirephp_Magewire`) is a Magento 2 adaptation of Laravel Livewire that enables **server-driven reactive components** using PHP. Components are regular PHP classes with public properties that sync automatically with the frontend via AJAX round-trips. The browser UI updates without writing custom JavaScript — `wire:` directives in `.phtml` templates handle data binding, method calls, and DOM updates.

Magewire is a **standalone package** — it does not require Hyvä Checkout or the Hyvä theme. It can be used anywhere in Magento: admin panels, CMS pages, customer account sections, frontend widgets, or any layout where you need reactive behavior without a full JavaScript framework.

## When to Use This Skill

| Scenario | Use This Skill? |
|----------|-----------------|
| Building reactive forms (contact, newsletter, custom) | Yes |
| Creating interactive admin panel components | Yes |
| Building CMS page widgets with server-driven state | Yes |
| Adding reactive sections to customer account pages | Yes |
| Building any non-checkout Magewire component | Yes |
| Customizing Hyvä Checkout steps or payment methods | No (use `hyva-checkout`) |
| Standard Magento frontend with KnockoutJS | No (use `frontend`) |
| Hyvä theme Alpine.js components (no Magewire) | No (use `hyva-theme`) |

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│  Layout XML         (component declaration + arguments)    │
├──────────────────────────────────────────────────────────┤
│  Magewire PHP       (server-side reactive component)       │
├──────────────────────────────────────────────────────────┤
│  .phtml template    (wire: directives, ViewModels)         │
├──────────────────────────────────────────────────────────┤
│  Alpine.js + Livewire.js  (client-side reactivity)         │
└──────────────────────────────────────────────────────────┘
```

**Key package:** `Magewirephp_Magewire` (composer: `magewirephp/magewire`)

**Request flow:** User interaction triggers `wire:` directive -> Livewire.js sends AJAX request -> Magewire PHP processes the update -> server returns new HTML + updated state -> DOM patches automatically.

## Component Fundamentals

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
     * @var bool
     */
    public bool $active = false;

    /**
     * Public methods are callable from templates via wire:click etc.
     *
     * @return void
     */
    public function increment(): void
    {
        $this->count++;
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        $this->count = 0;
        $this->name = '';
    }
}
```

**Key rules:**
- Public properties = reactive state (synced to frontend automatically)
- Public methods = callable actions (invoked via `wire:click`, `wire:submit`, etc.)
- Protected/private properties and methods are server-only (not exposed to the frontend)
- Property types must be serializable (string, int, float, bool, array) — no objects

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

<!-- With initial property values (array syntax) -->
<block name="my.component"
       template="Vendor_Module::magewire/my-component.phtml">
    <arguments>
        <argument name="magewire" xsi:type="array">
            <item name="type" xsi:type="object">\Vendor\Module\Magewire\MyComponent</item>
            <item name="name" xsi:type="string">Default Name</item>
            <item name="count" xsi:type="number">5</item>
        </argument>
    </arguments>
</block>
```

**Important:** The `magewire` argument name is required — this is how the framework identifies Magewire-enabled blocks.

## Template with `wire:` Directives

```php
<?php

declare(strict_types=1);

use Vendor\Module\Magewire\MyComponent;
use Magento\Framework\Escaper;

/** @var MyComponent $magewire */
/** @var Escaper $escaper */
?>

<div>
    <!-- Two-way data binding (updates on every keystroke) -->
    <input type="text" wire:model="name" />

    <!-- Lazy binding (updates on blur/change — use for expensive operations) -->
    <input type="text" wire:model.lazy="name" />

    <!-- Display reactive property -->
    <p>Hello, <?= $escaper->escapeHtml($magewire->name) ?></p>

    <!-- Call method on click -->
    <button wire:click="increment">
        Count: <?= $escaper->escapeHtml((string) $magewire->count) ?>
    </button>

    <!-- Magic actions -->
    <button wire:click="$set('count', 0)">Reset Count</button>
    <button wire:click="$toggle('active')">Toggle Active</button>
    <button wire:click="$refresh()">Refresh Component</button>

    <!-- Prevent DOM re-rendering for third-party JS widgets -->
    <div wire:ignore>
        <!-- Third-party JS widget here — Magewire won't touch this DOM subtree -->
    </div>
</div>
```

### `wire:` Directive Reference

| Directive | Purpose | Example |
|-----------|---------|---------|
| `wire:model` | Two-way bind to property (every keystroke) | `<input wire:model="email" />` |
| `wire:model.lazy` | Two-way bind on blur/change | `<input wire:model.lazy="email" />` |
| `wire:click` | Call method on click | `<button wire:click="save">` |
| `wire:submit` | Call method on form submit | `<form wire:submit="submitForm">` |
| `wire:change` | Call method on change event | `<select wire:change="updateRegion">` |
| `wire:ignore` | Exclude subtree from DOM diffing | `<div wire:ignore>...</div>` |
| `wire:loading` | Show element during AJAX request | `<span wire:loading>Saving...</span>` |

### Magic Actions

| Action | Purpose | Example |
|--------|---------|---------|
| `$set('prop', value)` | Set a property value | `wire:click="$set('count', 0)"` |
| `$toggle('prop')` | Toggle a boolean property | `wire:click="$toggle('active')"` |
| `$refresh()` | Re-render the component | `wire:click="$refresh()"` |

## Component Lifecycle Hooks

Hooks execute in this order during a request:

```
Initial render:    boot() → mount() → booted()
Subsequent AJAX:   boot() → hydrate() → [updating → action → updated] → dehydrate() → booted()
```

```php
class MyComponent extends Component
{
    /**
     * Called on EVERY request (initial + subsequent).
     * Use for setup that must run every time (e.g., injecting services).
     *
     * @return void
     */
    public function boot(): void {}

    /**
     * Called only on INITIAL render (not on subsequent AJAX requests).
     * Use for one-time initialization (e.g., loading data from DB).
     *
     * @return void
     */
    public function mount(): void {}

    /**
     * Called after boot()+mount() on initial, after boot()+hydrate() on subsequent.
     *
     * @return void
     */
    public function booted(): void {}

    /**
     * Called on subsequent requests when component state is restored from the client.
     * Not called on initial render.
     *
     * @return void
     */
    public function hydrate(): void {}

    /**
     * Called BEFORE any property is updated. Receives the new value and property name.
     *
     * @param mixed $value
     * @param string $name
     * @return void
     */
    public function updating(mixed $value, string $name): void {}

    /**
     * Called AFTER any property is updated.
     *
     * @param mixed $value
     * @param string $name
     * @return void
     */
    public function updated(mixed $value, string $name): void {}

    /**
     * Called BEFORE a specific property is updated (e.g., updatingEmail for $email).
     *
     * @param mixed $value
     * @return void
     */
    public function updatingEmail(mixed $value): void {}

    /**
     * Called AFTER a specific property is updated.
     * Return a value to override the property value.
     *
     * @param mixed $value
     * @return string
     */
    public function updatedEmail(mixed $value): string
    {
        return strtolower($value);
    }

    /**
     * Called BEFORE the component is serialized and sent back to the client.
     *
     * @return void
     */
    public function dehydrate(): void {}
}
```

### Nested Property Update Hooks

For nested properties (e.g., `$data['email']['address']`), use camelCase concatenation:

```php
/**
 * Triggered when $data['email']['address'] is updated.
 *
 * @param mixed $value
 * @return void
 */
public function updatedDataEmailAddress(mixed $value): void
{
    // Validate nested property
}
```

## Events Between Components

Components communicate through events. A component declares which events it listens to and can emit events for other components to handle.

### Declaring Listeners

```php
class ShippingSelector extends Component
{
    /**
     * Map event names to handler methods.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'address_saved' => 'onAddressSaved',
        'coupon_applied' => 'refresh',  // Built-in: re-renders the component
    ];

    /**
     * @param array $data
     * @return void
     */
    public function onAddressSaved(array $data): void
    {
        // React to another component's event
    }
}
```

### Emitting Events

```php
/**
 * @param string $methodCode
 * @return void
 */
public function selectMethod(string $methodCode): void
{
    // Save selection...

    // Emit to ALL listening components
    $this->emit('shipping_method_selected', ['method' => $methodCode]);

    // Emit to a SPECIFIC component (by Layout XML block name)
    $this->emitTo('checkout.payment.methods', 'refresh');

    // Emit only to THIS component (self-listeners)
    $this->emitSelf('method_updated');
}
```

## Flash Messages

Display transient messages to the user:

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

## Browser Events from PHP

Dispatch custom JavaScript events from the server to trigger client-side behavior:

```php
/**
 * @return void
 */
public function onComplete(): void
{
    $this->dispatchBrowserEvent('my-custom-event', [
        'status' => 'complete',
        'id' => 42,
    ]);
}
```

Listen in Alpine.js:

```html
<div x-data @my-custom-event.window="alert($event.detail.status)">
    <!-- Reacts to the PHP-dispatched event -->
</div>
```

## Loader Configuration

Show loading indicators for specific properties and methods:

```php
class SearchForm extends Component
{
    /**
     * Map property/method names to loading text.
     *
     * @var array<string, string>
     */
    protected array $loader = [
        'query' => 'Searching...',          // While $query property updates
        'submitSearch' => 'Loading results', // While submitSearch() runs
    ];
}
```

## Alpine.js Integration

### Accessing Component via `$wire`

Inside a Magewire template, Alpine.js has access to the `$wire` proxy:

```html
<div x-data>
    <!-- Read reactive property -->
    <h1 x-text="$wire.name"></h1>

    <!-- Set property from Alpine -->
    <button x-on:click="$wire.set('name', 'New Value')">Update Name</button>

    <!-- Call PHP method from Alpine -->
    <button x-on:click="$wire.increment()">Increment</button>

    <!-- Conditional rendering based on Magewire state -->
    <div x-show="$wire.active">Active content</div>
</div>
```

### Entangle: Two-Way Sync Between Alpine and Magewire

`entangle()` creates a two-way binding between an Alpine.js local variable and a Magewire server property:

```html
<div x-data="{ localValue: $wire.entangle('serverValue') }">
    <!-- Changes to localValue sync to PHP $serverValue and vice versa -->
    <input type="text" x-model="localValue" />
    <span x-text="localValue"></span>
</div>
```

### Emitting Events from JavaScript

```javascript
// Emit to all listening components
Magewire.emit('address_saved', { addressId: 123 });

// Emit to a specific component (by block name)
Magewire.emitTo('my.component.block', 'refresh');
```

## Best Practices

- **Keep components focused** — one responsibility per component, coordinate via events
- **Use `wire:model.lazy`** for text inputs to avoid a server round-trip on every keystroke
- **Avoid heavy `mount()` logic** — mount runs only on initial render; use `boot()` for logic needed on every request
- **Use `wire:ignore`** for third-party JS widgets that manage their own DOM
- **Don't store objects in public properties** — only serializable types (string, int, float, bool, array)
- **Use events for inter-component communication** — don't share state directly between components
- **Inject dependencies via constructor** — Magewire components support standard Magento DI
- **Use `$loader`** to show meaningful loading states during expensive operations
