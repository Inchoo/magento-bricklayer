# Tool System

The tool system is the core of Magento Bricklayer, providing 79 MCP tools organized into groups with progressive disclosure and shared behavior traits.

## Tool Registration

Tools are registered automatically via PHP 8.1 attributes:

```php
#[McpTool(
    name: 'product-get',
    description: 'Get product by SKU. Use fields to limit response.',
    group: 'catalog'
)]
public function getProduct(string $sku, string $fields = ''): array
```

`ToolRegistry` scans all tool classes in `src/Mcp/Tool/` at startup, collecting metadata (name, description, group, parameters) from `#[McpTool]` attributes.

## Tool Classes (21 classes)

| Class | Group | Tools |
|-------|-------|-------|
| `ApplicationTools` | introspection | application-info |
| `ConfigurationTools` | introspection | configuration-get, configuration-list, di-configuration, plugin-list, event-list, preference-list |
| `ModuleTools` | introspection | module-list, module-structure, validate-module |
| `EavTools` | introspection | eav-attributes, eav-entity-types |
| `RoutingTools` | introspection | route-list, route-info, api-endpoints, url-rewrites |
| `CatalogTools` | catalog | product-get/list/create/update/delete, product-stock-*, product-media-*, product-link-*, category-* |
| `OrderTools` | orders | order-*, invoice-*, shipment-*, creditmemo-* |
| `CustomerTools` | customers | customer-*, customer-address-* |
| `DatabaseTools` | database | database-schema, database-query |
| `LogTools` | logs | log (read/list/search/analyze) |
| `DiagnosticTools` | diagnostic | diagnose-error |
| `PerformanceTools` | diagnostic | diagnose-performance |
| `GraphqlTools` | graphql | graphql-inspect |
| `DevelopmentTools` | system | system-status, reinitialize |
| `CodeRunnerTools` | development | code-runner, code-runner-help |
| `SearchTools` | development | search-tools, search-docs |
| `BatchTools` | development | batch-execute |
| `CodeGenerationTools` | generation | generate-module/model/controller/api |
| `ContextTools` | context | development-context |

## Progressive Disclosure

To reduce token overhead for AI agents, tools are split into two tiers:

- **Tier 1 (16 tools)** — Always visible in `tools/list`. These are the most commonly needed tools.
- **Tier 2 (63 tools)** — Hidden from `tools/list` but fully callable. Discoverable via `search-tools`.

Tier 2 tools have `meta: ['hidden' => true]` in their registration.

## Shared Traits

| Trait | Purpose |
|-------|---------|
| `RequiresMagento` | Ensures Magento is bootstrapped; handles staleness detection |
| `ChecksConfig` | Validates tool is enabled; enforces production safety |
| `FiltersFields` | Implements `fields` parameter for response filtering |
| `ReadsLogFiles` | Shared log file reading with truncation support |

## Tool Response Patterns

All tools follow consistent response patterns:

- **List tools** include `has_more` boolean for pagination
- **Get tools** support `fields` parameter for response filtering
- **List tools** support `count_only=true` for size checking
- **Write tools** check `requireToolEnabled()` and `requireNonProduction()`
- **Context-aware hints** — `_hint` field guides agents to logical next steps

## Batch Execution

`batch-execute` runs up to 20 tool calls in a single request:

```json
[
  {"tool": "product-get", "params": {"sku": "SKU-001"}},
  {"tool": "product-get", "params": {"sku": "SKU-002"}}
]
```

Results are returned as an array with per-tool success/error status.
