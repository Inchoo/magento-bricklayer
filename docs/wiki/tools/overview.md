# Tools Reference

Magento Bricklayer provides 83 MCP tools organized into 11 groups. Use `search-tools` at runtime to discover tools by keyword.

## Tool Groups

| Group | Tools | Description |
|-------|-------|-------------|
| [Introspection](tools/introspection) | 19 | Application info, configuration, modules, EAV, routing, view layer, message queue |
| [Catalog](tools/catalog) | 18 | Products, categories, stock, media, links |
| [Orders](tools/orders) | 14 | Orders, invoices, shipments, credit memos |
| [Customers](tools/customers) | 12 | Customers, addresses, groups, validation |
| [Database](tools/database) | 2 | Schema inspection and read-only queries |
| [Logs](tools/logs-and-diagnostics) | 1 | Log reading, searching, and analysis (4 actions) |
| [Diagnostic](tools/logs-and-diagnostics) | 2 | Error diagnosis and performance analysis |
| [GraphQL](tools/graphql) | 1 | Schema inspection (types, queries, mutations) |
| [Development](tools/development) | 3 | Code runner, search tools, batch execution |
| [Code Generation](code-generation/overview) | 4 | Module, model, controller, API scaffolding |
| [Context](guidelines/overview) | 1 | Development guidelines loader |

## Tier 1 Tools (Always Visible)

These 17 tools appear in the MCP `tools/list` response:

| Tool | Description |
|------|-------------|
| `check-class` | Essential pre-check — combined plugins, DI, and preferences for any class |
| `application-info` | Magento/PHP version, deploy mode, store hierarchy |
| `database-schema` | Check BEFORE writing db_schema.xml — actual table structure |
| `database-query` | Read-only SELECT query execution |
| `eav-attributes` | Check BEFORE working with product/customer data — all attributes including DB-only |
| `di-configuration` | Check BEFORE modifying DI — runtime-resolved config from all modules |
| `plugin-list` | Check BEFORE writing a plugin — existing plugins with sortOrder |
| `preference-list` | Check BEFORE overriding a class — all preferences (rewrites) |
| `product-get` | Get product by SKU |
| `order-get` | Get order by increment ID |
| `customer-get` | Get customer by email |
| `code-runner` | Execute PHP in Magento context |
| `code-runner-help` | Code runner documentation |
| `search-tools` | Discover tools by keyword |
| `development-context` | Load coding guidelines BEFORE writing code |
| `batch-execute` | Run multiple tools in one call |
| `diagnose-error` | FIRST STEP for any error — combines logs, DI, and plugin analysis |

## Tier 2 Tools (Discoverable)

The remaining 66 tools are hidden from `tools/list` to reduce token overhead but remain fully callable. Discover them using:

```
search-tools query="product" detail="summary"
```

## Response Hints

Introspection tools include behavioral hints that guide agents through a check→learn→write workflow:

### `_skill_hint`

Returned by introspection tools (plugin-list, di-configuration, eav-attributes, database-schema, etc.) on success. Points the agent to the relevant `development-context` category to load next.

```json
{
  "class": "Magento\\Catalog\\Api\\ProductRepositoryInterface",
  "plugins": { ... },
  "_skill_hint": "For plugin development patterns: development-context category=plugin"
}
```

### `_next_steps`

Returned by `development-context` after loading guidelines. Suggests introspection tools the agent should call before writing code.

```json
{
  "category": "plugin",
  "_next_steps": [
    "Before writing your plugin: check-class className=TargetClass",
    "Check existing plugins and their sortOrder to avoid conflicts"
  ]
}
```

Categories that don't need introspection (coding-standards, security, testing) return `_next_steps: null`.

## Common Parameters

Many tools share these parameters:

| Parameter | Description | Example |
|-----------|-------------|---------|
| `fields` | Comma-separated field filter | `sku,name,price` |
| `count_only` | Return count instead of data | `true` |
| `pageSize` | Results per page | `20` |
| `currentPage` | Page number | `1` |
| `sortField` | Sort by field | `created_at` |
| `sortDir` | Sort direction | `DESC` |
| `verbosity` | Output detail level | `minimal`, `standard`, `detailed` |
