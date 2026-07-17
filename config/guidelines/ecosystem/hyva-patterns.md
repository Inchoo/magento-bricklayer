# Hyvä Theme Patterns & ViewModels

> Related: See [hyva-architecture.md](hyva-architecture.md) for architecture overview, Tailwind CSS, and Alpine.js component patterns.

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
    /**
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     */
    public function __construct(
        private readonly \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return string
     */
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

<?= /* @noEscape */ $modal ?>
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
