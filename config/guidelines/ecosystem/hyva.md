# Hyva Theme Guidelines

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

## ViewModels

Hyva replaces Block-based data access with ViewModels. Access via `ViewModelRegistry`:

### Core ViewModels

| ViewModel | Namespace | Purpose |
|-----------|-----------|---------|
| `CurrentProduct` | `Hyva\Theme\ViewModel` | Access current product on PDP |
| `CurrentCategory` | `Hyva\Theme\ViewModel` | Access current category |
| `ProductPage` | `Hyva\Theme\ViewModel` | Product page helpers (add-to-cart URL, descriptions) |
| `ProductListItem` | `Hyva\Theme\ViewModel` | Product list rendering helpers |
| `ProductList` | `Hyva\Theme\ViewModel` | Fetch product lists (related, upsell, cross-sell) |
| `ProductPrice` | `Hyva\Theme\ViewModel` | Price rendering utilities |
| `StoreConfig` | `Hyva\Theme\ViewModel` | Access store configuration values |
| `Modal` | `Hyva\Theme\ViewModel` | Modal dialog builder |
| `SvgIcons` | `Hyva\Theme\ViewModel` | SVG icon rendering |
| `LucideIcons` | `Hyva\Theme\ViewModel` | Lucide icon set (default in CSP theme) |
| `HeroiconsOutline` | `Hyva\Theme\ViewModel` | Heroicons outline set |
| `HeroiconsSolid` | `Hyva\Theme\ViewModel` | Heroicons solid set |
| `Navigation` | `Hyva\Theme\ViewModel` | Category navigation tree |
| `Cart\Items` | `Hyva\Theme\ViewModel\Cart` | Cart item data |
| `Cart\GraphQlQueries` | `Hyva\Theme\ViewModel\Cart` | Cart GraphQL queries |
| `CustomerSectionData` | `Hyva\Theme\ViewModel` | Private content section data |
| `Wishlist` | `Hyva\Theme\ViewModel` | Wishlist functionality |
| `ProductCompare` | `Hyva\Theme\ViewModel` | Compare list functionality |
| `Cookie` | `Hyva\Theme\ViewModel` | Cookie configuration |
| `Currency` | `Hyva\Theme\ViewModel` | Currency formatting |
| `BlockJsDependencies` | `Hyva\Theme\ViewModel` | Register JS dependencies for blocks |
| `HyvaCsp` | `Hyva\Theme\ViewModel` | CSP nonce management |
| `Image` | `Hyva\Theme\ViewModel` | Image helper utilities |
| `ReCaptcha` | `Hyva\Theme\ViewModel` | reCAPTCHA integration |
| `Slider` | `Hyva\Theme\ViewModel` | Product slider configuration |
| `SwatchRenderer` | `Hyva\Theme\ViewModel` | Color/image swatch rendering |

### Using ViewModels in Templates

```php
<?php
/** @var \Hyva\Theme\Model\ViewModelRegistry $viewModels */

// Direct require (preferred)
$productPage = $viewModels->require(\Hyva\Theme\ViewModel\ProductPage::class);
$lucideIcons = $viewModels->require(\Hyva\Theme\ViewModel\LucideIcons::class);
$storeConfig = $viewModels->require(\Hyva\Theme\ViewModel\StoreConfig::class);

// Access store config
$storeName = $storeConfig->getStoreConfig('general/store_information/name');
?>

<?= $lucideIcons->shoppingCartHtml('', 24, 24, ['aria-hidden' => 'true']) ?>
```

### Creating Custom ViewModels

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class CustomData implements ArgumentInterface
{
    public function __construct(
        private readonly \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
    }

    public function getProductJson(\Magento\Catalog\Api\Data\ProductInterface $product): string
    {
        return json_encode([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getFinalPrice(),
        ]);
    }
}
```

Register in layout XML:
```xml
<block name="custom.data" class="Magento\Framework\View\Element\Template"
       template="Vendor_Module::custom.phtml">
    <arguments>
        <argument name="view_model" xsi:type="object">Vendor\Module\ViewModel\CustomData</argument>
    </arguments>
</block>
```

## Private Content (Section Data)

Hyva uses the `private-content-loaded` custom browser event instead of Luma's `Magento_Customer/js/customer-data`:

```javascript
<script>
    window.addEventListener('private-content-loaded', (event) => {
        const sectionData = event.detail.data;

        // Customer data
        const isLoggedIn = sectionData.customer && !!sectionData.customer.firstname;
        const customerName = sectionData.customer?.firstname;

        // Cart data
        const cartCount = sectionData.cart ? sectionData.cart.summary_count : 0;
        const isGuestCheckoutAllowed = sectionData.cart?.isGuestCheckoutAllowed;
    }, { once: true });
</script>
```

## GraphQL Integration

Hyva prefers GraphQL for dynamic data (cart, customer, checkout). The `hyva-themes/magento2-graphql-tokens` module handles token management.

```javascript
async function fetchCartData() {
    const query = `{
        cart(cart_id: "${cartId}") {
            items { id quantity product { name sku } }
            prices { grand_total { value currency } }
        }
    }`;

    const response = await fetch('/graphql', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Store': 'default',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ query })
    });

    return response.json();
}
```

## CSP (Content Security Policy)

The CSP theme variant eliminates the need for `unsafe-eval` in the Content-Security-Policy header.

### HyvaCsp ViewModel

Use `HyvaCsp` to register inline scripts that need CSP nonces:

```php
<?php
/** @var \Hyva\Theme\ViewModel\HyvaCsp $hyvaCsp */
$hyvaCsp = $viewModels->require(\Hyva\Theme\ViewModel\HyvaCsp::class);
?>

<script>
    // Your inline script here
    function myComponent() { return { /* ... */ } }
    window.addEventListener('alpine:init', () => Alpine.data('myComponent', myComponent), {once: true});
</script>
<?php $hyvaCsp->registerInlineScript() ?>
```

The `registerInlineScript()` call must come **after** the `<script>` tag. It registers the preceding script for CSP nonce injection.

## Icons

The CSP theme uses **Lucide Icons** by default (replacing Heroicons):

```php
<?php
$lucideIcons = $viewModels->require(\Hyva\Theme\ViewModel\LucideIcons::class);
?>

<?= $lucideIcons->shoppingCartHtml('', 24, 24, ['aria-hidden' => 'true']) ?>
<?= $lucideIcons->pencilHtml('text-primary', 20, 20) ?>
<?= $lucideIcons->scaleHtml('', 20, 20, ['aria-hidden' => 'true']) ?>
```

For custom SVG icons, use `SvgIcons`:

```php
$svgIcons = $viewModels->require(\Hyva\Theme\ViewModel\SvgIcons::class);
echo $svgIcons->renderHtml('custom-icon', 24, 24);
```

## JS Dependency Management

Use `BlockJsDependencies` to ensure JS templates are rendered on pages that need them:

```php
$viewModels->require(\Hyva\Theme\ViewModel\BlockJsDependencies::class)
    ->setBlockTemplateDependency($block, 'Magento_Catalog::product/list/js/price-box.phtml');
```

## Modal Dialogs

```php
<?php
$modal = $viewModels->require(\Hyva\Theme\ViewModel\Modal::class)
    ->createModal()
    ->withDialogRefName('my-dialog')
    ->withContent(<<<'EOCONTENT'
<div>
    <h3>Dialog Content</h3>
    <button @click="hide" type="button" class="btn">Close</button>
</div>
EOCONTENT
    );
echo $modal->getShowJs();
?>

<?= /** @noEscape */ $modal ?>
```

## Module Compatibility

### Making Modules Hyva-Compatible

1. **Create a compatibility module** or add Hyva templates alongside Luma templates
2. **Register CSS sources** via `hyva-modules` npm integration
3. **Replace KnockoutJS/RequireJS** with Alpine.js components
4. **Use ViewModels** instead of Block methods
5. **Add `etc/hyva-libraries.json`** if your module needs specific Alpine version

### Fallback to Luma

The `hyva-themes/magento2-compat-module-fallback` package provides Luma fallback for pages that lack Hyva templates (e.g., third-party checkout modules).

## Template Patterns

### Escaping (Same as Luma)

```php
<?= $escaper->escapeHtml($text) ?>
<?= $escaper->escapeUrl($url) ?>
<?= $escaper->escapeHtmlAttr($attribute) ?>
<?= $escaper->escapeJs($jsString) ?>
<?= /* @noEscape */ $safeHtml ?>
```

### Typical Template Header

```php
<?php
declare(strict_types=1);

use Hyva\Theme\Model\ViewModelRegistry;
use Hyva\Theme\ViewModel\LucideIcons;
use Hyva\Theme\ViewModel\ProductPage;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;

/** @var Template $block */
/** @var Escaper $escaper */
/** @var ViewModelRegistry $viewModels */

$productPage = $viewModels->require(ProductPage::class);
$lucideIcons = $viewModels->require(LucideIcons::class);
?>
```

## Layout XML

Hyva uses standard Magento layout XML. The `hyva-themes/magento2-base-layout-reset` module removes Luma-specific layout handles. Custom layout handles can be added via the `hyva_` prefix convention.

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceContainer name="content">
            <block class="Magento\Framework\View\Element\Template"
                   name="custom.block"
                   template="Vendor_Module::custom.phtml">
                <arguments>
                    <argument name="view_model" xsi:type="object">
                        Vendor\Module\ViewModel\CustomData
                    </argument>
                </arguments>
            </block>
        </referenceContainer>
    </body>
</page>
```

## Performance Optimization

### Hyva-Specific Optimizations

1. **Tailwind CSS purging** - Only used classes are included in `styles.css`
2. **No RequireJS overhead** - Alpine.js is loaded as a single script
3. **x-defer="intersect"** - Lazy-initialize Alpine components on scroll
4. **x-cloak** - Prevent FOUC (Flash of Unstyled Content) on Alpine components
5. **GraphQL batching** - Combine related queries where possible
6. **SVG icons** - No icon font HTTP requests
7. **No jQuery** - Eliminated as a dependency

### Core Web Vitals Checklist

- LCP: Use proper image sizing in `etc/view.xml`
- CLS: Set explicit dimensions on images and containers
- INP: Keep Alpine.js handlers lightweight; use `x-defer` for below-fold components

## Best Practices

1. **Use ViewModels** over Block classes for data access
2. **Minimize PHP in templates** - Move logic to ViewModels
3. **Use Alpine.js CSP patterns** - Register all components with `Alpine.data()`
4. **Use `x-cloak`** on initially-hidden Alpine content to prevent flicker
5. **Leverage GraphQL** for dynamic content (cart, customer, checkout)
6. **Run `npm run build`** before deployment to generate optimized CSS
7. **Check module compatibility** before installing third-party extensions
8. **Test Core Web Vitals** after every frontend change
9. **Use Lucide/Heroicons ViewModels** instead of custom icon implementations
10. **Register inline scripts** with `HyvaCsp::registerInlineScript()` in CSP theme
11. **Use `private-content-loaded` event** for customer/cart data, not Luma's customer-data
12. **Customize design tokens** in `hyva.config.json` rather than hardcoding colors
