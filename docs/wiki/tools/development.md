# Development Tools

Tools for executing PHP code, discovering tools, searching documentation, and batch operations.

## Code Runner

### `code-runner`

Execute PHP in Magento's DI context.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `code` | string | *required* | PHP code to execute |
| `mode` | string | `execute` | `execute` or `define` |
| `allow_write` | bool | false | Enable write operations |
| `area` | string | `""` | Area emulation context |
| `timeout` | int | 30 | Max execution time in seconds |

**Built-in helpers:**

| Helper | Purpose |
|--------|---------|
| `$get(Class::class)` | Get singleton from DI |
| `$create(Class::class)` | Create new instance |
| `$repo(Interface::class)` | Repository shorthand |
| `$config('path')` | Read system configuration |
| `query('SELECT ...')` | Execute read-only SQL |
| `runLog($value, 'label')` | Capture values to response |

**Modes:**
- **`execute`** — Run code and return results. Read-only by default (uses transaction rollback).
- **`define`** — Save a named PHP function for reuse across calls. Max 20 per session.

**Security:** 9 dangerous code patterns are blocked. No network access. Path traversal protection.

### `code-runner-help`

Returns detailed code runner documentation. No parameters.

## Search & Discovery

### `search-tools`

Discover tools by keyword or group.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `query` | string | `""` | Keyword to search |
| `group` | string | `""` | Filter by tool group |
| `detail` | string | `summary` | `names`, `summary`, `full` |

Use `detail=names` first for a lightweight overview, then `detail=full` for specific tools.

### `search-docs`

Search Magento documentation topics.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `query` | string | *required* | Search query |
| `limit` | int | 10 | Max results |

## Batch Execution

### `batch-execute`

Run multiple tools in a single call (max 20).

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `operations_json` | string | *required* | JSON array of `{tool, params}` objects |

**Example:**

```json
[
  {"tool": "product-stock-update", "params": {"sku": "SKU-001", "qty": 100}},
  {"tool": "product-stock-update", "params": {"sku": "SKU-002", "qty": 50}}
]
```

Each operation returns its own success/error status. Cannot nest `batch-execute` or `code-runner`.
