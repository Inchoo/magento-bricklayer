# Hyvä UI Components: CSS & Design System

> Related: See [hyva-ui-component-alpine](../hyva-ui-component-alpine/SKILL.md) for Alpine.js components, modals, accessibility, and integration patterns.

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
