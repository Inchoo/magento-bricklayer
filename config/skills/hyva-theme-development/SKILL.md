# Hyva Theme Development Skill

## Overview

Hyva is a modern Magento 2 frontend theme built on Alpine.js and Tailwind CSS, replacing Luma's RequireJS/KnockoutJS/jQuery/LESS stack. This skill covers creating child themes, building Hyva-compatible modules, writing Alpine.js CSP components, and customizing the Tailwind CSS build.

## When to Use This Skill

| Scenario | Use This Skill? |
|----------|-----------------|
| Creating a new storefront theme | Yes (child of Hyva/default-csp) |
| Adding frontend features to Hyva store | Yes |
| Making a module Hyva-compatible | Yes |
| Customizing product pages, category pages | Yes |
| Working with Alpine.js components | Yes |
| Tailwind CSS customization | Yes |
| Admin panel development | No (use ui-component-development) |
| Luma/Blank theme work | No (use theme-development) |

## Creating a Hyva Child Theme

### Required Files

#### 1. registration.php

```php
<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/Vendor/hyva-child',
    __DIR__
);
```

#### 2. theme.xml

```xml
<?xml version="1.0"?>
<theme xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamepaceSchemaLocation="urn:magento:framework:Config/etc/theme.xsd">
    <title>Vendor Hyva Child</title>
    <parent>Hyva/default-csp</parent>
    <media>
        <preview_image>media/preview.png</preview_image>
    </media>
</theme>
```

#### 3. composer.json

```json
{
    "name": "vendor/theme-frontend-hyva-child",
    "description": "Custom Hyva child theme",
    "type": "magento2-theme",
    "require": {
        "hyva-themes/magento2-default-theme-csp": "^3.0"
    },
    "autoload": {
        "files": ["registration.php"]
    }
}
```

#### 4. etc/hyva-libraries.json

```json
{
    "alpine": "3"
}
```

### Tailwind Setup for Child Theme

Copy the `web/tailwind/` directory from the parent theme and customize:

```bash
# In your child theme directory
cp -r vendor/hyva-themes/magento2-default-theme-csp/web/tailwind web/tailwind
cd web/tailwind
npm install
```

Update `hyva.config.json` to include parent theme sources:

```json
{
    "tailwind": {
        "include": [
            {
                "src": "vendor/hyva-themes/magento2-default-theme-csp"
            }
        ]
    },
    "tokens": {
        "values": {
            "color": {
                "primary": {
                    "DEFAULT": "oklch(55% 0.2 240)"
                }
            }
        }
    }
}
```

Update `tailwind-source.css` to include parent theme sources if needed:

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
    --color-bg: var(--color-white);
    --color-fg: var(--color-gray-900);
    --color-surface: var(--color-white);
}
```

## Alpine.js CSP Components

### Basic Component Pattern

All components in CSP theme must be registered via `Alpine.data()`:

```php
<?php

declare(strict_types=1);

use Hyva\Theme\Model\ViewModelRegistry;
use Hyva\Theme\ViewModel\HyvaCsp;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;

/** @var Template $block */
/** @var Escaper $escaper */
/** @var ViewModelRegistry $viewModels */

/** @var HyvaCsp $hyvaCsp */
$hyvaCsp = $viewModels->require(HyvaCsp::class);
?>

<div x-data="initAccordion"
     data-initial-open="<?= $escaper->escapeHtmlAttr('true') ?>">
    <button type="button" @click="toggle" class="btn">
        <span x-text="isOpen ? 'Close' : 'Open'"></span>
    </button>
    <div x-show="isOpen" x-transition x-cloak>
        <?= $escaper->escapeHtml(__('Accordion content')) ?>
    </div>
</div>

<script>
    function initAccordion() {
        return {
            isOpen: false,
            init() {
                this.isOpen = this.$root.dataset.initialOpen === 'true';
            },
            toggle() {
                this.isOpen = !this.isOpen;
            }
        };
    }

    window.addEventListener(
        'alpine:init',
        () => Alpine.data('initAccordion', initAccordion),
        {once: true}
    );
</script>
<?php $hyvaCsp->registerInlineScript() ?>
```

### Component with PHP Data

```php
<?php
$productData = [
    'id' => (int) $product->getId(),
    'name' => $product->getName(),
    'price' => $product->getFinalPrice(),
    'qty' => 1,
];
?>

<div x-data="initAddToCart"
     data-product="<?= $escaper->escapeHtmlAttr(json_encode($productData)) ?>">
    <form @submit.prevent="submitForm">
        <label for="qty"><?= $escaper->escapeHtml(__('Qty')) ?></label>
        <input type="number"
               id="qty"
               min="1"
               :value="qty"
               @input="updateQty">
        <button type="submit" class="btn btn-primary" :disabled="isLoading">
            <span x-show="!isLoading"><?= $escaper->escapeHtml(__('Add to Cart')) ?></span>
            <span x-show="isLoading" x-cloak><?= $escaper->escapeHtml(__('Adding...')) ?></span>
        </button>
    </form>
</div>

<script>
    function initAddToCart() {
        return {
            product: {},
            qty: 1,
            isLoading: false,
            init() {
                this.product = JSON.parse(this.$root.dataset.product);
                this.qty = this.product.qty;
            },
            updateQty() {
                this.qty = parseInt(this.$event.target.value) || 1;
            },
            async submitForm() {
                this.isLoading = true;
                try {
                    const formData = new FormData(this.$root.querySelector('form'));
                    formData.append('product', this.product.id);
                    formData.append('qty', this.qty);
                    // Submit via fetch...
                } finally {
                    this.isLoading = false;
                }
            }
        };
    }

    window.addEventListener(
        'alpine:init',
        () => Alpine.data('initAddToCart', initAddToCart),
        {once: true}
    );
</script>
<?php $hyvaCsp->registerInlineScript() ?>
```

### Component with Private Content

```php
<div x-data="initMiniCart"
     @private-content-loaded.window="onSectionDataLoaded">
    <button @click="toggleCart" class="relative">
        <?= $lucideIcons->shoppingCartHtml('', 24, 24, ['aria-hidden' => 'true']) ?>
        <span x-show="itemCount > 0"
              x-text="itemCount"
              x-cloak
              class="absolute -top-2 -right-2 bg-primary text-on-primary rounded-full text-xs w-5 h-5 flex items-center justify-center">
        </span>
    </button>
</div>

<script>
    function initMiniCart() {
        return {
            isOpen: false,
            itemCount: 0,
            onSectionDataLoaded() {
                const cartData = this.$event.detail.data.cart;
                if (cartData) {
                    this.itemCount = cartData.summary_count || 0;
                }
            },
            toggleCart() {
                this.isOpen = !this.isOpen;
                window.dispatchEvent(new CustomEvent('toggle-cart', {
                    detail: { open: this.isOpen }
                }));
            }
        };
    }

    window.addEventListener(
        'alpine:init',
        () => Alpine.data('initMiniCart', initMiniCart),
        {once: true}
    );
</script>
<?php $hyvaCsp->registerInlineScript() ?>
```

## ViewModels

### Using Built-in ViewModels

```php
<?php
use Hyva\Theme\Model\ViewModelRegistry;
use Hyva\Theme\ViewModel\CurrentProduct;
use Hyva\Theme\ViewModel\LucideIcons;
use Hyva\Theme\ViewModel\ProductPage;
use Hyva\Theme\ViewModel\StoreConfig;
use Hyva\Theme\ViewModel\ProductCompare;
use Hyva\Theme\ViewModel\Wishlist;

/** @var ViewModelRegistry $viewModels */

// Product data
$currentProduct = $viewModels->require(CurrentProduct::class);
$product = $currentProduct->get();

// Product page helpers
$productPage = $viewModels->require(ProductPage::class);
$addToCartUrl = $productPage->getAddToCartUrl($product);
$shortDescription = $productPage->getShortDescriptionForProduct($product);

// Icons
$icons = $viewModels->require(LucideIcons::class);
echo $icons->heartHtml('text-red-500', 20, 20);

// Store config
$storeConfig = $viewModels->require(StoreConfig::class);
$storeName = $storeConfig->getStoreConfig('general/store_information/name');

// Feature checks
$wishlistEnabled = $viewModels->require(Wishlist::class)->isEnabled();
$compareEnabled = $viewModels->require(ProductCompare::class)->showInProductList();
?>
```

### Creating a Custom ViewModel

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class FeaturedProducts implements ArgumentInterface
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @param int $limit
     * @return array
     */
    public function getFeaturedProducts(int $limit = 8): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('is_featured', 1)
            ->addFilter('status', 1)
            ->setPageSize($limit)
            ->create();

        return $this->productRepository->getList($searchCriteria)->getItems();
    }

    /**
     * @param array $products
     * @return string
     */
    public function getProductsJsonConfig(array $products): string
    {
        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => (int) $product->getId(),
                'name' => $product->getName(),
                'sku' => $product->getSku(),
            ];
        }
        return json_encode($data);
    }
}
```

Register via layout XML argument or use directly:
```php
$featuredProducts = $viewModels->require(\Vendor\Module\ViewModel\FeaturedProducts::class);
```

## Making a Module Hyva-Compatible

### Module Structure for Hyva Compatibility

```
app/code/Vendor/Module/
├── etc/
│   └── module.xml
├── view/
│   └── frontend/
│       ├── layout/
│       │   └── vendor_module_index_index.xml
│       └── templates/
│           └── page.phtml                    # Works with both Luma and Hyva
├── ViewModel/
│   └── PageData.php                          # Use ViewModels for data
└── registration.php
```

### Separate Hyva Compatibility Module (Recommended)

```
app/code/Vendor/HyvaModuleCompat/
├── etc/
│   ├── module.xml
│   └── hyva-libraries.json                    # {"alpine": "3"}
├── view/
│   └── frontend/
│       ├── layout/
│       │   └── vendor_module_index_index.xml  # Hyva-specific layout
│       └── templates/
│           └── page.phtml                     # Alpine.js + Tailwind template
├── registration.php
└── composer.json
```

The compatibility module's `module.xml` should declare a sequence dependency:

```xml
<module name="Vendor_HyvaModuleCompat" setup_version="1.0.0">
    <sequence>
        <module name="Vendor_Module"/>
        <module name="Hyva_Theme"/>
    </sequence>
</module>
```

### Registering Module CSS Sources

If your module includes Tailwind CSS classes that need to be scanned, register the module in `hyva-modules`:

1. Add `@hyva-themes/hyva-modules` configuration in the module's `package.json`
2. Or manually add `@source` directives pointing to your module's template paths

## Tailwind CSS Customization

### Adding Custom Component Styles

Create or modify `web/tailwind/components/custom.css`:

```css
/* Custom button variants */
.btn-outline {
    @apply border-2 border-current bg-transparent hover:bg-current hover:text-white transition-colors;
}

.btn-ghost {
    @apply bg-transparent hover:bg-gray-100 transition-colors;
}

/* Custom card styles */
.card {
    @apply bg-surface rounded-lg shadow-sm;
}

.card-interactive {
    @apply card transition-shadow hover:shadow-md;
}
```

Import in `web/tailwind/components/index.css`:

```css
@import "./button.css";
@import "./card.css";
@import "./forms.css";
@import "./custom.css";
```

### Customizing Design Tokens

Edit `hyva.config.json`:

```json
{
    "tokens": {
        "values": {
            "color": {
                "primary": {
                    "lighter": "oklch(60% 0.18 250)",
                    "DEFAULT": "oklch(50% 0.22 250)",
                    "darker": "oklch(35% 0.22 250)"
                }
            },
            "form": {
                "radius": "var(--radius-md)",
                "stroke": "var(--color-gray-300)",
                "active-color": "var(--color-primary)"
            }
        }
    }
}
```

Then regenerate: `npm run generate`

### Adding Custom @theme Variables

In `tailwind-source.css`:

```css
@theme {
    --color-bg: var(--color-white);
    --color-fg: var(--color-gray-900);
    --color-surface: var(--color-white);
    --color-accent: var(--color-amber-500);
    --font-display: 'Playfair Display', serif;
}
```

## Modal Dialogs

### Using the Modal ViewModel

```php
<?php
$modal = $viewModels->require(\Hyva\Theme\ViewModel\Modal::class);

$sizeGuideModal = $modal->createModal()
    ->withDialogRefName('size-guide')
    ->withContent(<<<'EOCONTENT'
<div class="p-6">
    <h2 class="text-xl font-bold mb-4">Size Guide</h2>
    <table class="w-full">
        <thead>
            <tr>
                <th class="p-2 text-left">Size</th>
                <th class="p-2 text-left">Chest</th>
                <th class="p-2 text-left">Waist</th>
            </tr>
        </thead>
        <tbody>
            <tr><td class="p-2">S</td><td class="p-2">36"</td><td class="p-2">30"</td></tr>
            <tr><td class="p-2">M</td><td class="p-2">38"</td><td class="p-2">32"</td></tr>
            <tr><td class="p-2">L</td><td class="p-2">40"</td><td class="p-2">34"</td></tr>
        </tbody>
    </table>
    <button @click="hide" type="button" class="btn mt-4">Close</button>
</div>
EOCONTENT
    );
?>

<button type="button" class="text-primary underline" @click="<?= $sizeGuideModal->getShowJs() ?>">
    <?= $escaper->escapeHtml(__('Size Guide')) ?>
</button>

<?= /** @noEscape */ $sizeGuideModal ?>
```

## Template Override Patterns

### Overriding a Parent Theme Template

Place the override in your child theme at the matching path:

**Parent:** `vendor/hyva-themes/magento2-default-theme-csp/Magento_Catalog/templates/product/view/addtocart.phtml`
**Override:** `app/design/frontend/Vendor/hyva-child/Magento_Catalog/templates/product/view/addtocart.phtml`

### Overriding a Module Template for Hyva

**Module original:** `vendor/vendor/module/view/frontend/templates/page.phtml`
**Hyva override:** `app/design/frontend/Vendor/hyva-child/Vendor_Module/templates/page.phtml`

## Common Patterns

### Product List Item with Tailwind

```php
<div class="card card-interactive flex flex-col">
    <a href="<?= $escaper->escapeUrl($product->getProductUrl()) ?>" class="block">
        <?= $blockImage->toHtml() ?>
    </a>
    <div class="p-4 flex flex-col grow">
        <a href="<?= $escaper->escapeUrl($product->getProductUrl()) ?>"
           class="font-semibold text-lg hover:text-primary">
            <?= $escaper->escapeHtml($product->getName()) ?>
        </a>
        <div class="mt-auto pt-3">
            <?= /* @noEscape */ $productListItemViewModel->getProductPriceHtml($product) ?>
        </div>
        <button class="btn btn-primary mt-3" data-addto="cart">
            <?= $lucideIcons->shoppingCartHtml('', 20, 20, ['aria-hidden' => 'true']) ?>
            <span><?= $escaper->escapeHtml(__('Add to Cart')) ?></span>
        </button>
    </div>
</div>
```

### Responsive Grid Layout

```html
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    <?php foreach ($products as $product): ?>
        <!-- product card -->
    <?php endforeach; ?>
</div>
```

## Build & Deployment

```bash
# Development
cd app/design/frontend/Vendor/hyva-child/web/tailwind
npm install
npm run watch          # Watches for changes, rebuilds CSS

# Production
npm run build          # Minified CSS output

# After Tailwind build
bin/magento cache:clean
bin/magento setup:static-content:deploy -f   # Development
bin/magento setup:static-content:deploy      # Production
```

## Debugging Tips

1. **Alpine component not initializing?** Check browser console for CSP violations or Alpine.data() registration errors
2. **Tailwind classes not applying?** Run `npm run build` and verify `@source` paths include your templates
3. **ViewModels not found?** Ensure `Hyva_Theme` module is enabled and DI is compiled
4. **Private content not loading?** Check that the `private-content-loaded` event listener is registered correctly
5. **Icons missing?** Verify you're using `LucideIcons` (CSP theme) not `HeroiconsOutline` (legacy theme)

## Best Practices

1. **Always use CSP patterns** - Register components with `Alpine.data()`, avoid inline expressions
2. **Use ViewModels for data** - Never put complex logic in templates
3. **Customize via design tokens** - Change `hyva.config.json` before writing custom CSS
4. **Test with strict CSP headers** - Ensure no `unsafe-eval` or `unsafe-inline` is needed
5. **Keep Alpine components small** - One component per concern
6. **Use `x-defer="intersect"`** for below-fold interactivity
7. **Use `x-cloak`** to prevent FOUC on dynamically shown content
8. **Always escape output** - Use `$escaper` methods in all templates
9. **Register inline scripts** with `$hyvaCsp->registerInlineScript()` after each `<script>` tag
10. **Run Tailwind build** before committing - Generated `styles.css` should be up to date
