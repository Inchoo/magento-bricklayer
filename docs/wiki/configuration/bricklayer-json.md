# Configuration: .bricklayer.json

The `.bricklayer.json` file controls per-project tool behavior and security settings.

## Editing the File

Two commands produce and maintain this file — you should rarely need to edit it by hand:

| Command | Purpose |
|---------|---------|
| `bricklayer init` | Generate a fresh file with deploy-mode-aware defaults (also runs during `bricklayer install`) |
| `bricklayer config:set` | Update a single value with validation, round-trip verification, and an interactive picker for discoverability |

The interactive `config:set` flow lists every runtime-configurable tool with its current state, lets you drill into a specific setting, and prompts for the new value with type-aware validation. See [CLI Commands → config:set](../cli-commands#bricklayer-configset).

## Configuration Hierarchy

Configuration is loaded in priority order (highest wins):

1. **Environment variables** — Override any setting via `BRICKLAYER_*` (see [Environment Variables](environment-variables))
2. **Project config** — `.bricklayer.json` in project root
3. **Built-in defaults** — Safe defaults for all tools

## File Structure

A generated developer-mode file contains **one entry per runtime-configurable tool** (35 entries total). Destructive tools default to `enabled: false`, everything else defaults to `enabled: true`:

```json
{
    "tools": {
        "code-runner": {
            "enabled": true,
            "allow_write": false,
            "max_timeout": 60
        },
        "database-query": {
            "enabled": true,
            "max_rows": 100
        },
        "log": {
            "enabled": true,
            "max_lines": 500
        },
        "diagnose-performance": { "enabled": true },
        "product-create": { "enabled": true },
        "product-update": { "enabled": true },
        "product-delete": { "enabled": false },
        "category-create": { "enabled": true },
        "category-delete": { "enabled": false },
        "order-cancel": { "enabled": false },
        "customer-delete": { "enabled": false },
        "generate-module": { "enabled": false }
    }
}
```

Every key in a generated file maps to a `requireToolEnabled()` call site in the source code — so the file never contains dead keys, and new configurable tools are picked up automatically by `bricklayer init` via a one-time source scan.

## Tool Configuration

Each tool supports an `enabled` boolean and tool-specific options:

### Code Runner

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true (dev) / false (prod) | Enable code execution |
| `allow_write` | bool | false | Allow write operations (false = changes rolled back) |
| `max_timeout` | int | 60 | Max execution time in seconds |

### Database Query

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true | Enable SQL queries |
| `max_rows` | int | 100 (dev) / 50 (prod) | Maximum rows returned per query |

### Log

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true | Enable log reading |
| `max_lines` | int | 500 | Maximum log lines returned per read |

### Diagnose Performance

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true | Enable performance diagnostics |

### Destructive Tools

These tools are **disabled by default in both developer and production modes**. They must be explicitly enabled (`enabled: true`) to run:

| Tool | Type |
|------|------|
| `product-delete` | Catalog |
| `category-delete` | Catalog |
| `customer-delete` | Customer |
| `customer-address-delete` | Customer |
| `order-cancel` | Order |
| `creditmemo-create` | Order |

### Code Generation Tools

Disabled by default in both developer and production modes:

| Tool |
|------|
| `generate-module` |
| `generate-model` |
| `generate-controller` |
| `generate-api` |

To enable any of these temporarily, run:

```bash
vendor/bin/bricklayer config:set tools.generate-module.enabled true
```

## Guidelines Configuration

Control which development guidelines are loaded:

```json
{
    "guidelines": {
        "include": ["core", "modules", "patterns", "database"],
        "exclude": ["ecosystem/adobe-commerce"]
    }
}
```

## Agent Configuration

Specify which agents to generate configuration files for:

```json
{
    "agents": ["claude-code", "cursor", "copilot", "jetbrains", "gemini"]
}
```

## Hot Reload

The MCP server monitors `.bricklayer.json` mtime. Changes are picked up automatically on the next tool call without requiring a server restart. This applies to both hand-edits and `config:set` updates.

After a successful `config:set` the command prints a reminder:

```
 ! [NOTE] Changes apply on the next MCP tool call — bricklayer hot-reloads
 !        .bricklayer.json automatically based on file mtime, so no agent
 !        restart is required. If a tool call is already in flight, it will
 !        finish with the old config; subsequent calls will see the new value.
```

## Deploy-Mode Defaults

| Setting | Developer | Production |
|---------|-----------|------------|
| `code-runner` | enabled (read-only) | disabled |
| `database-query.max_rows` | 100 | 50 |
| Destructive tools | disabled | disabled |
| Code generation | disabled | disabled |
| Total disabled tool count | 10 | 11 (adds `code-runner`) |

## Related Pages

- [Environment Variables](configuration/environment-variables) — Override settings via env vars
- [Security](security/overview) — Production safety system
