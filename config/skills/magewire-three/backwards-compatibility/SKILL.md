---
name: magewire-three-backwards-compatibility
description: Migrate Magewire V1 components onto Magewire 3 using the per-component backwards-compatibility layer. Use for activation precedence, memo.bc.enabled, PHP and JavaScript shims, Hyva Checkout defaults, V1-to-V3 API mapping, phased opt-out, performance, troubleshooting, and disabling the feature site-wide.
---

# Magewire 3 Backwards Compatibility

Magewire V3 is a rewrite based on Livewire V3. Its backwards-compatibility (BC) layer lets Magewire
V1 components run on the V3 runtime while they are migrated one at a time. It is a migration tool,
not the target architecture.

Use [`magewire-three`](../SKILL.md) for native V3 development. Magewire V1 must not be treated as an
unsupported synonym: Hyvä Checkout 1.0-1.3 uses Magewire V1, while Hyvä Checkout 1.4+ uses Magewire
V3.

## Two Independent Switches

| Scope | Controls | Default |
|---|---|---|
| Per component | Whether one component receives V1 behavior | Off |
| Feature registration | Whether the BC subsystem exists in the area | Registered on frontend |

Most migrations use the per-component switch. Disable the whole Feature only after every installed
module and theme component is V3-native.

## Enable or Explicitly Disable a Component

Use the exact namespace:

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire;

use Magewirephp\Magewire\Component;
use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;

#[HandleBackwardsCompatibility]
class Cart extends Component
{
}
```

`Magewirephp\Magewire\Attributes\HandleBackwardsCompatibility` does not exist. A wrong import leaves
the component on native V3 semantics.

Opt a migrated component out even when a theme would enable BC:

```php
#[HandleBackwardsCompatibility(enabled: false)]
class Cart extends Component
{
}
```

## Resolution and Transport

The effective component flag resolves in this order:

1. Explicit attribute, whether `true` or `false`.
2. Programmatic component-store value.
3. Theme compatibility default.
4. No match means native V3 (`false`).

A Feature or Component Hook can set the value before resolution completes:

```php
use function Magewirephp\Magewire\store;

store($component)->set('magewire:bc', true);
```

The `LayoutResolver` stores the result under `magewire:bc`. Snapshot memo transports it as
`memo.bc.enabled`, giving the server, subsequent requests, and theme JavaScript the same decision.

## What Happens on PHP

The base `Component` always includes `HandlesComponentBackwardsCompatibility`, which retains V1
concerns such as:

- `emit()`, `emitUp()`, `emitSelf()`, and `emitTo()` wrappers.
- public `$id` and `getPublicProperties()`.
- V1 error, browser-event, request, and view helpers.
- deprecated message helpers.

The per-component flag controls the additional runtime adaptations:

- The BC Feature reconstructs V1-style server-memo data during hydration.
- Dehydration adds a BC property-path map for moved frontend properties.
- A lifecycle plugin adapts supported V1 hook argument conventions.
- Component memo exposes the browser activation flag.

The existence of a deprecated method does not mean new code should call it. Native V3 components
should use the V3 API even though the base class still carries wrappers.

## What Happens in JavaScript

The theme compatibility shim checks `memo.bc.enabled` for each component subtree. When true, it:

- Rewrites `wire:model` to `wire:model.live`.
- Rewrites `wire:model.defer` to `wire:model`.
- Rewrites `wire:model.lazy` to `wire:model.blur`.
- Rewrites `wire:model.delay.Xms` to `wire:model.live.debounce.Xms`.
- Restores live-by-default `$wire.entangle()`.
- Re-fires V1 hook names alongside V3 hooks.
- Aliases `component.data` to `component.$wire`.
- Aliases `component.deferredActions` to `component.queuedUpdates`.

The shim is supplied by the active compatibility package, not by generic application JavaScript.
When the flag is false, that component does not receive the shim behavior.

## Hyvä Checkout Behavior

The Magewire 3 Hyvä Checkout compatibility package enables BC for the component tree under
`hyva-checkout-main`. Nested and dynamically inserted children inherit the parent decision. This
supports V1-era checkout components and extensions on the V3 runtime without adding an attribute to
every class.

An explicit `#[HandleBackwardsCompatibility(enabled: false)]` wins over the checkout container rule.
Use that opt-out while converting checkout components incrementally.

## Migration Matrix

| V1 | Native V3 | BC behavior |
|---|---|---|
| live `wire:model` | `wire:model.live` | Rewritten |
| `wire:model.defer` | `wire:model` | Rewritten |
| `wire:model.lazy` | `wire:model.blur` | Rewritten |
| `wire:model.delay.500ms` | `wire:model.live.debounce.500ms` | Rewritten |
| live `$wire.entangle('x')` | `$wire.entangle('x').live` | Live proxy restored |
| `$listeners` | `#[On('event')]` | Still read; migrate manually |
| `emit()` | `dispatch()` | Wrapper retained |
| `emitUp()` | `dispatch()`; events bubble | Wrapper retained |
| `emitSelf()` | `dispatch()->self()` | Wrapper retained |
| `emitTo()` | `dispatch()->to()` | Wrapper retained |
| `dispatchBrowserEvent()` | `dispatch()` | Wrapper retained |
| JS `Magewire.emit*()` | `Magewire.dispatch()` / `dispatchTo()` | Manual |
| `getPublicProperties()` | `all()` | Wrapper retained |
| public `$id` | `id()` / `getId()` | Wrapper retained |
| `canRender()` | `skipRender()` | Manual |
| `switchTemplate()` / `render()` | `rendering()` + block template | Manual |
| V1 update hooks | V3 argument conventions | Supported cases adapted |
| `dispatch*Message()` | notification/flash builder | Wrapper retained |
| `$loader` mapping | `wire:loading` / loader Feature | Manual UI migration |
| V1 Hydrator | Synthesizer | Manual |
| `serverMemo` | snapshot data/memo/checksum | Runtime adaptation |
| global Magewire DI | area-scoped DI | Manual |
| old JS hooks | `component.init`, `commit`, `morph.*` | Common names re-fired |

The current Magewire `Event` object exposes `self()` and `to()` but not `up()`. V3 browser events
bubble by default, so V1 `emitUp()` becomes plain `dispatch()`.

## What BC Cannot Repair

Audit manually:

- Component and service method signatures not covered by a resolver.
- Public property types and initialization.
- Validation rule/API changes.
- Custom JavaScript using undocumented internals.
- Custom Hydrators and global DI extensions.
- Magento, Hyvä, checkout, or payment behavior changes.
- Authorization assumptions; BC does not secure an action.

Do not combine enabling BC with a behavioral redesign. First prove the V1 component works on V3,
then migrate it in a separate, reviewable step.

## Safe Migration Sequence

1. Upgrade platform requirements and install Magewire V3 plus the correct compatibility package.
2. Remove separately loaded Alpine.
3. Add the BC attribute to every V1 component not covered by a verified theme default.
4. Smoke-test the site without changing component behavior.
5. Migrate directives, state, events, lifecycle, JavaScript, messages, and DI one component at a time.
6. Set `enabled: false` on the migrated component and run its browser tests.
7. Remove the attribute after the component remains native V3.
8. Search every module and theme for remaining V1 APIs.
9. Disable the whole Feature only when the entire installation is native.

## Disable the Feature

Override the Feature item in each relevant area-scoped DI file:

```xml
<type name="Magewirephp\Magewire\Features">
    <arguments>
        <argument name="items" xsi:type="array">
            <item name="magewire_backwards_compatibility" xsi:type="boolean">false</item>
        </argument>
    </arguments>
</type>
```

This removes BC for every component regardless of attributes. Do not use it as a per-component
switch. Also remove or disable the theme package's BC bridge only after verifying it is no longer
needed.

## Performance and Troubleshooting

BC adds per-morph directive inspection, entangle proxy behavior, compatibility hook dispatches, and
server adaptation. The cost is small per component but accumulates across a large component tree.
Opt out as each component becomes native.

| Symptom | Check |
|---|---|
| Attribute has no effect | Exact namespace and installed source version |
| V1 input no longer updates live | `memo.bc.enabled`, compatibility JS, container inheritance |
| Entangle sends too many requests | BC restores live behavior; migrate and opt out |
| Old hooks do not run | Theme BC scripts loaded and component flag true |
| New component still behaves like V1 | Theme default; force `enabled: false` |
| Lifecycle receives wrong arguments | Flag, lifecycle plugin DI, unsupported custom signature |
| Feature disabled but V1 code remains | Restore Feature or finish migration before deploying |
