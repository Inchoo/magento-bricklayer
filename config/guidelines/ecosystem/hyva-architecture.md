# Hyvä Theme Architecture

> Related: See [hyva-patterns.md](hyva-patterns.md) for ViewModels, private content, GraphQL, CSP, icons, modals, and best practices.

## Overview

Hyva is a modern, performance-focused Magento 2 frontend theme that replaces the default Luma stack (RequireJS, KnockoutJS, jQuery, LESS) with Alpine.js and Tailwind CSS. The CSP (Content Security Policy) variant eliminates inline `eval()` for stricter browser security.

## Key Differences from Luma

| Aspect | Luma | Hyva |
|--------|------|------|
| JavaScript | RequireJS + KnockoutJS + jQuery | Alpine.js (v3) |
| CSS | LESS preprocessor | Tailwind CSS (v4) |
| Page Speed | 30-50 (mobile) | 90+ (mobile) |
| Bundle Size | 2MB+ | ~200KB |
| Data Fetching | AJAX + customer-data sections | GraphQL + private-content-loaded event |
| Icons | Font-based (icon fonts) | SVG (Lucide Icons / Heroicons) |
| Complexity | High (deep abstraction layers) | Low (declarative, template-centric) |

## Theme Variants

| Variant | Package | Registration | Notes |
|---------|---------|--------------|-------|
| Default CSP | `hyva-themes/magento2-default-theme-csp` | `frontend/Hyva/default-csp` | CSP-safe, no `unsafe-eval` |
| Default (legacy) | `hyva-themes/magento2-default-theme` | `frontend/Hyva/default` | Allows inline eval |

**Always prefer the CSP variant** for new projects. It uses `Alpine.data()` registration instead of inline expressions.

## Architecture

### Package Ecosystem

```
hyva-themes/magento2-theme-module          # Core PHP module (ViewModels, plugins, GraphQL)
hyva-themes/magento2-default-theme-csp     # Default theme (templates, layouts, Tailwind)
hyva-themes/magento2-base-layout-reset     # Strips Luma layout handles
hyva-themes/magento2-graphql-tokens        # GraphQL token management
hyva-themes/magento2-graphql-view-model    # GraphQL ViewModels
hyva-themes/magento2-email-module          # Email template handling
hyva-themes/magento2-compat-module-fallback # Luma fallback for incompatible modules
hyva-themes/magento2-order-cancellation-webapi # Order cancellation API
```

### Theme Directory Structure

```
app/design/frontend/Vendor/hyva-child/
├── etc/
│   ├── view.xml                    # Image size configuration
│   └── hyva-libraries.json         # Alpine version declaration ({"alpine": "3"})
├── media/
│   └── preview.png
├── web/
│   ├── css/
│   │   └── styles.css              # Compiled Tailwind output (generated)
│   └── tailwind/
│       ├── tailwind-source.css     # Main entry point for Tailwind
│       ├── hyva.config.json        # Design tokens + module source config
│       ├── package.json            # Node dependencies (@tailwindcss/cli ^4.x)
│       ├── base/                   # Base/reset styles
│       ├── components/             # Component layer (@layer components)
│       ├── theme/                  # Theme-specific styles
│       ├── utilities/              # Utility overrides
│       └── generated/              # Auto-generated from hyva-modules
│           ├── hyva-source.css     # Module source paths
│           └── hyva-tokens.css     # Design token CSS custom properties
├── Magento_Catalog/
│   ├── layout/
│   └── templates/
├── Magento_Theme/
│   ├── layout/
│   └── templates/
├── theme.xml                       # NO <parent> tag for base Hyva themes
├── registration.php
└── composer.json
```

### Creating a Child Theme

```xml
<!-- theme.xml -->
<theme xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="urn:magento:framework:Config/etc/theme.xsd">
    <title>Vendor Custom Hyva Theme</title>
    <parent>Hyva/default-csp</parent>
    <media>
        <preview_image>media/preview.png</preview_image>
    </media>
</theme>
```

```php
// registration.php
ComponentRegistrar::register(ComponentRegistrar::THEME, 'frontend/Vendor/hyva-child', __DIR__);
```

## Tailwind CSS v4

Hyva uses Tailwind CSS v4 with the `@tailwindcss/cli` package. Configuration is CSS-native (no `tailwind.config.js`).

### Entry Point (tailwind-source.css)

```css
@import "@hyva-themes/hyva-modules/css";
@import "tailwindcss" source(none);
@source "../../**/*.phtml";
@source "../../**/*.xml";

@import "./base";
@import "./components";
@import "./theme";
@import "./utilities";

@import "./generated/hyva-source.css";
@import "./generated/hyva-tokens.css";

@theme {
    --color-bg: var(--color-slate-50);
    --color-fg: var(--color-slate-950);
    --color-fg-secondary: var(--color-slate-600);
    --color-surface: var(--color-white);
}
```

### Design Tokens (hyva.config.json)

```json
{
  "tailwind": {
    "include": [],
    "exclude": []
  },
  "tokens": {
    "values": {
      "color": {
        "primary": {
          "lighter": "oklch(52% 0.2 265)",
          "DEFAULT": "oklch(46% 0.2 265)",
          "darker": "oklch(28% 0.2 265)"
        },
        "secondary": {
          "lighter": "oklch(72% 0.2 150)",
          "DEFAULT": "oklch(53% 0.15 150)",
          "darker": "oklch(39% 0.1 153)"
        },
        "on": {
          "primary": "#fff",
          "secondary": "#fff"
        }
      },
      "form": {
        "radius": "var(--radius-lg)",
        "stroke": "var(--color-slate-400)",
        "active-color": "var(--color-primary)"
      }
    }
  }
}
```

### Build Commands

```bash
cd web/tailwind/
npm install                    # Install dependencies (Node >= 20)
npm run generate               # Generate hyva-source.css + hyva-tokens.css
npm run watch                  # Dev mode with file watching
npm run build                  # Production build (minified)
```

## Alpine.js Components

### CSP-Compatible Pattern (Required for CSP Theme)

In CSP mode, all Alpine logic must use `Alpine.data()` registration. **No inline expressions** are allowed in `x-data`, `@click`, `:class`, etc.

```php
<?php
$items = [['name' => 'Foo'], ['name' => 'Bar']];
?>

<div x-data="myComponent"
     data-items="<?= $escaper->escapeHtmlAttr(json_encode($items)) ?>">
    <button type="button" @click="toggle">Toggle</button>
    <template x-if="isActive">
        <div>Active content</div>
    </template>
    <template x-for="(item, index) in items">
        <li x-text="item.name"></li>
    </template>
</div>

<script>
    function myComponent() {
        return {
            isActive: true,
            items: [],
            init() {
                this.items = JSON.parse(this.$root.dataset.items);
            },
            toggle() {
                this.isActive = !this.isActive;
            }
        };
    }

    window.addEventListener(
        'alpine:init',
        () => Alpine.data('myComponent', myComponent),
        {once: true}
    );
</script>
```

**CSP Key Rules:**
- `x-data` must reference a registered component name (string), not an inline object
- All mutations must happen inside methods, not inline expressions
- `x-model` is restricted; use `@input` + `:value` pattern instead
- Pass data to components via `data-*` attributes and parse in `init()`
- Register components with `Alpine.data()` inside an `alpine:init` listener
- Use `{once: true}` on the event listener to avoid duplicate registration

### Non-CSP Pattern (Legacy, Avoid for New Code)

```html
<div x-data="{ isOpen: false }">
    <button @click="isOpen = !isOpen">Toggle</button>
    <div x-show="isOpen" x-transition>Content</div>
</div>
```

### Lazy Loading with x-defer

```html
<button x-data="initCompareOnProductList"
        x-defer="intersect"
        @click.prevent="addToCompare">
    Compare
</button>
```

The `x-defer="intersect"` attribute delays Alpine initialization until the element enters the viewport.

### Event Communication

```javascript
// Dispatch event
window.dispatchEvent(new CustomEvent('toggle-cart', {
    detail: { open: true }
}));

// Listen in template (CSP-compatible)
<div x-data="cartListener"
     @toggle-cart.window="onToggle">
```
