# Diagnostics

Bricklayer provides diagnostic tools for identifying and resolving Magento issues.

## Error Diagnosis

### `diagnose-error`

Analyzes recent Magento exceptions and provides fix suggestions.

**Workflow:**

1. Reads the specified log file (exception, system, debug, cron)
2. Parses the error with context (module, class, method)
3. Inspects DI configuration, plugins, and preferences
4. Matches against 15+ known error patterns
5. Returns actionable fix suggestions with confidence levels

**Example:**

```
diagnose-error source="exception" since="1h"
```

**Output includes:**
- Error message and stack trace
- Module context (which module the error originates from)
- DI configuration for the affected class
- Active plugins that may interfere
- Error frequency (how often it occurred)
- Fix suggestions: high, medium, and low confidence

**Known patterns recognized:**
- Class not found / interface not found
- Plugin method signature mismatch
- Missing DI configuration
- Area code not set
- Invalid argument types
- Database constraint violations
- Cache-related issues
- And more

### Filtering

Use `pattern` to filter by error message:

```
diagnose-error pattern="Cannot instantiate" since="24h"
```

Use `index` to inspect older errors:

```
diagnose-error index=2  # Third most recent error
```

## Performance Diagnosis

### `diagnose-performance`

Runs 6 performance checks and returns findings with severity levels.

**Checks:**

| Check | What It Analyzes |
|-------|-----------------|
| `indexes` | Invalid or outdated indexer status |
| `cache` | Disabled cache types |
| `flat-tables` | Flat catalog configuration state |
| `cron-backlog` | Pending or stuck cron jobs |
| `config` | Suboptimal system configuration settings |
| `queries` | Slow query patterns |

**Example:**

```
diagnose-performance check="all"
```

**Finding format:**

Each finding includes:
- **Severity:** `info`, `warning`, `critical`
- **Description:** What was found
- **Fix:** Suggested remediation steps

## Log Analysis

### `log` with `action=analyze`

Aggregates error patterns from log files over a time window:

```
log action="analyze" hours=24
```

Returns error frequency counts, grouped by pattern, helping identify the most impactful issues.

## Recommended Debugging Workflow

1. **Start with `diagnose-error`** — First step for any Magento error; combines logs, DI context, and plugin chain analysis
2. **Run `check-class`** — If the error involves a specific class, check its full runtime picture (plugins, DI, preferences)
3. **Check `system-status`** — Verify cache, indexers, cron status
4. **Run `diagnose-performance`** — Identify performance bottlenecks
5. **Use `log action=search`** — Search for specific patterns across all log files
6. **Use `code-runner`** — Execute targeted PHP to inspect runtime state
