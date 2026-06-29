# Introspection Tools

Tools for inspecting Magento's application state, configuration, modules, EAV system, routing, view layer (layout and UI components), and message-queue wiring.

## Pre-Modification Check

### `check-class`

Essential pre-check before modifying any class — returns combined plugin list, DI configuration, and preferences in one call. Shows the full runtime picture that file reading misses.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `className` | string | *required* | Fully qualified class name |

Returns a combined view with `plugins`, `di_configuration`, and `preferences` keys. Includes a `_skill_hint` pointing to the relevant `development-context` category.

## Application & System

### `application-info`

Returns Magento version, PHP version, deploy mode, and installation summary.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include` | string | `""` | Use `stores` for store hierarchy |

### `system-status`

Check system status for cache, indexers, deploy mode, cron, or cron history.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `check` | string | *required* | `cache`, `indexers`, `deploy-mode`, `cron`, `cron-history` |
| `group` | string | `""` | Filter cron jobs by group |
| `jobCode` | string | `""` | Filter cron history by job code |
| `limit` | int | 50 | Max cron history records |

### `reinitialize`

Rebuilds the Magento ObjectManager. Call after `setup:upgrade`, `setup:di:compile`, or `module:enable`.

## Configuration

### `configuration-get`

Retrieve a system configuration value by path.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `path` | string | *required* | e.g., `general/locale/code` |
| `scopeType` | string | `default` | `default`, `websites`, `stores` |
| `scopeCode` | int | 0 | Website or store ID |

### `configuration-list`

List available configuration paths for a section.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `section` | string | `""` | e.g., `general`, `catalog` |

### `di-configuration`

Returns DI configuration (preferences, plugins, virtual types) for a class or interface.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `className` | string | *required* | Fully qualified class name |
| `area` | string | `global` | `global`, `frontend`, `adminhtml` |

### `plugin-list`

Lists all plugins (interceptors) for a class.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `className` | string | *required* | Fully qualified class name |
| `method` | string | `""` | Filter by method name |

### `event-list`

Lists events and their observers.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `eventName` | string | `""` | Filter by event name pattern |
| `area` | string | `global` | `global`, `frontend`, `adminhtml` |

### `preference-list`

Lists all class preferences (rewrites).

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `interface` | string | `""` | Filter by interface/class name |

## Modules

### `module-list`

Lists installed modules.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `vendor` | string | `""` | Filter by vendor |
| `enabledOnly` | bool | false | Only enabled modules |
| `verbosity` | string | `standard` | `minimal`, `standard`, `detailed` |
| `count_only` | bool | false | Return count only |

### `module-structure`

Returns the file/folder structure of a module.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `moduleName` | string | *required* | `Vendor_Module` format |

### `validate-module`

Validates module structure and configuration files.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `moduleName` | string | *required* | `Vendor_Module` format |

## EAV

### `eav-attributes`

Returns EAV attributes for an entity type.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `entityType` | string | *required* | e.g., `catalog_product`, `customer` |
| `userDefinedOnly` | bool | false | Only custom attributes |
| `verbosity` | string | `standard` | `minimal`, `standard`, `detailed` |

### `eav-entity-types`

Returns all registered EAV entity types. No parameters.

## Routing

### `route-list`

Lists all configured frontend or admin routes.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `area` | string | `frontend` | `frontend`, `adminhtml` |

### `route-info`

Returns detailed information about a specific route.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `frontName` | string | *required* | Route front name |
| `area` | string | `frontend` | `frontend`, `adminhtml` |

### `api-endpoints`

Lists configured REST API endpoints.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `method` | string | `""` | Filter by HTTP method |
| `path` | string | `""` | Filter by path pattern |

### `url-rewrites`

Lists URL rewrites with optional filtering.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `requestPath` | string | `""` | Filter by request path |
| `storeId` | int | 0 | Filter by store |
| `limit` | int | 100 | Max results |

## View Layer

### `layout-inspect`

Resolves a layout handle into its runtime-merged block/container tree (merged across all modules and the active theme), or lists every registered handle for an area when no handle is given. Shows applied `referenceBlock`/`referenceContainer`/`move`/`remove` directives, declared and theme-resolved `.phtml` template paths, and the page layout. Replaces the old static `magento://reference/layouts` resource — the handle list is read from the live install.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `handle` | string | `""` | Layout handle to resolve; omit to list all registered handles |
| `area` | string | `frontend` | `frontend`, `adminhtml` |
| `verbosity` | string | `standard` | `minimal`, `standard`, `detailed` |

Includes a `_skill_hint` pointing to `development-context category=frontend`.

### `ui-component-inspect`

Resolves an admin grid or form UI component's runtime-merged configuration — component tree, data source, columns/fieldsets, and child components — merged across all modules.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | *required* | UI component name, e.g. `customer_listing` |
| `verbosity` | string | `standard` | `minimal`, `standard`, `detailed` |

Includes a `_skill_hint` pointing to `development-context category=ui-component`.

## Message Queue

### `message-queue-inspect`

Returns the runtime-merged message-queue wiring: consumers, topics, queue/exchange bindings, publishers, and handlers assembled across `communication.xml`, `queue_consumer.xml`, `queue_topology.xml`, and `queue_publisher.xml` of every module. Each section degrades independently if a sub-config is absent.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `consumer` | string | `""` | Filter consumers by name substring (also scopes the topic-keyed sections) |
| `topic` | string | `""` | Filter topics/bindings/publishers by name substring |
| `verbosity` | string | `standard` | `minimal`, `standard`, `detailed` |

Includes a `_skill_hint` pointing to `development-context category=message-queue`.
