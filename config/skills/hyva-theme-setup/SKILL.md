# Hyvä Theme: Setup & Alpine.js Components

> Related: See [hyva-theme-components](../hyva-theme-components/SKILL.md) for ViewModels, module compatibility, Tailwind customization, modals, and deployment.

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
