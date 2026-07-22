---
name: magewire-three-architecture
description: Extend and debug Magewire 3 internals in Magento 2. Use for runtime boot modes, Mechanisms, Features, Component Hooks, resolvers, structured layout arguments, snapshots, synthesizers, request flow, observer bridges, and deciding where framework-level behavior belongs.
---

# Magewire 3 Architecture and Extension Points

Use the core [`magewire-three`](../SKILL.md) skill for ordinary components. Load this skill only when
the task changes how Magewire resolves, transports, extends, or renders components.

## Start with the Ownership Boundary

In `vendor/magewirephp/magewire/`:

| Path | Ownership | Edit? |
|---|---|---|
| `src/` | Magento module wiring: controllers, DI, layout, templates, assets | Yes, in Magewire core |
| `lib/Magewire/` | Hand-written Magento/Magewire runtime | Yes, in Magewire core |
| `lib/MagewireBc/` | Hand-written V1 compatibility code | Yes, in Magewire core |
| `portman/lib/Livewire/` | Downloaded upstream Livewire input | No |
| `portman/Livewire/` | Magewire augmentations merged into Livewire | Yes |
| `dist/` | Portman-generated PHP output | Never edit directly |

Since Magewire 3.2, theme integrations are separate packages such as
`magewirephp/magewire-hyva-theme`, `magewirephp/magewire-hyva-checkout`, and
`magewirephp/magewire-admin`. Do not target the removed in-core `themes/` layout.

For an application module, place components and services under that module. Do not patch files in
`vendor/`; use Magento DI, layout XML, a compatibility module, or an upstream contribution.

## Choose the Smallest Extension Type

| Need | Use |
|---|---|
| Interactive state and actions | Component plus application services |
| Observe a documented lifecycle event | Component Hook or Magento observer |
| Optional lifecycle behavior | Feature |
| Required request infrastructure | Mechanism |
| Build components from a new source | Component Resolver |
| Serialize a new public-property type | Synthesizer |
| Change ported Livewire PHP | Portman augmentation |
| Adapt assets/layout for a rendering stack | Theme compatibility package |

Prefer a Feature over a Mechanism. Mechanisms are mandatory runtime infrastructure and have a much
larger failure radius.

## Runtime and Request Modes

`MagewireServiceProvider` boots Containers, Mechanisms, and Features in sorted order. Its runtime
tracks:

- `PRECEDING`: initial Magento block rendering.
- `SUBSEQUENT`: a `/magewire/update` request.
- `UNDEFINED`: pre-boot state only.

Runtime state progresses from `UNINITIALIZED` through `SETUP`, `BOOTING`, and `BOOTED`, or to a
failure/stopped state. Booting is idempotent within one request. A frontend block observer triggers
preceding mode; the Magewire update route triggers subsequent mode.

Never inject `Runtime` directly for live state. Inject `MagewireServiceProvider` and call
`$serviceProvider->runtime()`; the provider owns the request's booted runtime instance.

Service boot modes are:

| Value | Mode | Meaning |
|---|---|---|
| `10` | Lazy | Boot only when explicitly resolved |
| `20` | Persistent | Boot during setup and remain available |
| `30` | Always | Boot during the normal request boot |

Inspect the installed `src/etc/{area}/di.xml` before depending on names, sort orders, or enabled
Features. Those registrations are the runtime truth.

## Mechanisms and Features

Core Mechanisms cover component resolution, persistent middleware, component/snapshot handling,
request handling, data storage, and frontend assets. Features layer optional behavior such as
attributes, events, redirects, layouts, notifications, rate limiting, lifecycle hooks, nesting,
exception handling, and backwards compatibility.

Register both in area-scoped DI only:

- `etc/frontend/di.xml` for storefront behavior.
- `etc/adminhtml/di.xml` for admin behavior.
- Never global `etc/di.xml`; it prevents areas from owning different pipelines.

### Add a Feature

A Feature extends `ComponentHook`. Implement direct lifecycle methods when that is sufficient, or a
`provide()` method to subscribe to lower-level events with `on()`.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Feature;

use Magewirephp\Magewire\ComponentHook;
use Magewirephp\Magewire\Mechanisms\HandleComponents\ComponentContext;

class SupportAuditEffect extends ComponentHook
{
    public function dehydrate(ComponentContext $context): void
    {
        $context->pushEffect('audit', ['component' => $this->component()->getName()]);
    }
}
```

```xml
<type name="Magewirephp\Magewire\Features">
    <arguments>
        <argument name="items" xsi:type="array">
            <item name="audit_effect" xsi:type="array">
                <item name="type" xsi:type="string">Vendor\Module\Magewire\Feature\SupportAuditEffect</item>
                <item name="sort_order" xsi:type="number">5050</item>
            </item>
        </argument>
    </arguments>
</type>
```

Choose sort order after reading neighboring installed registrations. A duplicate number is not a
stable dependency declaration.

`update`, `call`, and `render` hooks support before/after middleware by returning a callback. Do not
assume that `mount`, `hydrate`, `dehydrate`, or `exception` execute a returned callback.

Use the hook's component store (`storeSet()`, `storeGet()`, or `store($component)`) for
component-scoped Feature state. Push browser effects during dehydrate; never echo output from a
Feature.

## Magento Observer Bridge

Magewire lifecycle events are also exposed as Magento observer events when the observer Feature is
active. Names use `magewire_on_` plus the normalized hook name. Use an observer when the behavior is
a simple Magento integration and does not need a full Component Hook.

```xml
<event name="magewire_on_render">
    <observer name="vendor_module_magewire_render"
              instance="Vendor\Module\Observer\MagewireRender"/>
</event>
```

Confirm the exact event and payload in the installed source before binding production behavior.

## Component Resolution

The resolver manager selects a resolver in this order:

1. Explicit `magewire:resolver` block argument.
2. Resolver inherited from a live parent component.
3. Remembered block-to-resolver accessor.
4. Registered resolvers in `sortOrder`; the first matching `complies()` wins.

The chosen accessor is stored in snapshot memo and reused for subsequent requests. A resolver's DI
item name must match its accessor. Custom resolvers should sort before the fallback `LayoutResolver`.

A resolver owns:

| Method | Responsibility |
|---|---|
| `complies()` | Cheap initial match check |
| `construct()` | Bind component to block on initial render |
| `reconstruct()` | Rebuild block and component from request context |
| `arguments()` | Return the resolver's `MagewireArguments` implementation |
| `assemble()` | Set component identity, name, id, and alias |
| `remember()` | Allow or prevent resolver caching |

Do not create a custom resolver merely to pass configuration. Use structured layout arguments:

| Argument | Meaning |
|---|---|
| `magewire` | Component object or resolver-supported definition |
| `magewire.product-id` | Assign public `$productId` |
| `magewire:mount:product-id` | Named `mount($productId)` argument |
| `magewire:{group}:{key}` | Resolver/Feature-specific group |
| `magewire:resolver` | Force a registered accessor |
| `magewire:alias` | Set lookup alias |

## Snapshot and Update Flow

```text
initial layout render
  -> resolver constructs component and block
  -> mount, render, dehydrate
  -> snapshot { data, memo, checksum } + effects

POST /magewire/update
  -> verify checksum
  -> reconstruct using memo.resolver and layout metadata
  -> boot/initialize, hydrate
  -> apply updates and calls
  -> render and dehydrate
  -> new snapshot, effects, and HTML
  -> browser morphs DOM
```

- `data` contains serialized public state.
- `memo` contains reconstruction metadata such as component identity, resolver, children, and flags.
- `checksum` protects snapshot integrity; it is not authorization.
- `effects` carry events, redirects, notifications, streams, and extension output.

Keep memo reconstruction data minimal and stable. Never trust snapshot data as proof that the
current customer may perform an action.

## Synthesizers

Use scalar or array public state first. Built-in synthesizers cover the installed framework's
supported scalar wrappers, arrays, `stdClass`, backed enums, and Magento `DataObject`. Inspect the
installed synthesizer list before relying on any object type.

A custom synthesizer must:

1. Match only its owned type.
2. Dehydrate to JSON-safe data and minimal metadata.
3. Hydrate an equivalent value.
4. Round-trip nested children through the callbacks it receives.
5. Register on `HandleComponents` in each required area, with deliberate ordering.

Never serialize Magento models, repositories, sessions, service objects, or resources into public
state. Persist an identifier and re-fetch through an authorized application service.

## Debugging Order

1. Identify request mode and active area.
2. Confirm the installed compatibility package and area DI are loaded.
3. Inspect which resolver/accessor was stored in snapshot memo.
4. Verify component name, id, alias, layout handles, and root element.
5. Check checksum verification before investigating hydration.
6. Trace Feature/Mechanism sort order and boot mode.
7. Inspect response effects and browser `commit`/`morph.*` hooks.
8. If code is under `dist/`, locate its upstream input and Portman augmentation instead of editing it.

## Source Authority

Architecture changes quickly. Prefer, in order:

1. Installed Magewire PHP, DI XML, layout XML, and JavaScript.
2. The installed compatibility package for the active theme/area.
3. Current official Magewire documentation.
4. Livewire documentation only for behavior Magewire actually ports and enables.
