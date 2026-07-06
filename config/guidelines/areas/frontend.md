# Magento 2 Frontend Development Guidelines

## Overview

The frontend area handles storefront rendering, customer-facing pages, and the checkout experience. Understanding layouts, blocks, templates, and JavaScript is essential.

## Area Code

```php
\Magento\Framework\App\Area::AREA_FRONTEND // 'frontend'
```

## Directory Structure

```
app/code/Vendor/Module/
├── view/
│   └── frontend/
│       ├── layout/
│       │   ├── default.xml
│       │   └── catalog_product_view.xml
│       ├── templates/
│       │   └── product/
│       │       └── view.phtml
│       ├── web/
│       │   ├── css/
│       │   ├── js/
│       │   └── images/
│       └── requirejs-config.js
```

## Layout XML

### Basic Structure

```xml
<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">

    <head>
        <title>Page Title</title>
        <css src="Vendor_Module::css/custom.css"/>
        <script src="Vendor_Module::js/custom.js"/>
    </head>

    <body>
        <referenceContainer name="content">
            <block class="Vendor\Module\Block\Custom"
                   name="vendor.module.custom"
                   template="Vendor_Module::custom.phtml"
                   cacheable="true"/>
        </referenceContainer>
    </body>

</page>
```

### Layout Handles

Common layout handles:
- `default.xml` - Applied to all pages
- `catalog_product_view.xml` - Product detail page
- `catalog_category_view.xml` - Category page
- `checkout_index_index.xml` - Checkout page
- `customer_account.xml` - Customer account pages

### Container Operations

```xml
<!-- Add block to container -->
<referenceContainer name="content">
    <block class="..." name="..." template="..."/>
</referenceContainer>

<!-- Move block -->
<move element="block.name" destination="new.container" after="other.block"/>

<!-- Remove block -->
<referenceBlock name="block.name" remove="true"/>

<!-- Set block template -->
<referenceBlock name="block.name">
    <arguments>
        <argument name="template" xsi:type="string">Vendor_Module::new-template.phtml</argument>
    </arguments>
</referenceBlock>
```

## Block Classes

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Vendor\Module\Api\RepositoryInterface;

class Custom extends Template
{
    /**
     * @param Context $context
     * @param RepositoryInterface $repository
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly RepositoryInterface $repository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->repository->getActiveItems();
    }

    /**
     * @param int $itemId
     * @return string
     */
    public function getItemUrl(int $itemId): string
    {
        return $this->getUrl('vendor_module/item/view', ['id' => $itemId]);
    }
}
```

## Templates (PHTML)

```php
<?php

declare(strict_types=1);

use Magento\Framework\Escaper;
use Vendor\Module\Block\Custom;

/** @var Custom $block */
/** @var Escaper $escaper */
?>
<div class="custom-block">
    <h2><?= $escaper->escapeHtml(__('Items')) ?></h2>

    <?php if ($items = $block->getItems()): ?>
        <ul class="items-list">
            <?php foreach ($items as $item): ?>
                <li class="item">
                    <a href="<?= $escaper->escapeUrl($block->getItemUrl($item->getId())) ?>">
                        <?= $escaper->escapeHtml($item->getName()) ?>
                    </a>
                    <span class="price">
                        <?= /* @noEscape */ $block->formatPrice($item->getPrice()) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><?= $escaper->escapeHtml(__('No items found.')) ?></p>
    <?php endif; ?>
</div>
```

### Escaping

Always escape output with the `$escaper` object injected into every template
(prefer it over the legacy `$block->escape*()` helpers for consistency across
Luma and Hyvä):
- `$escaper->escapeHtml()` - For text content
- `$escaper->escapeUrl()` - For URLs
- `$escaper->escapeHtmlAttr()` - For HTML attributes
- `$escaper->escapeJs()` - For JavaScript strings

Not every value is escaped — there are three cases (see the coding-standards guideline for the full rule):
1. **Needs escaping** → use `$escaper->escape*()` as above.
2. **Already-safe HTML** from an `*Html`-suffixed call (`getChildHtml()`, `toHtml()`, icon `->…Html()`) or an `(int)` cast → output raw, no comment.
3. **Safe but unrecognisable** by the phpcs sniff (non-`Html` method like `formatPrice()`, an object rendered via `__toString()`, a pre-rendered HTML variable) → mark `/* @noEscape */`.

## JavaScript (RequireJS)

### requirejs-config.js

```javascript
var config = {
    map: {
        '*': {
            'customWidget': 'Vendor_Module/js/custom-widget'
        }
    },
    paths: {
        'externalLib': 'https://example.com/lib'
    },
    shim: {
        'externalLib': {
            deps: ['jquery']
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Vendor_Module/js/shipping-mixin': true
            }
        }
    }
};
```

### Custom Widget

```javascript
// web/js/custom-widget.js
define([
    'jquery',
    'jquery-ui-modules/widget'
], function ($) {
    'use strict';

    $.widget('vendor.customWidget', {
        options: {
            someOption: 'default'
        },

        _create: function () {
            this._bind();
        },

        _bind: function () {
            this._on({
                'click .action': this.handleClick
            });
        },

        handleClick: function (event) {
            event.preventDefault();
            // Handle click
        }
    });

    return $.vendor.customWidget;
});
```

### Knockout Component

```javascript
// web/js/view/custom-component.js
define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Vendor_Module/custom-template',
            items: []
        },

        initialize: function () {
            this._super();
            this.items = ko.observableArray(this.items);
            return this;
        },

        addItem: function (item) {
            this.items.push(item);
        }
    });
});
```

### Knockout Template

```html
<!-- web/template/custom-template.html -->
<div class="custom-component">
    <!-- ko foreach: items -->
    <div class="item" data-bind="text: name"></div>
    <!-- /ko -->

    <button data-bind="click: addItem">Add Item</button>
</div>
```

## CSS/LESS

### Module CSS

```less
// web/css/source/_module.less
.custom-block {
    &-title {
        font-size: 1.5rem;
        margin-bottom: @indent__base;
    }

    .items-list {
        .lib-list-reset-styles();

        .item {
            padding: @indent__s;
            border-bottom: 1px solid @border-color__base;
        }
    }
}
```

### Extend Theme

```less
// web/css/source/_extend.less
.page-header {
    .custom-addition {
        // Your styles
    }
}
```

## Full Page Cache Considerations

### Cacheable Blocks

```xml
<!-- Block is cacheable by default -->
<block class="..." cacheable="true"/>

<!-- Disable caching (use sparingly) -->
<block class="..." cacheable="false"/>
```

### Private Content

For user-specific data, use customer sections (private content):

```javascript
// web/js/customer-data.js
define(['Magento_Customer/js/customer-data'], function (customerData) {
    var customSection = customerData.get('custom-section');

    customSection.subscribe(function (data) {
        // React to section updates
    });
});
```

## Best Practices

1. **Use layouts over PHP** - Prefer XML configuration
2. **Always escape output** in templates
3. **Leverage caching** - Design for FPC compatibility
4. **Minimize JavaScript** - Load only what's needed
5. **Use Knockout for dynamic content** - Not jQuery DOM manipulation
6. **Follow BEM naming** for CSS classes
7. **Use view models** over block methods for complex logic
