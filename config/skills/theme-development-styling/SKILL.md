# Theme Development: Styling & JavaScript

> Related: See [theme-development-basics](../theme-development-basics/SKILL.md) for theme structure, creation, layout XML, and template overrides.

## LESS/CSS Styling

### Theme Variables (_theme.less)

```less
// Typography
@font-family__base: 'Open Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
@font-size__base: 14px;
@line-height__base: 1.5;

// Colors
@color-primary: #1979c3;
@color-secondary: #006bb4;
@color-text__primary: #333333;
@color-text__secondary: #666666;
@color-background: #ffffff;

// Layout
@layout__max-width: 1280px;
@layout-indent__width: 20px;

// Buttons
@button__background: @color-primary;
@button__color: #ffffff;
@button__border-radius: 3px;

// Header
@header__background-color: #ffffff;
@header-icons-color: #333333;
```

### Extending Styles (_extend.less)

```less
// Import theme variables
@import '_theme.less';

// Header customization
.page-header {
    background-color: @header__background-color;
    border-bottom: 1px solid #e0e0e0;

    .header.content {
        padding: 20px @layout-indent__width;
    }
}

// Custom navigation
.navigation {
    background: @color-primary;

    .level0 > .level-top {
        color: #ffffff;
        padding: 15px 20px;

        &:hover {
            background: darken(@color-primary, 10%);
        }
    }
}

// Product listing
.products-grid {
    .product-item {
        margin-bottom: 30px;

        .product-item-info {
            border: 1px solid #e0e0e0;
            padding: 15px;
            transition: box-shadow 0.3s ease;

            &:hover {
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
        }

        .product-item-name {
            font-size: 16px;
            margin: 10px 0;
        }
    }
}

// Custom buttons
.action.primary {
    .lib-button-primary();
    border-radius: @button__border-radius;

    &:hover {
        background: darken(@button__background, 10%);
    }
}
```

### Module-Specific Styles (_module.less)

```less
// Custom module styles
.custom-module {
    &-container {
        max-width: @layout__max-width;
        margin: 0 auto;
        padding: @layout-indent__width;
    }

    &-title {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 20px;
    }

    &-content {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
}
```

## Image Configuration (etc/view.xml)

```xml
<?xml version="1.0"?>
<view xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:Config/etc/view.xsd">
    <media>
        <images module="Magento_Catalog">
            <!-- Product listing thumbnail -->
            <image id="category_page_grid" type="small_image">
                <width>240</width>
                <height>300</height>
            </image>

            <!-- Product page main image -->
            <image id="product_page_image_medium" type="image">
                <width>700</width>
                <height>700</height>
            </image>

            <!-- Product page thumbnails -->
            <image id="product_page_image_small" type="thumbnail">
                <width>88</width>
                <height>110</height>
            </image>

            <!-- Cart item thumbnail -->
            <image id="cart_page_product_thumbnail" type="small_image">
                <width>165</width>
                <height>165</height>
            </image>
        </images>
    </media>

    <vars module="Magento_Catalog">
        <var name="gallery_nav">thumbs</var>
        <var name="gallery_allowfullscreen">true</var>
    </vars>
</view>
```

## JavaScript Customization

### RequireJS Configuration (requirejs-config.js)

```javascript
var config = {
    map: {
        '*': {
            'customSlider': 'js/custom-slider'
        }
    },
    paths: {
        'slick': 'js/vendor/slick.min'
    },
    shim: {
        'slick': {
            deps: ['jquery']
        }
    },
    config: {
        mixins: {
            'Magento_Catalog/js/catalog-add-to-cart': {
                'js/catalog-add-to-cart-mixin': true
            }
        }
    }
};
```

### Custom JavaScript Module (web/js/custom-slider.js)

```javascript
define([
    'jquery',
    'slick'
], function ($) {
    'use strict';

    return function (config, element) {
        var defaults = {
            dots: true,
            infinite: true,
            speed: 500,
            slidesToShow: 4,
            slidesToScroll: 1,
            responsive: [
                {
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 3
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 1
                    }
                }
            ]
        };

        var options = $.extend({}, defaults, config);
        $(element).slick(options);
    };
});
```

### JavaScript Mixin (web/js/catalog-add-to-cart-mixin.js)

```javascript
define([
    'jquery'
], function ($) {
    'use strict';

    return function (widget) {
        $.widget('mage.catalogAddToCart', widget, {
            submitForm: function (form) {
                // Custom logic before add to cart
                console.log('Adding product to cart...');

                // Call parent method
                this._super(form);

                // Custom logic after submit
                this._showCustomNotification();
            },

            _showCustomNotification: function () {
                // Custom notification logic
            }
        });

        return $.mage.catalogAddToCart;
    };
});
```

## Responsive Design

### Breakpoints

| Breakpoint | Width | Target |
|------------|-------|--------|
| Mobile | < 768px | Smartphones |
| Tablet | 768px - 1024px | Tablets |
| Desktop | > 1024px | Desktop browsers |

### Media Queries in LESS

```less
// Mobile-first approach
.page-header {
    padding: 10px;

    // Tablet and up
    @media (min-width: @screen__m) {
        padding: 15px;
    }

    // Desktop
    @media (min-width: @screen__l) {
        padding: 20px;
    }
}

// Using Magento's media mixin
.custom-component {
    .lib-css(display, flex, @screen__m);

    .media-width(@extremum, @break) when (@extremum = 'min') and (@break = @screen__m) {
        flex-direction: row;
    }

    .media-width(@extremum, @break) when (@extremum = 'max') and (@break = @screen__m) {
        flex-direction: column;
    }
}
```

## Static Content Deployment

### Development Mode

```bash
# Clean generated files
bin/magento cache:clean
rm -rf var/view_preprocessed/* pub/static/frontend/*

# Compile LESS on the fly (default in developer mode)
```

### Production Mode

```bash
# Deploy static content for specific theme and locale
bin/magento setup:static-content:deploy en_US -t Vendor/themename

# Deploy for multiple locales
bin/magento setup:static-content:deploy en_US de_DE -t Vendor/themename

# Force deployment in developer mode
bin/magento setup:static-content:deploy -f
```

## Theme Configuration in Admin

1. Navigate to **Content > Design > Configuration**
2. Select store view
3. Choose theme from **Applied Theme** dropdown
4. Configure theme-specific options
5. Save and clear cache

## Best Practices

1. **Always extend, rarely override** - Use `_extend.less` instead of copying entire files
2. **Use LESS variables** - Define colors, fonts, and sizes as variables for consistency
3. **Follow the fallback system** - Let Magento handle missing files via parent themes
4. **Optimize images** - Use appropriate sizes and formats for web
5. **Test responsive behavior** - Verify layouts on multiple screen sizes
6. **Minimize JavaScript** - Use mixins instead of overriding entire JS files
7. **Document customizations** - Comment complex layout changes
8. **Use semantic HTML** - Maintain accessibility standards
9. **Leverage browser caching** - Configure proper cache headers for static assets
10. **Profile performance** - Use browser dev tools to identify slow resources

## Common Customization Patterns

### Adding a Custom Font

```less
// web/css/source/_fonts.less
@font-face {
    font-family: 'CustomFont';
    src: url('../fonts/CustomFont.woff2') format('woff2'),
         url('../fonts/CustomFont.woff') format('woff');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}

// Apply in _theme.less
@font-family__base: 'CustomFont', sans-serif;
```

### Custom Logo

```xml
<!-- Magento_Theme/layout/default.xml -->
<referenceBlock name="logo">
    <arguments>
        <argument name="logo_file" xsi:type="string">images/custom-logo.svg</argument>
        <argument name="logo_width" xsi:type="number">200</argument>
        <argument name="logo_height" xsi:type="number">50</argument>
    </arguments>
</referenceBlock>
```

### Adding Schema.org Markup

```php
<!-- Magento_Theme/templates/html/header.phtml -->
<header class="page-header" itemscope itemtype="http://schema.org/WPHeader">
    <?= $block->getChildHtml('header.panel') ?>
    <div class="header content">
        <?= $block->getChildHtml() ?>
    </div>
</header>
```
