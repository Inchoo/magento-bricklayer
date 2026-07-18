---
name: magewire-three
description: Build, migrate, review, test, and troubleshoot Magewire 3 components in Magento 2. Use for greenfield Magewire 3 work, Magewire V1 to V3 upgrades, Livewire V3-style directives and events, component resolvers, snapshots, synthesizers, compiled templates, Alpine CSP integration, admin components, and Magewire 3 architecture extensions.
---

# Magewire 3 Development for Magento 2

> Related: Use [magewire](../magewire/SKILL.md) only for Magewire V1 code. Use
> [hyva-checkout-magewire](../hyva-checkout-magewire/SKILL.md) for checkout-specific patterns.
> Hyvä Checkout 1.0-1.3 uses Magewire V1; Hyvä Checkout 1.4 and newer uses Magewire V3.

## Load Focused Resources When Needed

Keep this core skill loaded for day-to-day component work. Load one focused resource when the task
crosses into that ownership boundary:

| Task | Resource |
|---|---|
| Features, Mechanisms, hooks, resolvers, snapshots, or synthesizers | `magento://skills/magewire-three/architecture` |
| Alpine CSP, hooks, directives, utilities, addons, or browser events | `magento://skills/magewire-three/javascript` |
| Theme adapters, layout insertion points, Tailwind, or adminhtml | `magento://skills/magewire-three/theming` |
| Livewire source ports, augmentations, transforms, or generated `dist/` | `magento://skills/magewire-three/portman` |
| Exact V1 compatibility behavior and staged migration | `magento://skills/magewire-three/backwards-compatibility` |
| Design, security, performance, testing, and review checklist | `magento://skills/magewire-three/best-practices` |

## Start by Identifying the Runtime

Do not mix Magewire generations. Inspect `composer.lock`, the root `composer.json`, and installed
modules before changing code.

| Installed constraint | Use |
|---|---|
| `magewirephp/magewire:^1` | The `magewire` skill and V1 APIs |
| `magewirephp/magewire:^3` | This skill and V3 APIs |
| Hyvä Checkout 1.0-1.3 | Magewire V1 plus the checkout-specific skill |
| Hyvä Checkout 1.4+ | Magewire V3 plus the checkout-specific skill |

Magewire V2 never existed. V1 followed Laravel Livewire V2 concepts; Magewire skipped V2 so its V3
major aligns with Livewire V3. V1 and V3 cannot run together in one Magento installation.

For V3, require Magento 2.4.4+, PHP 8.2+, and Composer 2. Treat the installed package source as the
final API authority because the V3 documentation is evolving:

- Documentation: <https://docs.magewirephp.nl>
- Upgrade guide: <https://docs.magewirephp.nl/pages/getting-started/upgrade.html>
- V3 versus V1: <https://docs.magewirephp.nl/pages/getting-started/v3-vs-v1.html>
- Source: <https://github.com/magewirephp/magewire>

## Choose a Workflow

### Build New V3 Functionality

1. Verify core and the theme compatibility package are installed.
2. Model the component as server state plus public actions; keep Magento domain work in services.
3. Add the component class, one-root PHTML template, and layout XML binding.
4. Add explicit authorization, escaping, loading/error states, and stable `wire:key` values.
5. Exercise initial render and `/magewire/update` requests in a browser.

### Migrate V1 Functionality

1. Upgrade the platform and install V3 without changing behavior.
2. Add `#[HandleBackwardsCompatibility]` to each V1 component.
3. Migrate one component at a time using the matrix below.
4. Set `enabled: false` on a migrated component to prove it is V3-native.
5. Remove the attribute, then disable the site-wide BC feature only after every component is native.

Never redesign component behavior in the same change that establishes backwards compatibility.

## Install the Correct Packages

Install core and the compatibility package for the active rendering area:

```bash
composer require magewirephp/magewire:^3.0

# Add only what the project uses.
composer require magewirephp/magewire-hyva-theme
composer require magewirephp/magewire-hyva-checkout
composer require magewirephp/magewire-admin

bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:flush
```

Skip compilation and static deployment in developer mode when appropriate. Since Magewire 3.2,
theme integrations are separate Composer packages; do not look for an in-core `themes/` directory.

Magewire bundles Alpine's CSP build. Remove separately loaded Alpine scripts from the theme. Two
Alpine instances conflict on directives, stores, and component state.

## Architecture Mental Model

V3 is a rewrite based on a port of Livewire V3 rather than an in-place V1 upgrade. Load
`magento://skills/magewire-three/architecture` before extending framework internals.

```text
Layout XML block
  -> Component Resolver
  -> Component + public state
  -> compiled PHTML template
  -> snapshot { data, memo, checksum }
  -> POST /magewire/update
  -> hydrate, call/update, render, dehydrate
  -> DOM morph + response effects
```

- **Mechanisms** are required runtime services such as component resolution, component handling,
  request handling, frontend assets, and template compilation.
- **Features** are area-scoped capabilities such as events, redirects, notifications, rate limits,
  nesting, exception handling, and backwards compatibility.
- **Preceding mode** is the initial page render. **Subsequent mode** is an update request.
- **Resolvers** reconstruct a component and its block. The built-in `LayoutResolver` covers normal
  layout XML; custom resolvers cover other sources.
- **Snapshots** carry public data and reconstruction metadata plus a checksum. A valid checksum
  prevents state tampering but does not authorize an action.
- **Synthesizers** serialize supported non-scalar public state across the snapshot boundary.

Register Mechanisms, Features, Component Hooks, resolvers, and synthesizers in area-scoped
`etc/frontend/di.xml` or `etc/adminhtml/di.xml`. Global `etc/di.xml` registrations are ignored by
the Magewire service provider.

## Build a Component

Use this module shape:

```text
Vendor/Module/
|-- Magewire/Product/Search.php
|-- view/frontend/layout/catalogsearch_result_index.xml
`-- view/frontend/templates/magewire/product/search.phtml
```

Keep services private so they never enter the snapshot. Treat public properties as untrusted client
state and public methods as HTTP endpoints.

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Magewire\Product;

use Magewirephp\Magewire\Component;
use Vendor\Module\Api\ProductSearchInterface;

class Search extends Component
{
    public string $query = '';
    public int $resultCount = 0;

    public function __construct(
        private readonly ProductSearchInterface $productSearch
    ) {
    }

    public function mount(string $initialQuery = ''): void
    {
        $this->query = $initialQuery;
    }

    public function search(): void
    {
        $query = trim($this->query);

        if ($query === '') {
            $this->resultCount = 0;
            return;
        }

        $this->resultCount = $this->productSearch->count($query);
    }
}
```

### Bind the Component in Layout XML

Use the `magewire` argument for the component. Use `magewire.*` for public properties and
`magewire:mount:*` for named `mount()` parameters.

```xml
<referenceContainer name="content">
    <block name="vendor.product.search"
           template="Vendor_Module::magewire/product/search.phtml">
        <arguments>
            <argument name="magewire" xsi:type="object" shared="false">
                Vendor\Module\Magewire\Product\Search
            </argument>
            <argument name="magewire:mount:initial-query" xsi:type="string">shoes</argument>
        </arguments>
    </block>
</referenceContainer>
```

Argument rules:

| Shape | Meaning |
|---|---|
| `magewire` object | Bind a component through the layout resolver |
| `magewire.product-id` | Assign public `$productId`; kebab-case becomes camelCase |
| `magewire:mount:product-id` | Pass named `$productId` to `mount()` |
| `magewire:{group}:{key}` | Provide a named argument group to a resolver or feature |
| `magewire:resolver` | Force a registered resolver |
| `magewire:alias` | Set a component lookup alias |

Use `shared="false"` whenever the same component class is bound to multiple blocks. Otherwise
Magento can reuse the same object and leak state between block instances.

### Write the Template

Render exactly one root element, escape every value, and make synchronization behavior explicit.

```php
<?php

declare(strict_types=1);

use Magento\Framework\Escaper;
use Vendor\Module\Magewire\Product\Search;

/** @var Search $magewire */
/** @var Escaper $escaper */
?>
<div>
    <form wire:submit="search">
        <label for="product-query"><?= $escaper->escapeHtml(__('Search products')) ?></label>
        <input id="product-query"
               type="search"
               wire:model="query"
               autocomplete="off">

        <button type="submit" wire:loading.attr="disabled" wire:target="search">
            <?= $escaper->escapeHtml(__('Search')) ?>
        </button>
    </form>

    <p wire:loading.remove wire:target="search">
        <?= $escaper->escapeHtml(__('%1 result(s)', (int) $magewire->resultCount)) ?>
    </p>
    <p wire:loading wire:target="search">
        <?= $escaper->escapeHtml(__('Searching...')) ?>
    </p>
</div>
```

`wire:model` is deferred in V3, so the form sends `query` with the `search` action. Use
`wire:model.live.debounce.250ms` only when the server must react while the user types, and use
`wire:model.blur` for synchronization on blur.

### Common HTML Directives

| Directive | Purpose |
|---|---|
| `wire:click="save"` | Call a public action |
| `wire:submit="save"` | Call an action on form submission |
| `wire:model="value"` | Defer property synchronization to the next request |
| `wire:model.live` | Synchronize immediately |
| `wire:model.blur` | Synchronize on blur |
| `wire:loading` / `.remove` | Show or hide content during a request |
| `wire:target="save"` | Scope loading/dirty behavior to an action or property |
| `wire:dirty` | Show unsaved client state |
| `wire:init="load"` | Run an action after initial render |
| `wire:poll.30s.visible="refresh"` | Poll at an explicit interval while visible |
| `wire:ignore` / `.self` | Protect third-party managed DOM from morphing |
| `wire:key="line-42"` | Give loop items stable identity |
| `wire:offline` | Show content while the browser is offline |
| `wire:stream="output"` | Receive streamed action output |

`wire:current` and SPA navigation are not ready in Magewire 3. Do not assume every Livewire V3
directive or modifier is supported merely because the JavaScript bundle recognizes it.

## State and Lifecycle

Use public state only for data the browser must round-trip. Built-in synthesizers cover scalars,
arrays, `stdClass`, backed enums, and `Magento\Framework\DataObject`. Write and area-register a
custom synthesizer for any other value object. Never expose repositories, sessions, database
connections, services, or models with hidden dependencies as public properties.

Use lifecycle hooks for request-bound concerns:

| Hook | Use |
|---|---|
| `boot()` | Run guards or setup on every request before mount/hydration |
| `mount(...)` | Initialize only on the first render from layout arguments |
| `hydrate()` / `hydrateFoo()` | Restore or inspect subsequent-request state |
| `updating($property, $value)` | Inspect a property before update |
| `updated($property, $value)` | React after update |
| `rendering()` | Prepare the block or switch its PHTML template |
| `rendered($view, $html)` | Observe rendered output |
| `dehydrate()` / `dehydrateFoo()` | Prepare state before snapshot serialization |
| `exception(Throwable $e, callable $stop)` | Handle a component exception |

There is no component `render()` method. Magento renders the template configured on the block. To
switch it, call `$this->magewireBlock()->setTemplate(...)` from `rendering()`.

Call `$this->skipRender()` when an action produces only an effect, such as a redirect or queue
operation, and no visible component state changed.

## Events and Browser Coordination

Prefer the V3 event API and attributes:

```php
use Magewirephp\Magewire\Attributes\On;

#[On('cart-updated')]
public function refreshCart(int $itemId): void
{
    // Reload only the state needed by this component.
}

public function addToCart(int $productId): void
{
    // Persist through a service, then dispatch named event data.
    $this->dispatch('cart-updated', itemId: $productId);
}
```

V3 events are browser events and bubble by default. Use `->self()` to restrict an event to the
source component or `->to(ComponentClass::class)` / `->to('alias')` to target a component. The
current Magewire `Event` API does not expose `up()`; replace V1 `emitUp()` with plain `dispatch()`.
From Alpine, use `$dispatch`; from standalone JavaScript, replace `Magewire.emit()` and
`Magewire.emitTo()` with `Magewire.dispatch()` and `Magewire.dispatchTo()`.

Register Alpine components at `alpine:init` and Magewire hooks at `magewire:init`, always with
`{ once: true }`. Magewire uses Alpine's CSP build, so keep complex expressions out of `x-*`
attributes and put them in an `Alpine.data()` registration. Load
`magento://skills/magewire-three/javascript` for the complete browser extension model.

## User Feedback and Redirects

Use transient notifications for toast UI:

```php
$this->magewireNotifications()
    ->make(__('Saved.'), 'save-result')
    ->asSuccess()
    ->withDuration(3000);
```

Use Magento flash messages when the message must enter Magento's message area:

```php
$this->magewireFlashMessages()
    ->make(__('The address was saved.'))
    ->asSuccess();
```

Redirect with a normal Magento URL. Do not pass the optional SPA navigation flag.

```php
$this->redirect($this->urlBuilder->getUrl('customer/address/index'));
```

## Compiled Templates, View Utilities, and CSP

V3 compiles Magewire PHTML files and supports directives such as `@if`, `@foreach`, `@auth`,
`@guest`, `@json`, `@translate`, `@script`, and `@fragment`. Raw PHP remains valid. Keep business
logic in the component or a service, not in compiler directives.

Magewire auto-binds its view model under the Magewire layout tree unless a block already supplies a
`view_model`. Access shared utilities through:

```php
$viewModel = $block->getData('view_model');
$viewModel->utils()->security()->getCsrfToken();
$viewModel->utils()->env()->isDeveloperMode();
$viewModel->utils()->fragment();
$viewModel->utils()->layout();
```

Wrap inline scripts in a Script fragment so the theme can add the correct CSP nonce or hash. Do not
hand-roll nonces or emit unprotected inline scripts.

```php
<?php $fragment = $viewModel->utils()->fragment()->make() ?>
<?php $script = $fragment->script()->start() ?>
<script>
    // Register behavior without inline Alpine expressions.
</script>
<?php $script->end() ?>
```

## Security and Performance Gates

- Authorize every sensitive public action with Magento ACL, customer/session ownership, or an
  application service. A snapshot checksum is integrity protection, not authorization.
- Treat action parameters and public properties as untrusted input. Re-fetch entities and verify
  store/customer ownership before mutation.
- Escape HTML, attributes, URLs, and JavaScript with the matching `Escaper` method.
- Keep CSRF/FormKey protection enabled on `/magewire/update`.
- Use deferred inputs by default; debounce live search by 250-500 ms.
- Keep public state small. Every public property is serialized on every request.
- Use stable `wire:key` values in loops and `wire:ignore` around third-party widgets.
- Set explicit, conservative polling intervals and use `.visible` where possible.
- Configure and monitor Magewire rate limiting for public actions.

## Testing and Troubleshooting

Test the full browser lifecycle; PHP unit tests alone do not validate snapshots or morphing.

1. Assert the initial component HTML and one root element.
2. Trigger each action and inspect the `/magewire/update` response.
3. Assert state, validation/error UI, notifications, redirects, and authorization failures.
4. Test rapid input, double submission, offline/loading states, and keyed list updates.
5. Check browser console output for Alpine, CSP, and morph warnings.

Diagnose common failures in this order:

| Symptom | Check |
|---|---|
| Nothing reacts | Theme compatibility package, emitted Magewire script, duplicate Alpine, JS errors |
| Checksum mismatch | Changed Magento crypt key, stale FPC/browser page, altered request body |
| Action is not called | Public method name, root element, `wire:target`, authorization exception |
| Feature never boots | Registration is in global rather than area-scoped DI |
| State leaks between blocks | Missing `shared="false"` on reused component object arguments |
| DOM widget resets | Missing `wire:ignore` or unstable/missing `wire:key` |
| Streaming arrives at once | PHP, Nginx, Varnish, CDN, or FPC buffering |

## How the Backwards-Compatibility Layer Works

Magewire registers the `magewire_backwards_compatibility` Feature for the frontend by default, but
V1 behavior is opt-in per component. Enable it with the exact attribute namespace:

```php
use Magewirephp\Magewire\Features\SupportMagewireBackwardsCompatibility\HandleBackwardsCompatibility;

#[HandleBackwardsCompatibility]
class V1Cart extends \Magewirephp\Magewire\Component
{
}
```

The resolver stores the result as `magewire:bc` and serializes it to `memo.bc.enabled`, preserving
the mode across update requests. The PHP Feature adapts V1 state and lifecycle expectations; the
theme JavaScript shim adapts model modifiers, entanglement, hook names, and component aliases.
Hyva Checkout can enable BC for its component subtree, while an explicit `enabled: false` still
opts one migrated component out.

BC does not rewrite source or fix incompatible application behavior. Migrate one component at a
time, opt it out, browser-test it, then remove the attribute. Disable the site-wide Feature only
after every component is native; doing so ignores all per-component attributes. Load
`magento://skills/magewire-three/backwards-compatibility` for flag precedence, the exact server and
browser adaptations, the DI switch, inheritance behavior, and troubleshooting.

## V1 to V3 Migration Matrix

| V1 | Native V3 | BC handles it? |
|---|---|---|
| `wire:model` live | `wire:model.live` | Yes |
| `wire:model.defer` | `wire:model` | Yes |
| `wire:model.lazy` | `wire:model.blur` | Yes |
| `wire:model.delay.500ms` | `wire:model.live.debounce.500ms` | Yes |
| `$wire.entangle('x')` live | `$wire.entangle('x').live` | Yes |
| `$listeners` | `#[On('event')]` | Still works; migrate manually |
| `emit()` | `dispatch()` | Wrapper remains |
| `emitUp()` | `dispatch()` (events bubble) | Wrapper remains |
| `emitSelf()` | `dispatch()->self()` | Wrapper remains |
| `emitTo()` | `dispatch()->to()` | Wrapper remains |
| `dispatchBrowserEvent()` | `dispatch()` | Wrapper remains |
| JS `Magewire.emit*()` | `Magewire.dispatch()` / `dispatchTo()` | Migrate manually |
| `getPublicProperties()` | `all()` | Wrapper remains |
| public `$id` | `id()` / `getId()` | Wrapper remains |
| `canRender()` | `skipRender()` | Migrate manually |
| `switchTemplate()` / `render()` | `rendering()` + block `setTemplate()` | Migrate manually |
| `updating($value, $name)` | `updating($property, $value)` | Current BC adapts common hooks |
| `dispatch*Message()` | notification or flash-message builder | Wrapper remains |
| component `$loader` mapping | `wire:loading` / V3 loader feature | Migrate UI deliberately |
| V1 Hydrator | Synthesizer | Migrate manually |
| `serverMemo` payload | snapshot `data` + `memo` + checksum | Runtime/BC handles it |
| global Magewire DI | area-scoped DI | No |
| storefront-only components | admin support via `magewirephp/magewire-admin` | No |
| custom V1 JS hooks | V3 `component.init`, `commit`, `morph.*` hooks | BC re-fires common names |
| `component.data` | `component.$wire` | Yes |
| `component.deferredActions` | `component.queuedUpdates` | Yes |

Complete a migration with repository searches for V1 directives, `entangle`, `$listeners`,
`emit*`, `getPublicProperties`, direct `$id`, old JS hook names, custom hydrators, and Magewire types
registered in global `etc/di.xml`. Remove BC per component only after its browser tests pass without
the shim.

## Do Not Assume Full Livewire Parity

Magewire ports much of Livewire V3, but Magento integration determines which features are active.
At the current V3 line:

- Upstream `#[Validate]` validation is not active; use project/Magento validation and verify the
  installed Magewire validation API before adopting examples.
- Form objects, file uploads, pagination, computed properties, lazy components, and JS evaluation
  must not be assumed available without checking the installed package.
- `wire:current` and SPA-style `navigate` are not ready for use.
- Admin components require `magewirephp/magewire-admin` and admin-scoped DI/layout/templates.

When Livewire documentation and Magewire source disagree, follow the installed Magewire source.
