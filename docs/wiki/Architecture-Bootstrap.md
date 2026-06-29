# Bootstrap Process

The bootstrap process initializes a connection to a Magento 2 installation from outside the module system, enabling tool access to Magento's ObjectManager and service layer.

## Bootstrap Flow

```
1. MagentoDetector::findRoot()
   └── Searches for app/etc/env.php upward from CWD

2. MagentoBootstrap::initialize($rootDir)
   ├── Register Composer autoloader
   ├── Set Magento root constants (BP)
   ├── Initialize ObjectManager
   ├── Record sentinel file mtimes
   └── Return ObjectManager instance

3. RequiresMagento (on each tool call)
   ├── Check sentinel mtimes (staleness)
   ├── If stale → reinitialize ObjectManager
   └── Proceed with tool execution
```

## Magento Detection

`MagentoDetector` locates the Magento root by searching for `app/etc/env.php` starting from the current working directory and walking upward. This supports:

- Standard Magento installations
- Subdirectory-based projects
- Containerized environments where the working directory differs

## ObjectManager Initialization

The bootstrap follows the same pattern as `n98-magerun2`:

1. Requires Magento's `app/bootstrap.php`
2. Creates the `Bootstrap` instance
3. Obtains the `ObjectManager` from the application

This grants full access to Magento's dependency injection container without being registered as a Magento module.

## Staleness Detection

After initialization, Bricklayer records the modification times of sentinel files:

- `app/etc/config.php` — Changes when modules are enabled/disabled
- `generated/metadata/global.php` — Changes after `setup:di:compile`

Before every tool call, `RequiresMagento::requireMagento()` checks whether these files have changed. If staleness is detected, the ObjectManager is rebuilt automatically. This ensures tools always operate on the current application state after `setup:upgrade`, `setup:di:compile`, or `module:enable`.

## Area Emulation

`AreaEmulator` provides area code switching for tools that need specific Magento contexts:

- `adminhtml` — Admin panel operations
- `frontend` — Storefront operations
- `webapi_rest` — REST API context
- `graphql` — GraphQL context
- `crontab` — Cron execution context

The `code-runner` tool exposes this via its `area` parameter.

## Environment Resolution

`EnvironmentResolver` auto-detects containerized environments:

| Environment | Detection Method |
|-------------|-----------------|
| DDEV | `/.dockerenv` + `DDEV_HOSTNAME` env var |
| Warden | `/.dockerenv` + `WARDEN_ENV_NAME` env var |
| Docker | `/.dockerenv` file existence |
| Native | Fallback when no container detected |

The `bricklayer-mcp-docker` wrapper script adjusts execution paths when running inside containers.
