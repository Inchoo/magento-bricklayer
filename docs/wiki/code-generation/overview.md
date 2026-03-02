# Code Generation

4 tools for scaffolding Magento modules, models, controllers, and REST API endpoints with proper structure and configuration.

## Tools

### `generate-module`

Scaffold a complete new module.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `vendor` | string | *required* | Vendor name (e.g., `Inchoo`) |
| `module` | string | *required* | Module name (e.g., `CustomPricing`) |
| `version` | string | `1.0.0` | Module version |
| `dry_run` | bool | false | Preview without writing |
| `force` | bool | false | Overwrite existing files |

**Generated files:**
- `registration.php`
- `etc/module.xml`
- `composer.json`

### `generate-model`

Generate model, resource model, and collection with database schema.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `vendor` | string | *required* | Vendor name |
| `module` | string | *required* | Module name |
| `entity` | string | *required* | Entity name (e.g., `Subscription`) |
| `table` | string | *required* | Database table name |
| `fields` | string | `""` | Comma-separated field definitions |
| `dry_run` | bool | false | Preview without writing |
| `force` | bool | false | Overwrite existing files |

**Generated files:**
- `Model/<Entity>.php`
- `Model/ResourceModel/<Entity>.php`
- `Model/ResourceModel/<Entity>/Collection.php`
- `etc/db_schema.xml`

### `generate-controller`

Generate controller with route, layout, and template.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `vendor` | string | *required* | Vendor name |
| `module` | string | *required* | Module name |
| `action` | string | `index` | Action name |
| `area` | string | `frontend` | `frontend` or `adminhtml` |
| `route` | string | `custom` | Route front name |
| `dry_run` | bool | false | Preview without writing |
| `force` | bool | false | Overwrite existing files |

**Generated files:**
- `Controller/<Action>.php` (or `Controller/Adminhtml/<Action>.php`)
- `etc/<area>/routes.xml`
- `view/<area>/layout/<route>_index_<action>.xml`
- `view/<area>/templates/<action>.phtml`

### `generate-api`

Generate REST API endpoint with interface and webapi.xml configuration.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `vendor` | string | *required* | Vendor name |
| `module` | string | *required* | Module name |
| `resource` | string | *required* | Resource name (e.g., `Subscription`) |
| `method` | string | `GET` | HTTP method |
| `path` | string | `""` | Custom API path |
| `dry_run` | bool | false | Preview without writing |
| `force` | bool | false | Overwrite existing files |

**Generated files:**
- `Api/<Resource>Interface.php`
- `Model/<Resource>.php` (implementation)
- `etc/webapi.xml`
- `etc/di.xml` (preference mapping)

## Dry Run Mode

All generators support `dry_run=true` to preview files without writing:

```
generate-module vendor="Inchoo" module="CustomPricing" dry_run=true
```

Output includes file paths, content preview, and `new`/`exists` annotations.

## Force Overwrite

Existing files cause a conflict error by default. Use `force=true` to overwrite:

```
generate-model vendor="Inchoo" module="CustomPricing" entity="Rule" table="inchoo_pricing_rule" force=true
```

## Production Safety

All code generation tools are **blocked in production mode**. This cannot be overridden.
