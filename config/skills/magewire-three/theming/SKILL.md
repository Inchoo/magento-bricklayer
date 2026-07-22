---
name: magewire-three-theming
description: Build, extend, review, and troubleshoot Magewire 3 theme compatibility modules for Magento storefronts, Hyva Theme, Hyva Checkout, and adminhtml. Use for package selection, area-scoped layout and DI, Alpine loading order, CSP-safe templates, Tailwind discovery, and custom theme adapters.
---

# Magewire 3 Theming

Use this resource for the Magento module plumbing around a theme. For component behavior, start
with `magento://skills/magewire-three`. For browser APIs and CSP-safe JavaScript, also load
`magento://skills/magewire-three/javascript`.

## Use the Current Package Model

Magewire 3.2 and newer ships theme integrations as separate Composer packages. Do not expect a
`themes/` directory inside `magewirephp/magewire`.

| Rendering context | Package |
|---|---|
| Theme-agnostic core | `magewirephp/magewire` |
| Hyva storefront | `magewirephp/magewire-hyva-theme` |
| Hyva Checkout 1.4+ | `magewirephp/magewire-hyva-checkout` |
| Magento adminhtml | `magewirephp/magewire-admin` |

Composer package names are stable integration boundaries. Verify Magento module names with
`bin/magento module:status` or the installed package's `src/etc/module.xml`; do not infer them from
package names.

Install only the adapters required by the application:

```bash
composer require magewirephp/magewire:^3.2
composer require magewirephp/magewire-hyva-theme
composer require magewirephp/magewire-hyva-checkout
composer require magewirephp/magewire-admin
```

Hyva Checkout 1.0-1.3 uses Magewire V1. Hyva Checkout 1.4 and newer uses Magewire 3. The checkout
adapter is not a general replacement for the Hyva Theme adapter; its package declares the theme
adapter as a dependency.

## Decide Where the Change Belongs

| Change | Owner |
|---|---|
| Component state and actions | Application module |
| Reusable framework behavior | Magewire core Feature or Mechanism |
| Theme loading order, template bridge, or build integration | Theme compatibility module |
| Checkout-specific migration behavior | Hyva Checkout compatibility module |
| Admin routing, session checks, or RequireJS integration | `magewire-admin` |

A compatibility module should stay thin. It may register area-scoped Features, add layout blocks,
override integration templates, or connect to a theme build pipeline. Domain behavior still belongs
in application services and components.

## Build a Custom Compatibility Module

Start with a normal Magento module packaged independently from Magewire core:

```text
Vendor/MagewireThemeAdapter/
|-- composer.json
`-- src/
    |-- registration.php
    |-- etc/module.xml
    |-- etc/frontend/di.xml
    `-- view/frontend/layout/default.xml
```

Declare direct dependencies in both Composer and Magento sequencing. Core must load before the
adapter, and the target theme module must be available before code references its contracts.

```json
{
  "name": "vendor/magewire-theme-adapter",
  "type": "magento2-module",
  "require": {"magewirephp/magewire": "^3.2"},
  "autoload": {
    "files": ["src/registration.php"],
    "psr-4": {"Vendor\\MagewireThemeAdapter\\": "src/"}
  }
}
```

```xml
<module name="Vendor_MagewireThemeAdapter">
    <sequence>
        <module name="Magewirephp_Magewire"/>
        <module name="Vendor_Theme"/>
    </sequence>
</module>
```

Use `etc/frontend/di.xml` and `view/frontend/` for storefront adapters. Use `etc/adminhtml/di.xml`
and `view/adminhtml/` for backend adapters. A global Feature registration can leak theme behavior
into the wrong area, and Magewire's service provider reads its extension collections per area.

## Use the Layout Extension Points Correctly

Inspect the installed core `view/base/layout/default.xml` before extending it. In Magewire 3.2,
the important public insertion points include:

| Layout name | Type | Intended content |
|---|---|---|
| `magewire.alpinejs.load` | container | Alpine bundle loading order |
| `magewire.alpinejs` | container | Magewire Alpine globals |
| `magewire.alpinejs.components` | container | Reusable Alpine data components |
| `magewire.alpinejs.directives` | container | Custom Alpine directives |
| `magewire.ui-components` | container | Browser-side UI components |
| `magewire.utilities` | block | Utility registrations and children |
| `magewire.addons` | block | Addon registrations and children |
| `magewire.directives` | block | Magewire `wire:*` directive bridges |
| `magewire.features` | block | Feature-specific scripts |
| `magewire.after` | container | Final application or theme additions |

Reference a container with `<referenceContainer>` and a block with `<referenceBlock>`. Adding a
child to `magewire.features` does not replace its template; replacing the block or its template is
a deliberate, higher-risk operation.

```xml
<referenceContainer name="magewire.alpinejs.components">
    <block name="vendor.magewire.alpine.product-picker"
           template="Vendor_MagewireThemeAdapter::js/product-picker.phtml"/>
</referenceContainer>

<referenceBlock name="magewire.features">
    <block name="vendor.magewire.feature.analytics"
           template="Vendor_MagewireThemeAdapter::features/analytics.phtml"/>
</referenceBlock>
```

Do not edit the core layout to solve a theme-specific problem. Add or move blocks from the adapter
layout and keep names globally unique.

## Alpine and CSP

Magewire supplies Alpine's CSP build. A theme adapter is responsible for making sure the page loads
one Alpine instance in the required order. Duplicate Alpine instances cause directives, stores, and
component state to register against different runtimes.

- Keep complex logic in named Alpine methods or registered JavaScript modules.
- Render script through Magewire's CSP fragment utility, not a raw inline script.
- Use `magewire.alpinejs.load` only for ordering the runtime itself.
- Use `magewire.alpinejs.components` and `magewire.alpinejs.directives` for registrations.
- Test with the site's enforced CSP, not only report-only mode.

## Tailwind and Static Assets

The adapter owns discovery of its own templates. For Hyva, connect the module path to Hyva's
Tailwind source generation using the supported Hyva event/API for the installed version. The
official Magewire Hyva adapter's `HyvaConfigGenerateBefore` observer is the canonical example.

- Include adapter PHTML and layout sources in content scanning.
- Include application module templates that emit utility classes.
- Prefer CSS custom properties for runtime theme values that do not require class generation.
- Rebuild the theme CSS after adding new class names; Magento static deployment alone is not a
  Tailwind build.
- Do not add Tailwind to adminhtml; use Magento's admin asset pipeline.

## Adminhtml Is a Separate Integration

Use `magewirephp/magewire-admin` rather than recreating backend plumbing in an application module.
Adminhtml needs more than a layout handle: the adapter handles backend routing and session
validation, the update URI, early asset injection around RequireJS, component resolution timing,
and Prototype.js interference.

Register application components and Features in the adminhtml area, but keep the infrastructure in
the official package. When debugging, verify the admin update URL, authorization failure, script
order, and the installed adapter version before changing component code.

## Theme Review Checklist

- Core and exactly the required compatibility packages are installed.
- Package constraints match the installed Magewire minor line.
- Magento module sequencing includes core and direct theme dependencies.
- DI, events, layout, and templates use the intended Magento area.
- Container and block references match the installed core layout.
- Only one Alpine instance is present and script order is deterministic.
- PHTML scripts use CSP fragments and Alpine expressions remain conservative.
- Tailwind scans every module that emits theme utility classes.
- Theme-specific behavior does not leak into Magewire core or component domain logic.
- Storefront, checkout, and admin adapters are not treated as interchangeable.

## Common Failures

| Symptom | Check |
|---|---|
| Magewire HTML renders but nothing reacts | Compatibility package, one Alpine instance, script order, CSP console errors |
| Feature works in storefront but not admin | Area-scoped DI and installed `magewire-admin` package |
| Classes disappear in production CSS | Tailwind source registration and production content paths |
| Core UI stops rendering | Accidental template replacement on a block instead of adding a child |
| Checkout components remain in V1 mode | Checkout version and per-component BC flag; load the BC resource |
| Layout change has no effect | Active theme handle, area, cache, and exact installed layout name |
