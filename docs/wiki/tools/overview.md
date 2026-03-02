# Tools Reference

Magento Bricklayer provides 79 MCP tools organized into 11 groups. Use `search-tools` at runtime to discover tools by keyword.

## Tool Groups

| Group | Tools | Description |
|-------|-------|-------------|
| [Introspection](introspection) | 16 | Application info, configuration, modules, EAV, routing |
| [Catalog](catalog) | 18 | Products, categories, stock, media, links |
| [Orders](orders) | 14 | Orders, invoices, shipments, credit memos |
| [Customers](customers) | 12 | Customers, addresses, groups, validation |
| [Database](database) | 2 | Schema inspection and read-only queries |
| [Logs](logs) | 1 | Log reading, searching, and analysis (4 actions) |
| [Diagnostic](diagnostic) | 2 | Error diagnosis and performance analysis |
| [GraphQL](graphql) | 1 | Schema inspection (types, queries, mutations) |
| [Development](development) | 3 | Code runner, search tools, batch execution |
| [Code Generation](../code-generation/overview) | 4 | Module, model, controller, API scaffolding |
| [Context](../guidelines/overview) | 1 | Development guidelines loader |

## Tier 1 Tools (Always Visible)

These 16 tools appear in the MCP `tools/list` response:

| Tool | Description |
|------|-------------|
| `application-info` | Magento/PHP version, deploy mode, store hierarchy |
| `database-schema` | Table structure with columns, indexes, foreign keys |
| `database-query` | Read-only SELECT query execution |
| `eav-attributes` | EAV attributes by entity type |
| `di-configuration` | DI preferences and plugins for a class |
| `plugin-list` | All plugins for a specified class |
| `preference-list` | All class preferences (rewrites) |
| `product-get` | Get product by SKU |
| `order-get` | Get order by increment ID |
| `customer-get` | Get customer by email |
| `code-runner` | Execute PHP in Magento context |
| `code-runner-help` | Code runner documentation |
| `search-tools` | Discover tools by keyword |
| `development-context` | Load coding guidelines by category |
| `batch-execute` | Run multiple tools in one call |
| `diagnose-error` | Diagnose errors with fix suggestions |

## Tier 2 Tools (Discoverable)

The remaining 63 tools are hidden from `tools/list` to reduce token overhead but remain fully callable. Discover them using:

```
search-tools query="product" detail="summary"
```

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
