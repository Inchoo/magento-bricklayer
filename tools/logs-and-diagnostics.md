# Logs & Diagnostics Tools

Tools for reading logs, diagnosing errors, and analyzing performance.

## Log Tool

### `log`

Unified log tool with 4 actions.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `action` | string | *required* | `read`, `list`, `search`, `analyze` |
| `logType` | string | `system` | `system`, `exception`, `debug`, `cron` |
| `lines` | int | 100 | Lines to read (read action) |
| `filter` | string | `""` | Text filter (read action) |
| `query` | string | `""` | Search query (search action) |
| `maxResults` | int | 50 | Max results per file (search action) |
| `hours` | int | 24 | Analyze window in hours |
| `max_entry_length` | int | 0 | Truncate long entries (0 = no limit) |

**Actions:**
- **`read`** — Read recent log entries from a specific log file
- **`list`** — List available log files with sizes
- **`search`** — Search across log files by query pattern
- **`analyze`** — Aggregate and summarize errors from the last N hours

**Tip:** Start with `max_entry_length=500` to avoid overwhelming output, then increase if you need full stack traces.

## Diagnostic Tools

### `diagnose-error`

Diagnose recent Magento errors with fix suggestions.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `source` | string | `exception` | `exception`, `system`, `debug`, `cron` |
| `index` | int | 0 | Error index (0 = most recent) |
| `pattern` | string | `""` | Filter by error message substring |
| `since` | string | `1h` | Time window (`5m`, `1h`, `24h`, `7d`) |
| `verbosity` | string | `standard` | `minimal`, `standard`, `detailed` |

**Provides:**
- Module context analysis
- DI configuration inspection
- Plugin and preference discovery
- Error frequency history
- Actionable fix suggestions with confidence levels (high/medium/low)
- Recognition of 15+ common Magento error patterns

### `diagnose-performance`

Analyze Magento performance across 6 areas.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `check` | string | `all` | `all`, `indexes`, `cache`, `flat-tables`, `cron-backlog`, `config`, `queries` |

**Checks:**
- **`indexes`** — Invalid or outdated indexer status
- **`cache`** — Disabled cache types
- **`flat-tables`** — Flat catalog configuration
- **`cron-backlog`** — Pending/stuck cron jobs
- **`config`** — Suboptimal system configuration
- **`queries`** — Slow query patterns

Each finding includes severity (`info`, `warning`, `critical`) and fix suggestions.
