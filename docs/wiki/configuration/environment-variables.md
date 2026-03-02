# Environment Variables

Environment variables provide the highest-priority configuration override, useful for CI/CD pipelines and container environments.

## Supported Variables

Environment variables follow the pattern `BRICKLAYER_<SECTION>_<KEY>`:

| Variable | Description | Default |
|----------|-------------|---------|
| `BRICKLAYER_MAGENTO_ROOT` | Override Magento root directory detection | Auto-detected |

## Container Detection

Bricklayer auto-detects container environments via these indicators:

| Variable | Environment |
|----------|-------------|
| `DDEV_HOSTNAME` | DDEV container |
| `WARDEN_ENV_NAME` | Warden container |
| `/.dockerenv` file | Generic Docker |

No configuration is needed for containerized setups — `EnvironmentResolver` handles detection automatically.

## Related Pages

- [.bricklayer.json](configuration/bricklayer-json) — Project-level configuration
- [Getting Started](getting-started) — Installation and setup
