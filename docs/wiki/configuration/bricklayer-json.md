# Configuration: .bricklayer.json

The `.bricklayer.json` file controls per-project tool behavior, security settings, and guidelines.

## Configuration Hierarchy

Configuration is loaded in priority order (highest wins):

1. **Environment variables** — Override any setting
2. **Project config** — `.bricklayer.json` in project root
3. **Built-in defaults** — Safe defaults for all tools

## File Structure

```json
{
    "tools": {
        "code-runner": {
            "enabled": true,
            "allow_write": false,
            "max_timeout": 30
        },
        "database-query": {
            "enabled": true,
            "max_rows": 100
        },
        "product-delete": {
            "enabled": false
        },
        "generate-module": {
            "enabled": false
        }
    },
    "guidelines": {
        "include": ["core", "modules", "patterns"],
        "exclude": ["ecosystem/adobe-commerce"]
    },
    "agents": ["claude-code", "cursor"]
}
```

## Tool Configuration

Each tool supports an `enabled` boolean and tool-specific options:

### Code Runner

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true (dev) / false (prod) | Enable code execution |
| `allow_write` | bool | false | Allow write operations |
| `max_timeout` | int | 30 | Max execution time in seconds |

**Note:** `code-runner` is **hard-blocked** in production mode — it cannot be enabled even with explicit configuration.

### Database Query

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true | Enable SQL queries |
| `max_rows` | int | 100 | Maximum rows returned |

### Log Reader

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true | Enable log reading |
| `max_lines` | int | 1000 | Maximum lines per read |

### Destructive Tools

These tools are **disabled by default in production mode**:

| Tool | Type |
|------|------|
| `product-delete` | Catalog |
| `category-delete` | Catalog |
| `customer-delete` | Customer |
| `customer-address-delete` | Customer |
| `order-cancel` | Order |
| `creditmemo-create` | Order |

### Code Generation Tools

Disabled by default in production:

| Tool |
|------|
| `generate-module` |
| `generate-model` |
| `generate-controller` |
| `generate-api` |

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

The MCP server monitors `.bricklayer.json` mtime. Changes are picked up automatically on the next tool call without requiring a server restart.

## Deploy-Mode Defaults

| Setting | Developer | Production |
|---------|-----------|------------|
| `code-runner` | enabled | hard-blocked |
| `database-query` | enabled | enabled |
| Destructive tools | enabled | disabled |
| Code generation | enabled | disabled |

## Related Pages

- [Environment Variables](environment-variables) — Override settings via env vars
- [Security](../security/overview) — Production safety system
