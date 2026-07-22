---
name: magewire-three-javascript
description: Build CSP-compatible JavaScript around Magewire 3 and Alpine in Magento 2. Use for lifecycle hooks, commit/request/morph debugging, Alpine data and bindings, Magewire utilities, addons, custom wire directives, Feature bridge scripts, layout placement, cleanup, and frontend event dispatch.
---

# Magewire 3 JavaScript and Alpine

Use [`magewire-three`](../SKILL.md) for component PHP and templates. Add JavaScript only when
`wire:*`, server actions, and small Alpine state cannot express the interaction cleanly.

## Respect the Active Compatibility Package

Magewire's browser runtime is based on the Livewire V3 bundle and exposes it as `window.Magewire`.
Theme packages control script order, CSP integration, styling, and compatibility aliases. Verify the
installed package and emitted events before copying examples between Hyvä, Hyvä Checkout, and admin.

Do not load a second Alpine instance. Magewire ships Alpine's CSP build; duplicate Alpine runtimes
split stores, directives, and component state.

## CSP-Safe Script Template

Put JavaScript PHTML under `view/{area}/templates/js/` or the owning Feature directory. Use the
fragment API so Magento can apply a nonce or register a hash for cacheable output.

```php
<?php

declare(strict_types=1);

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;
use Magewirephp\Magewire\ViewModel\Magewire as MagewireViewModel;

/** @var Escaper $escaper */
/** @var Template $block */
/** @var MagewireViewModel $magewireViewModel */

$magewireViewModel = $block->getData('view_model');
$fragment = $magewireViewModel->utils()->fragment();
?>
<?php $script = $fragment->make()->script()->start() ?>
<script>
    // CSP-managed JavaScript.
</script>
<?php $script->end() ?>
```

Always close the fragment. Escape PHP inserted into JavaScript with `escapeJs()` and prefer
`json_encode()` for structured data. Never hand-roll CSP nonces or output an unmanaged inline
`<script>`.

## Registration Timing

| Event | Register |
|---|---|
| `alpine:init` | `Alpine.data`, `Alpine.bind`, stores, pure utilities |
| `magewire:init` | Magewire hooks and Feature bridges after the alias is ready |
| `magewire:initialized` | Custom directives that need full DOM/runtime initialization |

Use `{ once: true }` on registration listeners. The underlying bundle emits `livewire:*`; active
compatibility packages may bridge those to `magewire:*`. Follow the installed package if the alias
events differ.

## Alpine CSP Rules

Alpine's CSP evaluator is not a full JavaScript evaluator. Keep HTML attributes to property access,
simple method references, and simple bindings. Move function calls, global APIs, iteration mapping,
nested assignment, and other complex expressions into `Alpine.data()` or `Alpine.bind()`.

```javascript
function productPicker() {
    'use strict';

    return {
        selectedId: null,

        select() {
            this.selectedId = Number(this.$el.dataset.productId);
            this.$dispatch('product-selected', { productId: this.selectedId });
        },

        bindings: {
            option: {
                ['x-on:click']() {
                    this.select();
                }
            }
        }
    };
}

document.addEventListener(
    'alpine:init',
    () => Alpine.data('productPicker', productPicker),
    { once: true }
);
```

Prefer named methods over logic embedded in `x-*` attributes. Access loop values through Alpine
scope inside the method rather than interpolating code strings.

## Events

From a component subtree, keep payload construction in a named Alpine method so the HTML remains
compatible with Alpine's CSP parser:

```html
<button type="button" x-on:click="dispatchCartUpdate">Update</button>
```

```javascript
dispatchCartUpdate() {
    this.$dispatch('cart-updated', { itemId: this.selectedId })
}
```

From standalone JavaScript:

```javascript
Magewire.dispatch('cart-updated', { itemId: 42 });
Magewire.dispatchTo('cart-summary', 'cart-updated', { itemId: 42 });
```

From `$wire`, use `$dispatch`, `$dispatchSelf`, or `$dispatchTo` when those magic methods are clearer.
V3 events are browser events and bubble by default. The current PHP `Event` API has `self()` and
`to()`, but no `up()`; bubbling replaces V1's explicit upward emit.

## Browser Hooks

Register hooks at initialization and clean up any external resources they create.

```javascript
document.addEventListener('magewire:init', () => {
    Magewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
        respond(() => {
            // Response received, before success/failure completion.
        });

        succeed(({ snapshot, effects }) => {
            // Inspect response effects or schedule post-morph work.
        });

        fail(({ status, content, preventDefault }) => {
            // Log or replace error handling deliberately.
        });
    });
}, { once: true });
```

Useful hooks include:

| Hook | Use |
|---|---|
| `component.init` | Per-component initialization; register cleanup |
| `element.init` | Element enters a component tree |
| `request` | Network options, response, or failure diagnostics |
| `commit` | One component's update payload and result |
| `morph.updating` | Inspect or skip an element update |
| `morph.updated` | Element updated |
| `morph.removing` / `morph.removed` | Teardown around removal |
| `morphed` | Component DOM morph completed |

Do not build business logic on undocumented payload fields. Check the installed JavaScript bundle
for hook signatures when upgrading.

## Utilities, Addons, and Alpine Components

Choose one ownership model:

| Type | Purpose | Registration |
|---|---|---|
| Utility | Pure stateless helper | `MagewireUtilities.register()` during `alpine:init` |
| Addon | Reusable stateful API | `MagewireAddons.register()` |
| Alpine component | Template-facing state/methods | `Alpine.data()` during `alpine:init` |
| Alpine binding | Reusable attribute bundle | `Alpine.bind()` during `alpine:init` |
| Directive | New `wire:*` behavior | `Magewire.directive()` after initialization |
| Feature bridge | Convert response effects/hooks into UI behavior | `magewire:init` |

### Utility

```javascript
function magewireCurrencyUtility() {
    'use strict';

    return {
        minorToMajor(value) {
            return Number(value) / 100;
        }
    };
}

document.addEventListener('alpine:init', () => {
    window.MagewireUtilities.register('currency', magewireCurrencyUtility);
}, { once: true });
```

Utilities must not depend on component state. Give each one a narrow responsibility.

### Addon

```javascript
function magewireQueueAddon() {
    'use strict';

    return {
        items: [],

        push(item) {
            this.items.push(item);
        }
    };
}

window.MagewireAddons.register('queue', magewireQueueAddon, true);
```

The reactive flag is appropriate only when addon state drives Alpine UI. Guard optional access with
`MagewireAddons.has('queue')`. Addons may call Magewire later, but registration must not assume the
runtime alias already exists.

## Custom Directive

```javascript
document.addEventListener('magewire:initialized', () => {
    Magewire.directive('mage:confirm', ({ el, directive, cleanup }) => {
        const confirmAction = event => {
            if (!window.confirm(directive.expression)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        };

        el.addEventListener('click', confirmAction, { capture: true });
        cleanup(() => el.removeEventListener('click', confirmAction, { capture: true }));
    });
}, { once: true });
```

Use the `mage:` prefix for Magewire-specific directives. Always unregister listeners, observers,
timers, and third-party widgets through the provided cleanup callback.

## Feature Bridge

A PHP Feature pushes a named effect during dehydrate. Its JavaScript bridge listens for that effect
and delegates UI state to an addon or Alpine component.

```javascript
document.addEventListener('magewire:init', () => {
    const addons = window.MagewireAddons;

    Magewire.hook('commit', ({ succeed }) => {
        succeed(({ effects }) => {
            if (effects.audit && addons.has('audit')) {
                addons.audit.receive(effects.audit);
            }
        });
    });
}, { once: true });
```

Keep Feature-owned scripts, addons, components, and templates together under
`templates/magewire-features/support-{name}/`. Standalone utilities/addons belong under the shared
`templates/js/` hierarchy.

## Layout Placement

Read the installed `src/view/base/layout/default.xml`; several extension points are blocks, not
containers.

| Content | Parent |
|---|---|
| Alpine component | `magewire.alpinejs.components` container |
| Standalone UI | `magewire.ui-components` container |
| Utility | child block of `magewire.utilities` |
| Addon | child block of `magewire.addons` |
| Custom Magewire directive | child block of `magewire.directives` |
| Feature bridge | child block of `magewire.features` |
| Final HTML/debug output | `magewire.after` container |

Use `<referenceBlock>` for a block parent and `<referenceContainer>` for a container parent. Do not
inject into `magewire.internal`; only compatibility packages should use the internal BC extension
point.

## Review Checklist

- One Alpine runtime and the correct compatibility package are present.
- Every inline script uses a Magewire fragment.
- Registration listeners use the correct timing and `{ once: true }`.
- HTML expressions remain CSP-evaluable; complex logic lives in named methods/bindings.
- Hook and DOM listeners have cleanup.
- Optional addons/utilities are checked before access.
- Stable `wire:key` values protect dynamic lists.
- `wire:ignore` protects third-party-owned DOM.
- The script is registered under the correct block/container and area.
- No code depends on unsupported SPA navigation or undocumented Livewire internals.
