# Guidelines & Skills

Bricklayer includes 33 development guidelines and 28 skill guides that AI agents load via the `development-context` tool before writing code.

## How It Works

1. Call `development-context` with a category name
2. Bricklayer returns the relevant guidelines and skills as markdown
3. The AI agent uses this context to write Magento-compliant code

```
development-context category="plugin"
→ Returns plugin development patterns, before/after/around conventions, etc.
```

## Guidelines (33 documents)

Guidelines are stored in `config/guidelines/` and organized by area:

### Core

| Guideline | Description |
|-----------|-------------|
| Architecture | High-level architecture: plugins, observers, preferences, factories, repositories, service contracts |
| Coding Standards (Syntax) | PSR-12 syntax and formatting |
| Coding Standards (Quality) | Quality rules, best practices, constants as final classes |
| Security | Input validation, CSRF, XSS prevention, ACL |
| Performance | Caching, lazy loading, query optimization |
| Testing | Unit, integration, API, and MFTF testing patterns |

### Patterns

Concrete, copy-ready implementation patterns in `config/guidelines/patterns/`:

| Guideline | Description |
|-----------|-------------|
| Plugin | Interceptors: before/after/around, plugin chains on repositories with sortOrder |
| Observer | Event observer development |
| Preference | Class preference (rewrite) development |
| Factory | Factory generation and usage |
| Repository | Repository / service-layer data access |
| Service Contract | `Api/` interfaces and Data Interfaces |
| Pool | DI-based strategy resolution with extensible array injection (replaces if/else type branching) |
| Value Object | Immutable DTOs with `public readonly` properties and `fromJson()`/`toJson()` |
| Context Object | Bundling repeated request-scoped params into one immutable object with a Builder |

### Database

| Guideline | Description |
|-----------|-------------|
| Schema | Declarative schema (`db_schema.xml`) |
| EAV | EAV attributes and entity development |
| Patches | Data and schema patch patterns |
| Indexers | Custom indexer development |

### Modules

| Guideline | Description |
|-----------|-------------|
| Registration | `registration.php`, `module.xml`, `composer.json` |
| Dependencies | Module dependency declaration |
| Versioning | Semantic versioning for modules |

### Areas

| Guideline | Description |
|-----------|-------------|
| Admin | Admin routing, controllers, UI components |
| Frontend | Layout XML, templates, JavaScript |
| WebAPI | REST endpoint development |
| GraphQL | Schema and resolver patterns |

### Ecosystem

| Guideline | Description |
|-----------|-------------|
| Adobe Commerce | Commerce-specific features |
| Hyva | Hyva theme architecture |
| Mage-OS | Mage-OS considerations |

## Skills (28 guides)

Skills are detailed, task-specific guides stored in `config/skills/`:

### Checkout & Payment

- Checkout customization (steps, layout processors, config providers)
- Payment integration (core, gateway components, checkout)

### Hyva Theme

- Theme setup and Alpine.js CSP components
- ViewModels and module compatibility
- UI components (CSS design system, Alpine.js interactivity)

### Hyva Checkout

- Step and component development
- XML configuration and layout
- Evaluation, form, and frontend APIs
- Magewire reactive components

### API & Integration

- REST API development
- GraphQL schema and resolvers
- Shipping carrier integration
- Message queues and async processing

### Data Processing

- Import entity development
- Export entity development

### Admin UI

- UI component grids
- UI component forms

### Core Patterns

- Plugin development
- Event observer development
- Cron job development
- Indexer development

### Theme Development

- Theme structure and layout XML
- LESS/CSS styling and JavaScript

## Category Reference

Use `development-context category="list"` to see all available categories at runtime.

| Category | Description |
|----------|-------------|
| `hyva-theme` | Hyva theme setup and Alpine.js CSP components |
| `hyva-theme-advanced` | Hyva ViewModels, module compatibility |
| `hyva-ui-component` | Hyva UI component CSS and design system |
| `hyva-ui-component-js` | Hyva UI component Alpine.js |
| `hyva-checkout` | Hyva Checkout step and component development |
| `hyva-checkout-config` | Hyva Checkout XML configuration |
| `hyva-checkout-api` | Hyva Checkout evaluation, form, frontend APIs |
| `magewire` | Magewire reactive component development |
| `module` | Module scaffolding and structure |
| `model` | Model, repository, data layer |
| `plugin` | Plugin (interceptor) development |
| `observer` | Event observer development |
| `preference` | Class preference (rewrite) development |
| `eav` | EAV attribute development |
| `data-patch` | Data and schema patches |
| `rest-api` | REST API endpoint development |
| `graphql` | GraphQL schema and resolvers |
| `payment` | Payment method module setup |
| `payment-gateway` | Payment gateway components |
| `payment-checkout` | Payment checkout integration |
| `shipping` | Shipping carrier integration |
| `message-queue` | Message queue and async processing |
| `import` | Custom import entity |
| `export` | Custom export entity |
| `frontend` | Frontend development |
| `theme` | Theme structure and layout |
| `theme-styling` | Theme LESS/CSS and JavaScript |
| `checkout` | Checkout custom steps |
| `checkout-advanced` | Checkout config providers, mixins, validation |
| `adminhtml` | Admin panel development |
| `ui-component` | Admin UI component grids |
| `ui-component-form` | Admin UI component forms |
| `cron` | Cron job development |
| `indexer` | Custom indexer development |
| `testing` | Testing strategies |
| `coding-standards` | PHP coding standards |
| `security` | Security best practices |
| `performance` | Performance optimization |
