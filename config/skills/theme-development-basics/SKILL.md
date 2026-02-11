# Theme Development: Structure & Layout

> Related: See [theme-development-styling](../theme-development-styling/SKILL.md) for LESS styling, JavaScript, responsive design, and deployment.

## Overview

Magento 2 themes control the visual presentation of the storefront and admin areas. This skill covers creating custom themes, extending parent themes, and implementing responsive designs following Magento's theming architecture.

## Theme Types

| Type | Description | Use Case |
|------|-------------|----------|
| Custom Theme | Complete theme implementation | New brand design |
| Child Theme | Extends a parent theme | Modifications to Luma/Blank |
| Admin Theme | Customizes admin panel | Backend branding |

## Theme Structure

```
app/design/frontend/Vendor/themename/
├── etc/
│   └── view.xml                    # Image configuration
├── media/
│   └── preview.jpg                 # Theme preview image
├── web/
│   ├── css/
│   │   └── source/
│   │       ├── _extend.less        # Extend parent styles
│   │       ├── _theme.less         # Theme variables
│   │       └── _module.less        # Custom styles
│   ├── fonts/                      # Custom fonts
│   ├── images/                     # Theme images
│   └── js/                         # Custom JavaScript
├── Magento_Theme/
│   ├── layout/
│   │   └── default.xml             # Layout overrides
│   └── templates/
│       └── html/
│           └── header.phtml        # Template overrides
├── registration.php
├── theme.xml
└── composer.json
```

## Creating a Theme

### 1. Theme Registration (registration.php)

```php
<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/Vendor/themename',
    __DIR__
);
```

### 2. Theme Declaration (theme.xml)

```xml
<?xml version="1.0"?>
<theme xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="urn:magento:framework:Config/etc/theme.xsd">
    <title>Vendor Theme Name</title>
    <parent>Magento/luma</parent>
    <media>
        <preview_image>media/preview.jpg</preview_image>
    </media>
</theme>
```

### 3. Composer Definition (composer.json)

```json
{
    "name": "vendor/theme-frontend-themename",
    "description": "Custom Magento 2 Theme",
    "type": "magento2-theme",
    "version": "1.0.0",
    "license": "proprietary",
    "autoload": {
        "files": [
            "registration.php"
        ]
    }
}
```

## Parent Theme Inheritance

| Parent | Features | Recommended For |
|--------|----------|-----------------|
| Magento/blank | Minimal styling, grid system | Full custom designs |
| Magento/luma | Complete storefront styling | Quick customizations |

### Inheritance Chain

```
Magento/blank (base)
    └── Magento/luma (extends blank)
        └── Vendor/themename (extends luma)
```

## Layout XML Customization

### Extending Layouts (default.xml)

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Move block to different container -->
        <move element="logo" destination="header.panel" after="-"/>

        <!-- Remove unwanted block -->
        <referenceBlock name="catalog.compare.sidebar" remove="true"/>

        <!-- Add CSS class to container -->
        <referenceContainer name="header.container">
            <container name="custom.header" htmlTag="div" htmlClass="custom-header-wrapper"/>
        </referenceContainer>

        <!-- Add new block -->
        <referenceContainer name="footer-container">
            <block class="Magento\Framework\View\Element\Template"
                   name="custom.footer.block"
                   template="Magento_Theme::html/custom-footer.phtml"/>
        </referenceContainer>
    </body>
</page>
```

### Page-Specific Layouts

```xml
<!-- Magento_Catalog/layout/catalog_product_view.xml -->
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Product page customizations -->
        <referenceContainer name="product.info.main">
            <block class="Magento\Framework\View\Element\Template"
                   name="custom.product.badge"
                   template="Magento_Catalog::product/badge.phtml"
                   before="product.info.price"/>
        </referenceContainer>
    </body>
</page>
```

## Template Overrides

### Override Strategy

To override a template from a module:
1. Create matching directory structure in your theme
2. Copy and modify the template file

**Original:** `vendor/magento/module-catalog/view/frontend/templates/product/list.phtml`
**Override:** `app/design/frontend/Vendor/themename/Magento_Catalog/templates/product/list.phtml`

### Template Example

```php
<?php
/**
 * Custom product list template
 *
 * @var \Magento\Catalog\Block\Product\ListProduct $block
 */
declare(strict_types=1);

$_productCollection = $block->getLoadedProductCollection();
$_helper = $block->getData('outputHelper');
?>
<?php if (!$_productCollection->count()): ?>
    <div class="message info empty">
        <div><?= $block->escapeHtml(__('We can\'t find products matching the selection.')) ?></div>
    </div>
<?php else: ?>
    <div class="products wrapper grid products-grid">
        <ol class="products list items product-items">
            <?php foreach ($_productCollection as $_product): ?>
                <li class="item product product-item">
                    <div class="product-item-info">
                        <?= $block->getProductDetailsHtml($_product) ?>

                        <strong class="product name product-item-name">
                            <a href="<?= $block->escapeUrl($block->getProductUrl($_product)) ?>"
                               class="product-item-link">
                                <?= $block->escapeHtml($_product->getName()) ?>
                            </a>
                        </strong>

                        <?= $block->getProductPrice($_product) ?>

                        <?= $block->getProductDetailsHtml($_product) ?>

                        <div class="product-item-actions">
                            <?= $block->getAddToCartHtml($_product) ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
<?php endif; ?>
```
