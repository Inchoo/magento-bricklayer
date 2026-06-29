# Environment Variables

Environment variables provide the **highest-priority** configuration override, sitting above `.bricklayer.json` and the built-in deploy-mode defaults. They are useful for CI/CD pipelines and container environments where editing a file per-project is awkward.

## Naming Convention

Every `.bricklayer.json` config key maps to an environment variable. The transform is:

```
BRICKLAYER_ + UPPERCASE(config key, with every "." and "-" replaced by "_")
```

So a dot-notation config key becomes an env var like this:

| Config key (`.bricklayer.json`) | Environment variable |
|---------------------------------|----------------------|
| `tools.code-runner.enabled` | `BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED` |
| `tools.product-delete.enabled` | `BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED` |
| `tools.database-query.max_rows` | `BRICKLAYER_TOOLS_DATABASE_QUERY_MAX_ROWS` |
| `tools.code-runner.allow_write` | `BRICKLAYER_TOOLS_CODE_RUNNER_ALLOW_WRITE` |
| `tools.log.max_lines` | `BRICKLAYER_TOOLS_LOG_MAX_LINES` |

> **Hyphenated tools need the full path.** Because both `.` and `-` collapse to `_`, you must spell out the whole key — e.g. `BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED`, not a shortened form. Prior to 1.15.0 these overrides were silently ignored for hyphenated tools; they now work for every tool.

## Supported Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `BRICKLAYER_MAGENTO_ROOT` | Override Magento root directory detection | Auto-detected |
| `BRICKLAYER_TOOLS_<TOOL>_ENABLED` | Force a tool on/off, overriding `.bricklayer.json` and deploy-mode defaults | Per deploy mode |
| `BRICKLAYER_TOOLS_<TOOL>_<OPTION>` | Override a tool-specific option (e.g. `MAX_ROWS`, `MAX_LINES`, `ALLOW_WRITE`, `MAX_TIMEOUT`) | Per tool |

Values are parsed by type: `true`/`false` → bool, `null` → null, numeric → int/float, everything else → string.

### Examples

```bash
# Disable code-runner in a CI job regardless of the committed config
export BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED=false

# Raise the database query row cap for a one-off audit
export BRICKLAYER_TOOLS_DATABASE_QUERY_MAX_ROWS=500

# Re-enable a destructive tool that ships disabled by default
export BRICKLAYER_TOOLS_PRODUCT_DELETE_ENABLED=true
```

> **Note:** `code-runner` is **hard-blocked in production deploy mode** and that block cannot be lifted by any env var or config setting.

### Interaction with `config:set`

`bricklayer config:set` warns you when a matching `BRICKLAYER_*` env var is set that would shadow the file change you are making — the env var wins at runtime, so the warning prevents silent confusion.

## Container Detection

Bricklayer auto-detects container environments via these indicators:

| Variable | Environment |
|----------|-------------|
| `DDEV_HOSTNAME` | DDEV container |
| `WARDEN_ENV_NAME` | Warden container |
| `/.dockerenv` file | Generic Docker |

No configuration is needed for containerized setups — `EnvironmentResolver` handles detection automatically.

## Related Pages

- [.bricklayer.json](Configuration-Bricklayer-Json) — Project-level configuration
- [CLI Commands](cli-commands) — `config:set` for validated single-value edits
- [Getting Started](getting-started) — Installation and setup
