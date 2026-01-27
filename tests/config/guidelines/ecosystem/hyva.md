# Hyvä Theme Guidelines

## Overview

Hyvä is a modern, performance-focused Magento 2 frontend theme that replaces the default Luma theme's complex UI components with Alpine.js and Tailwind CSS.

## Key Differences from Luma

| Aspect | Luma | Hyvä |
|--------|------|------|
| JavaScript | RequireJS + KnockoutJS | Alpine.js |
| CSS | LESS | Tailwind CSS |
| Page Speed | 30-50 (mobile) | 90+ (mobile) |
| Bundle Size | 2MB+ | ~200KB |
| Complexity | High | Low |

## Architecture

```
app/design/frontend/Vendor/hyva-theme/
├── Magento_Theme/
│   ├── layout/
│   │   └── default.xml
│   └── templates/
│       └── root.phtml
├── Magento_Catalog/
│   └── templates/
│       └── product/
├── web/
│   ├── css/
│   │   └── styles.css
│   └── tailwind/
│       ├── tailwind-source.css
│       └── tailwind.config.js
├── theme.xml
└── registration.php
```

## Alpine.js Components

### Basic Component

```html
<div x-data="initProductView()">
    <div x-show="isOpen" x-transition>
        Content
    </div>
    <button @click="toggle()">Toggle</button>
</div>

<script>
function initProductView() {
    return {
        isOpen: false,
        toggle() {
            this.isOpen = !this.isOpen;
        }
    }
}
</script>
```

### With PHP Data

```php
<div x-data="initAddToCart(<?= /* @noEscape */ $block->getAddToCartData() ?>)">
    <form @submit.prevent="addToCart">
        <input type="number" x-model="qty" min="1">
        <button type="submit" :disabled="isLoading">
            <span x-show="!isLoading">Add to Cart</span>
            <span x-show="isLoading">Adding...</span>
        </button>
    </form>
</div>
```

### Event Communication

```javascript
// Dispatch event
window.dispatchEvent(new CustomEvent('toggle-cart', {
    detail: { open: true }
}));

// Listen for event
<div @toggle-cart.window="isOpen = $event.detail.open">
```

## Tailwind CSS

### Configuration

```javascript
// tailwind.config.js
module.exports = {
    content: [
        '../../**/*.phtml',
        '../../../*/templates/**/*.phtml',
        './web/tailwind/*.css'
    ],
    theme: {
        extend: {
            colors: {
                primary: '#1a1a1a',
                secondary: '#666666',
            },
            fontFamily: {
                sans: ['Inter', 'sans-serif'],
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
}
```

### Build Process

```bash
# Development with watch
npm run watch

# Production build
npm run build-prod
```

## ViewModels

Hyvä uses ViewModels extensively for data access:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class ProductData implements ArgumentInterface
{
    public function __construct(
        private readonly \Magento\Catalog\Helper\Data $catalogHelper
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

Usage in layout:

```xml
<block name="product.data" class="Magento\Framework\View\Element\Template">
    <arguments>
        <argument name="view_model" xsi:type="object">
            Vendor\Module\ViewModel\ProductData
        </argument>
    </arguments>
</block>
```

## Compatibility Modules

Hyvä provides compatibility modules for common extensions:

- `hyva-themes/magento2-compat-module-fallback` - Luma fallback
- `hyva-themes/magento2-default-theme` - Default implementation
- `hyva-themes/magento2-graphql-*` - GraphQL integrations

## GraphQL Integration

Hyvä prefers GraphQL for dynamic data:

```javascript
async function fetchProductData(sku) {
    const query = `
        query getProduct($sku: String!) {
            products(filter: { sku: { eq: $sku } }) {
                items {
                    id
                    name
                    price_range {
                        minimum_price {
                            final_price {
                                value
                                currency
                            }
                        }
                    }
                }
            }
        }
    `;

    const response = await fetch('/graphql', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query, variables: { sku } })
    });

    return response.json();
}
```

## Best Practices

1. **Use ViewModels** - Not blocks for data
2. **Minimize PHP in templates** - Move logic to ViewModels
3. **Use Alpine.js x-cloak** - Prevent flicker on load
4. **Purge Tailwind** - Remove unused CSS
5. **Leverage GraphQL** - For dynamic content
6. **Test Core Web Vitals** - Maintain performance gains
7. **Check compatibility** - Before using third-party modules
