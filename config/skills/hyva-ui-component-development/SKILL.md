# Hyva UI Component Development Skill

## Overview

This skill covers building reusable, themeable UI components for Hyva-based Magento 2 storefronts. Components follow a design system approach using Tailwind CSS `@layer components`, CSS custom properties for theming, and Alpine.js for interactivity. Components integrate with Magento's template override system and Hyva's ViewModelRegistry.

## When to Use This Skill

| Scenario | Use This Skill? |
|----------|-----------------|
| Building reusable button, link, or form components | Yes |
| Creating off-canvas panels or modal dialogs | Yes |
| Building cookie consent / GDPR banners | Yes |
| Creating product image galleries | Yes |
| Building sliders with Splide.js | Yes |
| Creating responsive data tables | Yes |
| Building layered navigation components | Yes |
| Styling my-account pages | Yes |
| Admin panel UI components | No (use ui-component-development) |
| Luma/Blank theme components | No (use theme-development) |
| General Hyva theme setup / child themes | No (use hyva-theme-development) |

## Component Architecture

### Directory Structure

Components follow a three-directory pattern:

```
component-category/
  variant-name/
    markup/            # HTML preview markup (Handlebars syntax for style guide)
    media/             # Screenshots, preview images
    src/               # Magento theme override files
      Module_Name/
        templates/     # .phtml template overrides
      web/
        tailwind/
          components/  # CSS component files (@layer components)
          theme/       # Theme-level CSS overrides
            components/
            pages/
```

### Component Categories

The library includes these component categories:

| Category | Description |
|----------|-------------|
| `buttons` | Primary, secondary, icon, animated button variants |
| `links` | Underline links, icon links, reverse animations |
| `inputs` | Form fields, checkboxes, radios, toggle switches |
| `cookie-notice` | GDPR cookie consent banners |
| `gallery` | Product image galleries with zoom and video |
| `info-pop-up-modal` | Modal dialogs using Hyva ModalInterface |
| `off-canvas-account` | Slide-in account panels |
| `off-canvas-layered-navigation` | Slide-in filter panels |
| `cms-menu` | CMS page navigation menus |
| `my-account-pages` | Customer account page templates |
| `search-preview` | Live search results preview |
| `subcategory-slider` | Category slider components |
| `usp-bar` | USP bar with Splide.js autoplay |
| `success-page` | Order success page components |
| `transactional-emails` | Email template components |
| `product-labels` | Product badge/label overlays |
| `style-guide` | Style guide CMS page setup |

## CSS Component Layer Pattern

### Core Pattern: `@layer components` with CSS Custom Properties

All reusable CSS components use this pattern:

```css
@layer components {
    .component-name {
        /* 1. Declare ALL themeable values as CSS custom properties */
        --text-color: #FFFFFF;
        --default-color: #2563EB;
        --hover-color: #1D4ED8;
        --pressed-color: #1E3A8A;
        --disabled-color: #D4D4D4;
        --padding-x-mobile: 2rem;
        --padding-x-desktop: 3rem;
        --transition-duration: 300ms;
        --transition-timing-function: ease-in-out;

        /* 2. Base styles using @apply with var() references */
        @apply overflow-hidden relative bg-[var(--default-color)] text-[var(--text-color)]
               px-[var(--padding-x-mobile)] xl:px-[var(--padding-x-desktop)]
               transition-colors duration-[var(--transition-duration)]
               ease-[var(--transition-timing-function)];

        /* 3. State variants */
        &[disabled] {
            @apply bg-[var(--disabled-color)] cursor-not-allowed;
        }
    }

    /* 4. Modifier classes */
    .component-name-primary {
        @apply bg-[var(--default-color)] text-[var(--text-color)];

        &:hover {
            @apply bg-[var(--hover-color)];
        }

        &:active {
            @apply bg-[var(--pressed-color)];
        }
    }

    .component-name-secondary {
        @apply bg-[var(--text-color)] text-[var(--default-color)]
               border-[var(--default-color)];

        &:hover {
            @apply bg-[var(--hover-color)] text-[var(--text-color)];
        }
    }
}
```

### Key CSS Conventions

1. **Custom properties first** - Declare all themeable values at the top of the component block
2. **Use `var()` in `@apply`** - Reference custom properties via `bg-[var(--name)]`, `text-[var(--name)]`, etc.
3. **Responsive values** - Use `xl:` breakpoint prefix for desktop overrides: `px-[var(--padding-x-mobile)] xl:px-[var(--padding-x-desktop)]`
4. **Transition properties** - Always expose `--transition-duration` and `--transition-timing-function`
5. **State coverage** - Always style `:hover`, `:active`, `[disabled]`, and `:focus` states
6. **Nested selectors** - Use `&` for state and child selectors within `@layer components`

### Button Component Example

```css
@layer components {
    .btn {
        --text-color: #FFFFFF;
        --default-color: #2563EB;
        --hover-color: #1D4ED8;
        --pressed-color: #1E3A8A;
        --disabled-color: #D4D4D4;
        --padding-x-mobile: 2rem;
        --padding-x-desktop: 3rem;
        --padding-y-desktop: 0.688rem;
        --padding-y-mobile: 0.5rem;
        --gap-between-icon-and-text: 0.625rem;
        --icon-width: 1.5rem;
        --transition-duration: 300ms;
        --transition-timing-function: ease-in-out;

        @apply overflow-hidden relative
               px-[var(--padding-x-mobile)] xl:px-[var(--padding-x-desktop)]
               py-[var(--padding-y-mobile)] xl:py-[var(--padding-y-desktop)]
               font-bold inline-flex items-center justify-center
               transition-colors duration-[var(--transition-duration)]
               ease-[var(--transition-timing-function)]
               border border-transparent;

        &[disabled] {
            @apply bg-[var(--disabled-color)] cursor-not-allowed;
        }
    }

    .btn-primary {
        @apply bg-[var(--default-color)] text-[var(--text-color)];
        &:hover { @apply bg-[var(--hover-color)]; }
        &:active { @apply bg-[var(--pressed-color)]; }
    }

    .btn-secondary {
        @apply bg-[var(--text-color)] text-[var(--default-color)]
               border-[var(--default-color)];
        &:hover {
            @apply bg-[var(--hover-color)] border-[var(--hover-color)]
                   text-[var(--text-color)];
        }
    }

    /* Icon modifier */
    .btn.with-icon {
        @apply gap-[var(--gap-between-icon-and-text)];
    }

    /* Animation modifier: icon slides on hover */
    .btn.with-move {
        & svg, span {
            @apply transition-transform
                   duration-[var(--transition-duration)]
                   ease-[var(--transition-timing-function)];
        }
    }

    .btn.with-move.right:hover {
        & span {
            @apply translate-x-[calc((var(--gap-between-icon-and-text-when-hovered)/-2)
                   -_(var(--gap-between-icon-and-text)/-2))];
        }
    }
}
```

### Form Component Example

```css
/* Scope custom properties to form/fieldset for inheritance */
form, fieldset {
    --input-height-mobile: 3rem;
    --input-height-desktop: 3rem;
    --placeholder-color: #CBD5E1;
    --error-color: #DC2626;
    --disabled-bg-color: #F8FAFC;
    --disabled-label-color: #CBD5E1;
    --default-border-color: #CBD5E1;
    --hover-border-color: #3B82F6;
    --focus-border-color: #3B82F6;
    --disabled-border-color: #CBD5E1;
    --transition-duration: 150ms;
    --transition-timing-function: ease-in-out;
    --checkbox-size: 1rem;
    --radio-size: 1rem;
    --checked-color: #2563EB;
}

.field {
    @apply mb-3 w-full;

    & > .label {
        @apply mb-1 block select-none;
    }

    & .messages {
        @apply w-full text-[0.75em] text-[var(--error-color)] mt-1;
    }

    &.required .label span::after {
        @apply content-['*'] text-[var(--error-color)] font-normal;
    }

    &.field-error input,
    &.field-error select,
    &.field-error textarea {
        @apply border-[var(--error-color)];
    }
}

.form-input,
.form-select,
.form-textarea {
    @apply w-full h-[var(--input-height-mobile)]
           xl:h-[var(--input-height-desktop)]
           border border-[var(--default-border-color)]
           transition-colors duration-[var(--transition-duration)]
           ease-[var(--transition-timing-function)];

    &:hover { @apply border-[var(--hover-border-color)]; }
    &:focus { @apply border-[var(--focus-border-color)] ring-transparent; }
    &::placeholder { @apply text-[var(--placeholder-color)]; }
    &:disabled {
        @apply bg-[var(--disabled-bg-color)]
               border-[var(--disabled-border-color)]
               cursor-not-allowed;
    }
}
```

### Toggle Switch Component

```css
.field.choice .toggle {
    @apply inline-flex items-start p-0;

    &:hover .thumb {
        @apply border-[var(--hover-border-color)];
    }

    & input {
        @apply sr-only;

        &:checked + .thumb {
            @apply border-[var(--checked-color)]
                   after:bg-[var(--checked-color)]
                   after:translate-x-full;
        }
    }

    & .thumb {
        @apply relative w-[1.625rem] h-4
               border border-[var(--default-border-color)]
               rounded-full cursor-pointer shrink-0;

        &:after {
            @apply content-[''] absolute top-0.5 left-0.5
                   bg-[var(--default-border-color)]
                   rounded-full w-2.5 h-2.5
                   transition-[transform,background-color]
                   duration-[var(--transition-duration)];
        }
    }
}
```

### Link Component Example

```css
.link, .link:visited {
    --link-color: #1E40AF;
    --reverse-link-active-color: #3B82F6;
    --underline-direction: left;
    --underline-height: 1px;
    --transition-duration: 300ms;
    --transition-timing-function: in-out;

    @apply bg-no-repeat bg-[length:0%_100%]
           transition-all duration-[var(--transition-duration)]
           ease-[var(--transition-timing-function)]
           hover:bg-[length:100%_100%];
    background-image: linear-gradient(
        to bottom,
        transparent calc(100% - var(--underline-height)),
        var(--link-color) var(--underline-height)
    );
    background-position: var(--underline-direction);
}

/* Reverse variant: starts underlined, removes on hover */
.link.reverse {
    @apply bg-[length:100%_100%] hover:bg-[length:0%_100%];
}
```

### Responsive Table Component

```css
table.mobile-friendly-table {
    @apply w-full;

    & thead tr {
        @apply hidden lg:table-row border-b text-start text-sm;
    }

    & tbody tr {
        @apply align-top even:bg-account-complementary-gray;

        & td {
            @apply block text-right lg:table-cell lg:text-left;

            /* Mobile: show column header via data attribute */
            &:before {
                @apply content-[attr(data-title)] float-left text-sm
                       font-bold lg:hidden;
            }
        }
    }
}
```

## Alpine.js Component Patterns

### Off-Canvas Panel

```php
<?php

declare(strict_types=1);

use Hyva\Theme\Model\ViewModelRegistry;
use Hyva\Theme\ViewModel\HeroiconsOutline;
use Magento\Framework\Escaper;

/** @var Escaper $escaper */
/** @var ViewModelRegistry $viewModels */

$heroicons = $viewModels->require(HeroiconsOutline::class);
?>

<script>
    function initOffCanvasPanel() {
        return {
            open: false,
            animationDuration: 500,

            openPanel() {
                this.open = true;
                document.body.classList.add('overflow-hidden');
            },

            closePanel() {
                this.open = false;
                setTimeout(() => {
                    document.body.classList.remove('overflow-hidden');
                }, this.animationDuration);
            }
        }
    }
</script>

<div x-data="initOffCanvasPanel()"
     @keyup.escape="closePanel()">
    <button type="button"
            @click="openPanel()"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="true">
        <?= $heroicons->userHtml('w-6 h-6', 24, 24, ['aria-hidden' => 'true']) ?>
    </button>

    <div role="dialog"
         aria-modal="true"
         :aria-hidden="!open"
         class="fixed inset-y-0 z-30 flex right-0">
        <!-- Backdrop -->
        <div class="backdrop"
             x-show="open" x-cloak
             x-transition:enter="ease-in-out duration-500"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-500"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closePanel()"
             role="button"
             aria-label="<?= $escaper->escapeHtmlAttr(__('Close panel')) ?>">
        </div>
        <!-- Panel -->
        <div class="relative w-screen max-w-md shadow-2xl bg-white"
             x-show="open" x-cloak
             x-transition:enter="transform transition ease-in-out duration-500"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in-out duration-500"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full">
            <!-- Content here -->
            <button class="absolute top-0 right-0 p-6"
                    @click="closePanel()">
                <?= $heroicons->xHtml('', 24, 24, ['aria-hidden' => 'true']) ?>
            </button>
        </div>
    </div>
</div>
```

### Cookie Consent Banner

```php
<script>
    function initCookieBanner() {
        const cookieName = '<?= $escaper->escapeJs(Cookie::IS_USER_ALLOWED_SAVE_COOKIE) ?>';
        const websiteId = <?= (int) $storeViewModel->getStore()->getWebsiteId() ?>;

        return {
            showBanner: false,
            trackingChecked: false,
            marketingChecked: false,

            checkAcceptCookies() {
                const allowed = hyva.getCookie(cookieName);
                const parsed = allowed ? JSON.parse(unescape(allowed)) : [];
                this.showBanner = parsed[websiteId] === undefined;
            },

            acceptAll() {
                hyva.setCookie(cookieName, cookieValue, cookieExpires);
                hyva.setCookie('gdpr-necessary', 1, cookieExpires);
                hyva.setCookie('gdpr-tracking', 1, cookieExpires);
                hyva.setCookie('gdpr-marketing', 1, cookieExpires);
                this.showBanner = false;
                window.dispatchEvent(new CustomEvent('user-allowed-save-cookie'));
            },

            acceptNecessaryOnly() {
                hyva.setCookie(cookieName, cookieValue, cookieExpires);
                hyva.setCookie('gdpr-necessary', 1, cookieExpires);
                hyva.setCookie('gdpr-tracking', 0, cookieExpires);
                hyva.setCookie('gdpr-marketing', 0, cookieExpires);
                this.showBanner = false;
            }
        }
    }
</script>

<section x-data="initCookieBanner()"
         @private-content-loaded.window="checkAcceptCookies()">
    <template x-if="showBanner">
        <div role="dialog" aria-modal="true"
             class="fixed bottom-0 left-0 z-50 bg-zinc-50 p-6">
            <!-- Cookie options UI -->
            <button @click="acceptNecessaryOnly(); showBanner = false"
                    class="btn btn-primary">
                <span><?= $escaper->escapeHtml(__('Accept necessary only')) ?></span>
            </button>
            <button @click="acceptAll(); showBanner = false"
                    class="btn btn-primary">
                <span><?= $escaper->escapeHtml(__('Allow Cookies')) ?></span>
            </button>
        </div>
    </template>
</section>
```

### Layered Navigation Off-Canvas

```php
<script>
    function initLayeredNavigation() {
        return {
            isMobile: false,
            mobileOpen: false,

            checkIsMobileResolution() {
                this.isMobile = window.matchMedia('(max-width: 768px)').matches;
                if (!this.isMobile) {
                    this.closeOffcanvasLayeredNavigation();
                }
            },

            openOffcanvasLayeredNavigation() {
                this.mobileOpen = true;
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => {
                    hyva.trapFocus(this.$refs.layeredNavigation);
                });
            },

            closeOffcanvasLayeredNavigation() {
                this.mobileOpen = false;
                document.body.style.overflow = '';
                hyva.releaseFocus(this.$refs.layeredNavigation);
            }
        }
    }
</script>

<div x-data="initLayeredNavigation()"
     x-init="checkIsMobileResolution()"
     @resize.window.debounce="checkIsMobileResolution()"
     @keyup.escape.window="closeOffcanvasLayeredNavigation()"
     @open-off-canvas-filters.window="openOffcanvasLayeredNavigation()"
     x-ref="layeredNavigation">
    <!-- Off-canvas filter content -->
</div>
```

### Splide.js Slider Integration

```php
<?php $uniqueId = uniqid(); ?>

<section id="slider_<?= $escaper->escapeHtmlAttr($uniqueId) ?>"
         class="splide w-full"
         x-data="initSlider_<?= $escaper->escapeHtmlAttr($uniqueId) ?>()">
    <div class="splide__track">
        <ul class="splide__list">
            <li class="splide__slide"><!-- Slide content --></li>
        </ul>
    </div>
</section>

<script>
    function initSlider_<?= $escaper->escapeHtml($uniqueId) ?>() {
        return {
            init() {
                const splide = new Splide(this.$root, {
                    mediaQuery: 'min',
                    type: 'loop',
                    arrows: false,
                    pagination: false,
                    perPage: 2,
                    gap: '4rem',
                    autoScroll: { autoStart: false, speed: 1 },
                    breakpoints: {
                        640: { perPage: 3 },
                        768: { type: 'slide', perPage: 4 }
                    }
                });
                splide.on('ready', () => this.toggleAutoScroll(splide));
                splide.mount(window.splide.Extensions);
                splide.on('updated', () => this.toggleAutoScroll(splide));
            },
            toggleAutoScroll(instance) {
                window.matchMedia("(min-width: 768px)").matches
                    ? instance.Components.AutoScroll.pause()
                    : instance.Components.AutoScroll.play();
            }
        }
    }
</script>
```

### Product Image Gallery

Key patterns from the gallery component:

```php
<div id="gallery"
     x-data="initGallery()"
     x-bind="eventListeners">
    <!-- Main image with zoom -->
    <template x-for="(image, index) in images" :key="index">
        <img @dblclick="zoom()"
             @touchstart="onTouchStart()"
             @mousemove.document="moveZoomedImage()"
             :src="fullscreen ? image.full : image.img"
             x-show="active === index"
             x-transition.opacity.duration.500ms />
    </template>
    <!-- Thumbnail slider -->
</div>

<script>
function initGallery() {
    return {
        active: 0,
        fullscreen: false,
        isZoomed: false,
        images: <?= /* @noEscape */ $block->getGalleryImagesJson() ?>,
        initialImages: <?= /* @noEscape */ $block->getGalleryImagesJson() ?>,
        eventListeners: {
            ['@keydown.window.escape']() {
                if (this.fullscreen) this.closeFullScreen();
            },
            ['@update-gallery.window'](event) {
                this.receiveImages(event.detail);
            },
            ['@reset-gallery.window']() {
                this.resetGallery();
            }
        },
        openFullscreen() {
            this.fullscreen = true;
            hyva.trapFocus(this.$root);
        },
        closeFullScreen() {
            this.fullscreen = false;
            hyva.releaseFocus(this.$root);
        },
        // Touch swipe navigation
        handleTouchStart(event) { /* store touch start coords */ },
        handleTouchMove(event) { /* detect swipe direction, navigate */ }
    }
}
</script>
```

## Modal Dialogs

### Using Hyva ModalInterface

```php
<?php
use Hyva\Theme\Model\Modal\ModalInterface;

/** @var ModalInterface $modal */
?>

<div <?= $modal->isInitiallyHidden() ? 'x-cloak' : '' ?>
     x-bind="overlay('<?= $escaper->escapeHtmlAttr($modal->getDialogRefName()) ?>')"
     class="fixed z-50 inset-0 bg-black bg-opacity-60 transition-all duration-300">
    <div x-ref="<?= $escaper->escapeHtmlAttr($modal->getDialogRefName()) ?>"
         role="dialog"
         aria-modal="true"
         <?php if ($modal->getAriaLabel()): ?>
             aria-label="<?= $escaper->escapeHtmlAttr($modal->getAriaLabel()) ?>"
         <?php endif; ?>
         @click.outside="hide()"
         class="bg-white absolute p-5 rounded max-h-[60%] overflow-y-auto
                -translate-y-1/2 -translate-x-1/2 top-1/2 left-1/2
                w-[90%] max-w-lg">
        <button @click="hide()" type="button"
                aria-label="<?= $escaper->escapeHtmlAttr(__('Close')) ?>">
            <?= $heroicons->xHtml('w-6 h-6') ?>
        </button>
        <?= $modal->getContentHtml() ?>
    </div>
</div>
```

## Hyva Helper Functions

Components frequently use these built-in Hyva JavaScript helpers:

| Function | Purpose |
|----------|---------|
| `hyva.getCookie(name)` | Read browser cookie value |
| `hyva.setCookie(name, value, days)` | Set browser cookie |
| `hyva.trapFocus(element)` | Trap keyboard focus within element (modals, off-canvas) |
| `hyva.releaseFocus(element)` | Release focus trap |
| `hyva.str(template, ...args)` | String interpolation with `%1`, `%2` placeholders |

## ViewModelRegistry Integration

### Common ViewModels in Components

```php
<?php
use Hyva\Theme\Model\ViewModelRegistry;

/** @var ViewModelRegistry $viewModels */

// Icons - use HeroiconsOutline for general components
$heroicons = $viewModels->require(\Hyva\Theme\ViewModel\HeroiconsOutline::class);
echo $heroicons->chevronDownHtml('w-6 h-6', 24, 24, ['aria-hidden' => 'true']);
echo $heroicons->xHtml('', 24, 24, ['aria-hidden' => 'true']);
echo $heroicons->userHtml('', 32, 32, ['aria-hidden' => 'true']);
echo $heroicons->truckHtml('stroke-slate-600 w-8 h-8 shrink-0');

// Icons - use HeroiconsSolid for filled variants
$heroiconsSolid = $viewModels->require(\Hyva\Theme\ViewModel\HeroiconsSolid::class);

// Store configuration
$storeConfig = $viewModels->require(\Hyva\Theme\ViewModel\StoreConfig::class);
$configValue = $storeConfig->getStoreConfig('catalog/product_video/show_related');

// Store data
$storeViewModel = $viewModels->require(\Hyva\Theme\ViewModel\Store::class);
$websiteId = $storeViewModel->getStore()->getWebsiteId();
?>
```

## Accessibility Patterns

### ARIA for Dialogs and Panels

```html
<!-- Off-canvas panel -->
<div role="dialog"
     aria-modal="true"
     :aria-hidden="!open"
     aria-label="<?= $escaper->escapeHtmlAttr(__('Panel Title')) ?>">

<!-- Trigger button -->
<button :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="true"
        aria-label="<?= $escaper->escapeHtmlAttr(__('Open Panel')) ?>">

<!-- Accordion section -->
<button :aria-controls="`${id}-content`"
        :aria-expanded="open">
<div :id="`${id}-content`">
```

### Focus Management

```javascript
// Opening a panel/modal
openPanel() {
    this.open = true;
    document.body.style.overflow = 'hidden'; // or classList.add('overflow-hidden')
    this.$nextTick(() => {
        hyva.trapFocus(this.$refs.panelElement);
    });
},

// Closing
closePanel() {
    this.open = false;
    document.body.style.overflow = '';
    hyva.releaseFocus(this.$refs.panelElement);
}
```

### Escape Key Handling

```html
<div x-data="initComponent()"
     @keyup.escape="close()"
     @keyup.escape.window="close()">
```

## Transition Patterns

### Fade Transition (Backdrop)

```html
<div x-show="open" x-cloak
     x-transition:enter="ease-in-out duration-500"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in-out duration-500"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
```

### Slide Transition (Panel from Right)

```html
<div x-show="open" x-cloak
     x-transition:enter="transform transition ease-in-out duration-500"
     x-transition:enter-start="translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transform transition ease-in-out duration-500"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="translate-x-full">
```

### Slide Transition (Panel from Left)

```html
<div x-show="open" x-cloak
     x-transition:enter="transform transition ease-in-out duration-500"
     x-transition:enter-start="-translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transform transition ease-in-out duration-500"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="-translate-x-full">
```

### CSS Grid Accordion (No JavaScript Height Calculation)

```html
<div class="grid duration-300 ease-in-out grid-rows-[0fr]
            transition-[grid-template-rows]"
     :class="{ '!grid-rows-[1fr]': open }">
    <div class="overflow-hidden">
        <!-- Collapsible content -->
    </div>
</div>
```

## Unique ID Pattern for Multiple Instances

When a component may appear multiple times on a page:

```php
<?php $uniqueId = uniqid(); ?>

<div x-data="initComponent_<?= $escaper->escapeHtmlAttr($uniqueId) ?>()">
    <!-- Component markup -->
</div>

<script>
    function initComponent_<?= $escaper->escapeHtml($uniqueId) ?>() {
        return {
            init() {
                // Use this.$root to scope DOM queries
            }
        }
    }
</script>
```

Alternatively, use Alpine's `$id()` for scoped IDs:

```html
<div x-data="{ open: false, id: $id('accordion') }"
     x-defer="intersect">
    <button :aria-controls="`${id}-content`"
            :aria-expanded="open">Title</button>
    <div :id="`${id}-content`" x-show="open">Content</div>
</div>
```

## Integrating Components into Magento Theme

### File Placement

Component source files go into your child theme at matching paths:

```
app/design/frontend/Vendor/hyva-child/
  Module_Name/
    templates/              # .phtml template overrides
  web/
    tailwind/
      components/           # CSS component layer files
        button.css
        forms.css
        typography.css
        table.css
        customer.css
      theme/
        components/         # Theme-specific component overrides
        pages/              # Page-specific styles
```

### CSS Import Order

In your `components/index.css` (or directly in `tailwind-source.css`):

```css
@import "./button.css";
@import "./forms.css";
@import "./typography.css";
@import "./table.css";
@import "./customer.css";
```

### Layout XML for Template Overrides

Use layout XML to swap templates or add blocks:

```xml
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <referenceBlock name="cookie_notices"
                        template="Magento_Cookie::notices.phtml" />
    </body>
</page>
```

## Markup Preview Files

The `markup/` directories contain HTML previews using Handlebars-like syntax for the style guide:

```html
<!-- Icon placeholder syntax -->
{{icon "heroicons/outline/arrow-narrow-right" classes="w-6 h-6" width=24 height=24}}

<!-- Usage in button markup preview -->
<button class="btn btn-primary with-icon">
    {{icon "heroicons/outline/arrow-narrow-right" classes="w-6 h-6" width=24 height=24}}
    <span>Button label</span>
</button>
```

These are NOT used in Magento templates - they're for the component style guide preview system only.

## Best Practices

1. **CSS custom properties for every themeable value** - Colors, sizes, spacing, transitions must all be CSS custom properties so projects can override without editing component CSS
2. **Use `@layer components`** for reusable CSS classes - This ensures correct specificity ordering with Tailwind utilities
3. **Compose with `@apply`** - Reference Tailwind utilities inside component classes for maintainability
4. **Always handle disabled state** - Every interactive component needs `[disabled]` styling
5. **Body scroll lock for overlays** - Add `overflow-hidden` to body when opening full-screen overlays, remove on close with timeout matching animation duration
6. **Use `x-cloak` with `x-show`** - Prevent flash of unstyled content for conditionally rendered elements
7. **Use `template x-if` for heavy content** - Cookie banners and modals that may never show should use `<template x-if>` to avoid rendering DOM until needed
8. **Scope to `this.$root`** - Use `this.$root` for DOM queries within Alpine components to avoid cross-component interference
9. **Unique function names** - Append `uniqid()` to function names when multiple instances may exist on the same page
10. **Use `hyva.trapFocus()` / `hyva.releaseFocus()`** for modal and off-canvas components
11. **Escape all PHP output** - Use `$escaper->escapeHtml()`, `$escaper->escapeHtmlAttr()`, `$escaper->escapeJs()`, `$escaper->escapeUrl()`
12. **Use `x-defer="intersect"`** for below-the-fold interactive components to defer Alpine initialization
13. **Mobile-first responsive tables** - Use `data-title` attributes on `<td>` elements with `content-[attr(data-title)]` for mobile column labels
