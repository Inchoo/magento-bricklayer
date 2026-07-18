---
name: magewire-three-best-practices
description: Review and harden Magewire 3 application code in Magento 2. Use for component boundaries, public state, lifecycle, layout XML, templates, security, authorization, events, dependency injection, serialization, performance, testing, naming, and code-review checklists.
---

# Magewire 3 Best Practices

Use this skill to design or review application-level Magewire code. For framework internals, load
`magento://skills/magewire-three/architecture`; for browser extensions, load
`magento://skills/magewire-three/javascript`.

## Consistency First

Before creating a component, inspect neighboring components from the same module and installed
version. Follow established service boundaries, layout handles, naming, validation, and theme
patterns unless they are demonstrably unsafe or V1-only.

Do not copy Livewire examples without verifying that the installed Magewire Feature is enabled.

## Component Design

- Extend `Magewirephp\Magewire\Component`, not a Magento block.
- Put components under `Vendor\Module\Magewire\` and keep domain work in injected services.
- Model one interactive responsibility per component.
- Treat every public method as a remotely callable HTTP action.
- Use constructor injection for dependencies, but run initial state loading in `mount()`.
- Use private methods and services for implementation details.
- Call `skipRender()` only when no visible state needs morphing.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Account;

use Magewirephp\Magewire\Component;
use Vendor\Module\Api\PreferenceServiceInterface;

class Preferences extends Component
{
    public bool $marketing = false;

    public function __construct(
        private readonly PreferenceServiceInterface $preferenceService
    ) {
    }

    public function mount(): void
    {
        $this->marketing = $this->preferenceService->isMarketingEnabled();
    }

    public function save(): void
    {
        $this->preferenceService->saveMarketing($this->marketing);
        $this->magewireNotifications()->make(__('Preferences saved.'))->asSuccess();
    }
}
```

The service must enforce the current customer's authorization; a component property is not a trust
boundary.

## Public State

Public properties cross the browser boundary on every request. They should be:

- Typed and initialized with a sensible default.
- Limited to state the UI must round-trip.
- Scalars, arrays, or explicitly supported synthesized types.
- Free of secrets, credentials, form keys, session identifiers, and service objects.
- Treated as untrusted input after hydration.

Prefer identifiers and display data over Magento models:

```php
public int $productId = 0;
public string $productName = '';
public float $productPrice = 0.0;
```

Re-fetch authoritative entities inside an authorized action. Never trust a public price, owner id,
customer id, store id, or permission flag.

Use `fill()` for deliberate bulk assignment and `reset()`, `resetExcept()`, or `pull()` only after
confirming those APIs exist in the installed Magewire version. Dot notation is suitable for nested
array state; use stable shapes and initialized keys.

## Input Synchronization

| Directive | Default use |
|---|---|
| `wire:model` | Most fields; sync on next request |
| `wire:model.blur` | Validation or work after leaving a field |
| `wire:model.live.debounce.250ms` | Search or live feedback with measured need |
| `wire:model.live` | Low-frequency state that truly needs immediate round-trips |

Avoid un-debounced live text input. Every live change sends state, runs lifecycle hooks, and may
render HTML.

## Lifecycle Responsibilities

| Hook | Appropriate work |
|---|---|
| `boot()` | Cheap authorization and request setup every request |
| `mount(...)` | Initial defaults and layout-provided input |
| `hydrate()` | Guarded subsequent-request restoration |
| `updating*()` | Reject or normalize a property update |
| `updated*()` | Small side effects after a property update |
| `rendering()` | Prepare block/template selection |
| `rendered()` | Observe output, not domain mutation |
| `dehydrate()` | Final snapshot/effect preparation |
| `exception()` | Convert expected failures into controlled UI behavior |

Keep `boot`, `hydrate`, and `dehydrate` cheap. Do not perform an unconditional repository query or
remote API call on every keystroke. Prefer property-specific hooks over a generic hook containing a
large conditional.

Never call a component `render()` method manually. Magewire V3 renders the block's configured PHTML.

## Layout XML

- Register a component only on handles where it is used.
- Bind it through the `magewire` block argument.
- Use `shared="false"` when the same component class appears on multiple blocks.
- Pass component state with `magewire.*` and mount parameters with `magewire:mount:*`.
- Give every block a stable unique Magento name.
- Use `magewire:alias` only when lookup/targeting requires a public alias.
- Do not use a component class as the Magento block class.

```xml
<block name="account.preferences"
       template="Vendor_Module::magewire/account/preferences.phtml">
    <arguments>
        <argument name="magewire" xsi:type="object" shared="false">
            Vendor\Module\Magewire\Account\Preferences
        </argument>
    </arguments>
</block>
```

## Templates

- Render exactly one root element.
- Keep computation and service access out of PHTML.
- Escape text, attributes, URLs, and JavaScript for their exact context.
- Use Magento's `Escaper`, not raw `htmlspecialchars()` as a substitute.
- Use stable domain identifiers in `wire:key`; never array offsets for reorderable data.
- Add loading, disabled, dirty, offline, and error states expected by the workflow.
- Use `wire:ignore` only around DOM genuinely owned by another library.
- Wrap inline JavaScript in Magewire fragments.

Do not output raw translation phrases or user data with `@noEscape` merely to silence static
analysis.

## Security

Snapshot checksums detect transport tampering. They do not authorize methods or establish ownership.

For every public action:

1. Validate scalar shape, range, enum membership, and required values.
2. Re-fetch records by identifier through a service/repository.
3. Verify customer/session ownership, website/store scope, or ACL.
4. Apply business validation in the domain service.
5. Persist through a service contract.
6. Return only the state/effects needed by the UI.

Keep FormKey/CSRF handling and checksum verification enabled. Do not expose generic method dispatch,
arbitrary class names, raw SQL, file paths, or unrestricted URLs through public properties/actions.

Rate limiting reduces abuse volume; it is not a replacement for authorization or validation.

## Events and Communication

- Prefer direct state/actions within one component.
- Use kebab-case event names for decoupled sibling or cross-tree communication.
- Listen with `#[On]` in native V3 code.
- Use named payload arguments with stable keys.
- Use `dispatch()->self()` for the source component and `dispatch()->to()` for a known target.
- Use plain `dispatch()` for bubbling; the current Event API has no `up()` method.
- Avoid event chains where a service call or explicit parent-owned state is clearer.

Events are browser-visible. Do not put secrets or trusted authorization claims in their payloads.

## DI and Extensions

- Never use `ObjectManager::getInstance()` in component code.
- Register Features, Mechanisms, resolvers, synthesizers, and their plugins in area DI.
- Inspect installed sort orders before choosing one.
- Use a Feature for optional lifecycle behavior and a Mechanism only for required infrastructure.
- Keep component-scoped Feature state in the Magewire store.
- Keep Feature-owned JavaScript/templates with the Feature.
- Do not edit `dist/`; use a Portman augmentation for ported code.

## Serialization

Prefer scalar state over custom synthesizers. A synthesizer is justified for a stable value object
that must cross the boundary and cannot reasonably be represented as an array.

A custom synthesizer must round-trip exactly, return JSON-safe data, include only reconstruction
metadata, and reject values it does not own. Never synthesize a full Magento model.

## Performance

- Minimize the number and size of public properties.
- Paginate or summarize large datasets instead of serializing them.
- Debounce live inputs and avoid overlapping requests.
- Use `wire:target` so loading UI describes the active action.
- Use `skipRender()` for effect-only actions.
- Keep nesting shallow where independent siblings and events suffice.
- Avoid broad polling; choose an explicit interval and `.visible` when polling is necessary.
- Cache expensive read models in the appropriate Magento layer, not in hidden component globals.
- Remove BC per component after migration.

Measure `/magewire/update` payload size, server duration, query count, and morph behavior before and
after optimizing.

## Testing

Unit-test domain services separately. Magewire behavior needs browser or integration coverage for:

- Initial render and one-root output.
- Snapshot/update round-trips.
- Authorization and invalid/tampered input.
- Property synchronization variants.
- Loading, disabled, error, offline, and double-submit behavior.
- Notifications, flash messages, redirects, events, and streams.
- Keyed list insertion/removal/reordering.
- Alpine CSP and third-party widgets across morphs.
- BC-on and BC-off behavior during migrations.

Inspect the network response and console, not only the final DOM.

## Review Checklist

- Component responsibility is narrow and domain work lives in services.
- Public properties are typed, initialized, serializable, minimal, and non-sensitive.
- Every public action validates and authorizes server-side.
- Lifecycle work matches the hook and avoids repeated heavy I/O.
- Layout binding, area, `shared`, alias, and mount arguments are correct.
- PHTML has one root, correct escaping, stable keys, and complete feedback states.
- Events use current V3 APIs and named payloads.
- JavaScript is CSP-managed, correctly registered, and cleaned up.
- DI is area-scoped and generated/ported code is not edited directly.
- Tests cover the browser round-trip and failure paths.
