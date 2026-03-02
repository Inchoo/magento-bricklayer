# Security Overview

Bricklayer implements a defense-in-depth security model with multiple layers of protection for Magento installations.

## Security Layers

### 1. Production Safety System

Deploy-mode-aware defaults protect production environments:

| Category | Developer Mode | Production Mode |
|----------|---------------|-----------------|
| Code execution (`code-runner`) | Enabled | **Hard-blocked** (no override) |
| Destructive tools (delete, cancel) | Enabled | Disabled by default |
| Code generation | Enabled | Disabled by default |
| Read operations | Enabled | Enabled |
| Introspection tools | Enabled | Enabled |

**Fail-closed behavior:** When Magento bootstrap is unavailable, `isProductionMode()` returns `true`, blocking destructive tools by default rather than allowing them.

### 2. Per-Tool Granular Control

Every tool can be individually enabled or disabled in `.bricklayer.json`:

```json
{
    "tools": {
        "product-delete": { "enabled": false },
        "database-query": { "enabled": true, "max_rows": 50 }
    }
}
```

### 3. Query Safety

- **Read-only SQL** — Only `SELECT` statements are accepted
- **Table validation** — Table names are verified against actual database tables before use (prevents SQL injection)
- **Row limits** — Configurable `max_rows` prevents unbounded result sets
- **Sensitive path masking** — Configuration values for `payment/*`, `carriers/*`, `oauth/*`, and similar paths are masked in query results

### 4. Code Execution Safety

The `code-runner` tool has multiple safeguards:

- **Read-only by default** — Uses database transaction rollback unless `allow_write=true`
- **Blocked patterns** — 9 dangerous PHP patterns are detected and rejected (e.g., `exec()`, `system()`, `file_put_contents()`)
- **Path traversal protection** — `realpath()` boundary checks prevent file access outside the Magento root
- **No network access** — External HTTP requests are blocked
- **Timeout enforcement** — Configurable max execution time (default: 30s)
- **Hard-blocked in production** — Cannot be enabled even with explicit configuration

### 5. Code Generation Safety

- **File overwrite detection** — Existing files cause a conflict error instead of silent overwrite
- **Force parameter** — Explicit `force=true` required to overwrite existing files
- **Dry-run support** — Preview generated files without writing to disk
- **Production blocking** — All code generation tools are blocked in production mode

### 6. Log Safety

- **Line limits** — `max_lines` config prevents reading unbounded log data
- **Entry truncation** — `max_entry_length` parameter truncates long stack traces
- **Sensitive data** — Log content is returned as-is (no filtering) but access is controlled via `tools.log-reader.enabled`

## Recommended Production Configuration

```json
{
    "tools": {
        "code-runner": { "enabled": false },
        "database-query": { "enabled": false },
        "product-delete": { "enabled": false },
        "customer-delete": { "enabled": false },
        "category-delete": { "enabled": false },
        "generate-module": { "enabled": false },
        "generate-model": { "enabled": false },
        "generate-controller": { "enabled": false },
        "generate-api": { "enabled": false }
    }
}
```

## Related Pages

- [Configuration](../configuration/bricklayer-json) — Per-tool enable/disable
- [Code Runner](../tools/development) — Execution safety details
- [Database Tools](../tools/database) — Query restrictions
