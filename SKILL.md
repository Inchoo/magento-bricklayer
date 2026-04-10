---
name: Magento Bricklayer
description: Comprehensive reference for AI agents on how magento-bricklayer works — its MCP tools, CLI, configuration, and decision patterns for common Magento 2 tasks.
---

# Magento Bricklayer

Bricklayer is a Symfony Console application + MCP (Model Context Protocol) server that
gives AI coding agents first-class access to a running Magento 2 installation. It is
implemented as a **standalone Composer library** (not a Magento module) — zero Magento
footprint, no `setup:upgrade` required, easy install/remove.

This skill orients an agent so it can choose the right tool for any Magento task without
guessing. Read the Quick Reference first, then drill into the specific section you need.

---

## Quick Reference

**When starting any Magento task, follow this order:**

1. **Load relevant context** — call `development-context category=<task-category>` before writing or modifying code. Always load `coding-standards` for any PHP file.
2. **Check runtime state** — Magento resolves DI, plugins, preferences, and events at runtime across modules. Reading source files alone misses these overrides. Use the tools listed in the [Introspection Decision Matrix](#introspection-decision-matrix).
3. **Prefer consolidated tools** — `check-class`, `diagnose-error`, `diagnose-performance`, `system-status`, `graphql-inspect`, and `code-runner` each replace multiple chained calls.
4. **Be token-conscious** — use `fields`, `count_only`, `verbosity=minimal` on list tools; use `code-runner` for multi-step operations; truncate logs with `max_entry_length`.

**80 MCP tools across 20 tool classes.** 16 are visible in `tools/list` (tier 1); the other 64 are discoverable via `search-tools` but callable by name at any time (tier 2).

---

## Architecture at a Glance

```
┌─────────────────────────────────────────────────────────────────┐
│  AI Agent (Claude, Cursor, Copilot, JetBrains AI, Gemini)       │
└────────────────────────┬────────────────────────────────────────┘
                         │ JSON-RPC 2.0 over stdio
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  bin/bricklayer-mcp  →  McpServerFactory  →  MCP Server         │
│                                                                  │
│  Auto-discovers:                                                │
│    - src/Mcp/Tool/*.php       → #[McpTool] methods              │
│    - src/Mcp/Resource/*.php   → #[McpResource] templates        │
│    - src/Mcp/Prompt/*.php     → #[McpPrompt] workflows          │
└────────────────────────┬────────────────────────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
 ┌────────────────┐ ┌──────────┐ ┌────────────────┐
 │ Tool Classes   │ │ Concerns │ │ Magento        │
 │ (20 files,     │ │ (5       │ │ Bootstrap      │
 │  80 tools)     │ │  traits) │ │ (ObjectManager)│
 └────────┬───────┘ └─────┬────┘ └────────┬───────┘
          │               │               │
          └───────────────┴───────────────┘
                         │
                         ▼
          ┌──────────────────────────────┐
          │ ConfigLoader ← .bricklayer.json│
          │              ← BRICKLAYER_* env│
          │              ← built-in defaults│
          └──────────────────────────────┘
```

**Key design principles:**

- **Progressive disclosure** — Tier-1 tools (16) cover the most common entry points; tier-2 tools (64) handle the long tail and are discoverable via `search-tools`.
- **Runtime-state first** — Every write/destructive tool goes through `ChecksConfig::requireToolEnabled()`. Every tool that touches Magento goes through `RequiresMagento::requireMagento()` (lazy + staleness-aware).
- **Hot reload everywhere** — `.bricklayer.json` mtime is checked on every tool call via `ConfigLoader::reloadIfStale()`. Sentinel files (`app/etc/config.php`, `generated/metadata/global.php`) trigger full ObjectManager reinitialization when they change on disk. **No agent restart is ever required when configuration or Magento state changes.**
- **Zero drift** — `ConfigValidator::getKnownTools()` and `ConfigInitializer::discoverConfigurableTools()` both discover tools from source (reflection on `#[McpTool]` attributes, source scan for `requireToolEnabled()` call sites) so no hand-maintained lists exist.

---

## The Seven CLI Commands

Bricklayer ships a Symfony Console application with **7 commands**:

| Command | Purpose |
|---------|---------|
| `bricklayer install` | One-shot setup: generates `.mcp.json`, `.bricklayer.json`, and agent-specific guideline files (CLAUDE.md, .cursorrules, etc.). Detects environment (DDEV, Warden, docker-compose, Hooli, Docker, native) and writes the right `mcp exec` command. Auto-runs `verify` at the end. |
| `bricklayer init` | Generates only `.bricklayer.json` with deploy-mode-aware defaults. 32 entries in developer mode (10 destructive tools disabled), 32 in production mode (11 disabled because `code-runner` joins the list, and `database-query.max_rows` drops to 50). Tool list discovered by scanning source for `requireToolEnabled()` call sites. |
| `bricklayer config:set` | Update a single `.bricklayer.json` value with validation, round-trip verification, and an **interactive picker** for discoverability. Run without args to walk through tool → setting → value. Supports bool picker, int validator that re-prompts on non-numeric input. Warns if a key is ineffective (e.g. `enabled` on a read-only tool) or if a `BRICKLAYER_*` env var would shadow the change. |
| `bricklayer mcp` | Starts the MCP server over stdin/stdout. Normally invoked by AI agents automatically via `.mcp.json`. Also available as `bin/bricklayer-mcp` (bare script without Symfony Console overhead). |
| `bricklayer inspect` | Shows Magento version, edition, PHP version, deploy mode, module count, store hierarchy. `--json` for machine output, `--no-bootstrap` to skip full DI load. |
| `bricklayer update` | Regenerates agent files (CLAUDE.md, .cursorrules, etc.) from current bundled content **plus any project-local overrides in `.bricklayer/`**. Reports which local files were applied. |
| `bricklayer verify` | Post-install health check: Magento bootstrap, deploy mode, MCP server creation + tool count, agent config files, PsySH for code-runner, DB connectivity, log writability, `.bricklayer.json` validation. Auto-generates `.bricklayer.json` if missing. `--json` for machine output. |

### Scripted vs. interactive config:set

```bash
# Interactive — walks through tool/setting/value
vendor/bin/bricklayer config:set

# Scripted — dot-notation key + value
vendor/bin/bricklayer config:set tools.product-delete.enabled true
vendor/bin/bricklayer config:set tools.database-query.max_rows 250
vendor/bin/bricklayer config:set tools.code-runner.allow_write false
vendor/bin/bricklayer config:set tools.log.max_lines 1000

# Negative integers need the `--` separator (Symfony Console quirk)
vendor/bin/bricklayer config:set -- tools.database-query.max_rows -1  # will fail validation
```

**Value parser**: `true`/`false` → bool, `null` → null, numeric → int/float, `[...]`/`{...}` → JSON-decoded, otherwise string.

---

## MCP Tools Inventory (80 tools, 20 classes)

### Tier-1 tools — always visible in `tools/list`

These 16 are the entry points. Everything else is discoverable through `search-tools` or callable by name.

| Tool | Class | Use when |
|------|-------|----------|
| `batch-execute` | BatchTools | You have 3+ similar calls to make (max 20) and want one round-trip |
| `check-class` | ConfigurationTools | Before modifying any class — returns plugins, DI config, and preferences in one call |
| `code-runner` | CodeRunnerTools | You need to run arbitrary PHP in Magento context — replaces chains of individual calls |
| `code-runner-help` | CodeRunnerTools | You're about to write `code-runner` PHP and need the helper reference (`get()`, `create()`, `repo()`, `config()`, area emulation) |
| `customer-get` | CustomerTools | Fetching a single customer |
| `database-query` | DatabaseTools | Read-only SELECT queries with automatic `LIMIT` enforcement |
| `database-schema` | DatabaseTools | Table structures, columns, indexes, foreign keys |
| `development-context` | ContextTools | **Always** before writing PHP — loads coding guidelines and task-specific patterns |
| `diagnose-error` | DiagnosticTools | Investigating any Magento error — orchestrates logs + DI + module context + suggestions |
| `di-configuration` | ConfigurationTools | Checking DI preferences and plugins for a specific class |
| `eav-attributes` | EavTools | Listing EAV attributes for `catalog_product`, `catalog_category`, `customer`, `customer_address` |
| `order-get` | OrderTools | Fetching a single order |
| `plugin-list` | ConfigurationTools | Listing all plugins/interceptors for a class (optionally filtered by method) |
| `preference-list` | ConfigurationTools | Listing class preference rewrites |
| `product-get` | CatalogTools | Fetching a single product |
| `reinitialize` | DevelopmentTools | Force fresh ObjectManager after config/DI changes when sentinel files didn't update |
| `search-tools` | SearchTools | Discovering tools by keyword or group. Use `detail=names` first for a lightweight overview, then `detail=full` only for tools you need |

### Tier-2 tools — hidden, callable by name, discoverable via `search-tools`

| Group | Tools |
|-------|-------|
| **Introspection** | `application-info`, `module-list`, `module-structure`, `validate-module`, `eav-entity-types`, `configuration-get`, `configuration-list`, `event-list`, `route-list`, `route-info`, `api-endpoints`, `url-rewrites` |
| **Catalog** | `product-list`, `product-create`, `product-update`, `product-delete`, `product-stock-get`, `product-stock-update`, `product-media-list`, `product-media-add`, `product-link-list`, `product-link-set`, `category-tree`, `category-get`, `category-create`, `category-update`, `category-delete`, `category-products`, `category-assign-products` |
| **Orders** | `order-list`, `order-items`, `order-comments`, `order-add-comment`, `order-cancel`, `order-hold`, `order-unhold`, `invoice-create`, `invoice-list`, `shipment-create`, `shipment-list`, `shipment-track-add`, `creditmemo-create`, `creditmemo-list` |
| **Customers** | `customer-list`, `customer-create`, `customer-update`, `customer-delete`, `customer-validate`, `customer-groups-list`, `customer-orders`, `customer-addresses`, `customer-address-create`, `customer-address-update`, `customer-address-delete` |
| **Development** | `system-status`, `search-docs` |
| **Diagnostics** | `diagnose-performance` |
| **Logs** | `log` (consolidated read/list/search/analyze) |
| **GraphQL** | `graphql-inspect` (types/queries/mutations/resolvers) |
| **Code Generation** | `generate-module`, `generate-model`, `generate-controller`, `generate-api` (all disabled by default — require explicit `enabled: true`) |

---

## Cross-cutting Concerns (Tool Traits)

Every tool class composes one or more of these traits from `src/Mcp/Tool/Concern/`. Understanding them makes the runtime behavior of any tool obvious.

| Trait | Provides | Returns an error when… |
|-------|----------|------------------------|
| `RequiresMagento` | `requireMagento()` — lazy validation, auto-reinit on sentinel-file changes | Magento is not bootstrapped |
| `ChecksConfig` | `requireToolEnabled($name)` — honors `.bricklayer.json`; `requireNonProduction($name)` — fail-closed production guard; `isProductionMode()` | Tool is `enabled: false` in config, OR tool is destructive and mode is production without explicit `enabled: true` |
| `ReadsLogFiles` | `readLastLines()`, `parseLogEntry()`, `calculateTimeCutoff()`, `truncateText()` — efficient tail-reading | — (helpers only) |
| `FiltersFields` | `filterFields($data, $fields)` — whitelist response fields via comma-separated string | — (helpers only) |
| `SecureArea` | `withSecureArea($callback)` — registers `isSecureArea=true` for protected delete operations | — (helpers only) |

### The guard order inside every write/destructive tool

```php
public function someWriteOperation(...): array {
    if ($error = $this->requireMagento())            return $error;  // Magento ready?
    if ($error = $this->requireToolEnabled('foo'))   return $error;  // .bricklayer.json allows?
    if ($error = $this->requireNonProduction('foo')) return $error;  // prod + unconfigured?
    // ... actual work
}
```

If you see an error like `"foo is disabled in configuration."`, the fix is:

```bash
vendor/bin/bricklayer config:set tools.foo.enabled true
```

The running MCP picks up the change on the very next tool call — no restart needed.

---

## Configuration: `.bricklayer.json`

### Priority order (highest wins)

1. **Environment variables** (`BRICKLAYER_*`)
2. **Project config** (`.bricklayer.json` at the Magento root)
3. **Built-in defaults** (`ConfigLoader::getDefaultConfig()`)

### Shape

A generated developer-mode file contains **32 entries** — one per runtime-configurable tool. Entries are discovered by scanning source for `requireToolEnabled()` call sites, so the file never contains dead keys.

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
        "category-delete": { "enabled": false },
        "customer-delete": { "enabled": false },
        "order-cancel": { "enabled": false },
        "creditmemo-create": { "enabled": false },

        "generate-module": { "enabled": false },
        "generate-model": { "enabled": false },
        "generate-controller": { "enabled": false },
        "generate-api": { "enabled": false }
    }
}
```

### Deploy-mode-aware defaults

| Setting | Developer | Production |
|---------|-----------|------------|
| `code-runner.enabled` | `true` | `false` |
| `code-runner.allow_write` | `false` | `false` |
| `code-runner.max_timeout` | `60` | `60` |
| `database-query.max_rows` | `100` | `50` |
| `log.max_lines` | `500` | `500` |
| Destructive tools | `false` | `false` |
| Code generation tools | `false` | `false` |
| Total disabled | 10 | 11 (adds `code-runner`) |

Destructive tools: `product-delete`, `category-delete`, `customer-delete`, `customer-address-delete`, `order-cancel`, `creditmemo-create`, and all 4 `generate-*`. These are disabled in **both** modes by default and must be explicitly enabled.

### Environment variable overrides

```bash
BRICKLAYER_MAGENTO_ROOT=/path/to/magento   # Override Magento root detection
BRICKLAYER_CODE_RUNNER_ENABLED=false       # Disable code-runner
BRICKLAYER_CODE_RUNNER_ALLOW_WRITE=false   # Enforce read-only globally
BRICKLAYER_CODE_RUNNER_MAX_TIMEOUT=30      # Cap timeout
BRICKLAYER_DATABASE_QUERY_MAX_ROWS=50      # Cap query results
BRICKLAYER_DEBUG=1                         # Verbose output
BRICKLAYER_CONTAINER_USER=www-data         # Override user in docker exec
```

`config:set` will warn if a matching `BRICKLAYER_*` variable is set, since env vars take priority over the file.

### Hot reload is automatic

`ChecksConfig::getConfigLoader()` calls `ConfigLoader::reloadIfStale()` on every tool invocation — a `filemtime()` check per call. Changes to `.bricklayer.json` are picked up **on the next MCP tool call**. There is no reason to restart the agent or the MCP server after a config change.

---

## Development Context (`development-context` tool)

Before writing or modifying any PHP file, call `development-context` with the relevant category to load coding guidelines and development patterns. **Always load `coding-standards` for any PHP file**, and then load one or more task-specific categories.

### The 38 bundled categories

| Group | Categories |
|-------|------------|
| **System & Quality** | `coding-standards` (always), `testing`, `security`, `performance`, `cron`, `indexer` |
| **Module Development** | `module`, `model`, `plugin`, `observer`, `preference`, `eav`, `data-patch` |
| **API & Integration** | `rest-api`, `graphql`, `payment`, `payment-gateway`, `payment-checkout`, `shipping`, `message-queue`, `import`, `export` |
| **Frontend & Admin** | `frontend`, `theme`, `theme-styling`, `checkout`, `checkout-advanced`, `adminhtml`, `ui-component`, `ui-component-form` |
| **Hyvä Theme** | `hyva-theme`, `hyva-theme-advanced`, `hyva-ui-component`, `hyva-ui-component-js`, `hyva-checkout`, `hyva-checkout-config`, `hyva-checkout-api`, `magewire` |
| **Meta** | `list` — returns the catalog of available categories |

### How the tool works

`ContextTools::getDevelopmentContext($category)`:

1. Looks up the category in `CATEGORY_MAP` (`src/Mcp/Tool/ContextTools.php`)
2. Each entry maps to `{skills: string[], guidelines: string[], description, group}`
3. Loads `.md` files from `config/guidelines/` and `config/skills/`
4. **Prefers project-local overrides** in `.bricklayer/skills/<category>/SKILL.md` and `.bricklayer/guidelines/<path>.md`
5. Strips YAML frontmatter from skill files before returning content
6. Appends task-specific next-step guidance (e.g. `plugin` → "check-class before modifying", `observer` → "event-list to find events")

### Cheap discovery pattern

```
search-docs query="how do I add a column"    # → lightweight keyword search returns pointer
development-context category=data-patch      # → full guidelines + skill content
```

`search-docs` is the scout (cheap); `development-context` is the full context loader (more tokens).

---

## Project-Local Overrides (`.bricklayer/` directory)

Anything a project places under `.bricklayer/` at the Magento root is picked up automatically by `bricklayer update`, `development-context`, and `search-docs`. **No registration in `.bricklayer.json` is required** — the path is the contract (modelled after Laravel Boost).

| Path | Purpose |
|------|---------|
| `.bricklayer/skills/<category>/SKILL.md` | Override a bundled skill, or add a new category. Optional YAML frontmatter with `name`/`description` keys. |
| `.bricklayer/guidelines/<path>.md` | Override a bundled guideline if `<path>` matches, or add a new guideline. No frontmatter. |
| `.bricklayer/project-context.md` | Appended under "Project-Specific Context" in every compiled agent file (CLAUDE.md, .cursorrules, etc.) |
| `.bricklayer/decision-matrix.md` | Extra rows appended to the "Before Modifying Magento Code" decision matrix. Must be valid markdown table row format. |

During compilation `GuidelinesCompiler` tracks `appliedLocalOverrides[]` and `appliedLocalAdditions[]` and reports them at the end of `bricklayer update`.

---

## Introspection Decision Matrix

Magento resolves DI, plugins, preferences, and events at runtime across modules. Reading source files alone misses overrides. **Before modifying any class, check runtime state first, then load the relevant guideline.**

| Task | Check runtime state | Then load guideline |
|------|---------------------|---------------------|
| Writing or modifying a **plugin** | `check-class className=Target\Class` | `development-context category=plugin` |
| Overriding/extending a class (**preference**) | `check-class className=Target\Class` | `development-context category=preference` |
| Injecting or changing **DI config** | `di-configuration className=Target\Class` | (based on findings) |
| Working with **product data** | `eav-attributes entityType=catalog_product` | `development-context category=eav` |
| Working with **customer data** | `eav-attributes entityType=customer` | `development-context category=eav` |
| Creating/modifying a **DB table** | `database-schema table=table_name` | `development-context category=data-patch` |
| Subscribing to an **event** | `event-list eventName=event_name` | `development-context category=observer` |
| Adding a **REST API endpoint** | `api-endpoints` | `development-context category=rest-api` |
| Writing a **GraphQL resolver** | `graphql-inspect target=types` | `development-context category=graphql` |
| Creating a **cron job** | `system-status check=cron` | `development-context category=cron` |
| Debugging **any error** | `diagnose-error` | (based on diagnosis) |
| Investigating **performance** | `diagnose-performance` | `development-context category=performance` |
| Writing **any PHP file** | — | `development-context category=coding-standards` (always) |

---

## Consolidated Tools (prefer these over chains)

These tools orchestrate multiple internal calls to give agents a complete answer in one round-trip.

### `check-class` — before modifying any class

Returns **plugins + DI config + preferences** for any class in a single call. This is the pre-flight check before writing a plugin, preference, or DI override.

```
check-class className="Magento\Catalog\Model\Product"
```

### `diagnose-error` — the first tool to call on any error

Orchestrates log parsing, DI analysis, plugin context, module inspection, and suggestion synthesis. Recognizes 15 common Magento error patterns (class-not-found, DI compilation, database, search engine, invalid templates/blocks, memory, sessions, etc.).

```
diagnose-error(index=0, source="exception", since="1h", pattern="", verbosity="standard")
```

**Returns**: `error` (parsed exception with chain), `module_context`, `di_context`, `environment`, `history`, `suggestions` (actionable fixes with confidence levels + CLI commands), and an optional `_hint`.

### `diagnose-performance` — system-wide performance audit

One call produces findings across indexes, cache, flat-tables, cron-backlog, config, and queries. Each finding has a severity (`info`/`warning`/`critical`) and a suggestion. Use `check` to scope:

```
diagnose-performance check=all            # everything
diagnose-performance check=indexes        # just indexer state
diagnose-performance check=cron-backlog   # just cron queue
```

### `system-status` — consolidated system check

```
system-status check=cache          # cache types and enabled state
system-status check=indexers       # indexer status + staleness
system-status check=deploy-mode    # developer/production/default
system-status check=cron           # last cron run + schedule health
system-status check=cron-history   # recent execution log
```

### `graphql-inspect` — consolidated GraphQL introspection

```
graphql-inspect target=types        # all types
graphql-inspect target=queries      # query fields
graphql-inspect target=mutations    # mutation fields
graphql-inspect target=resolvers    # resolver classes
graphql-inspect target=types name=Product  # detail on one type
```

### `log` — consolidated log tool

```
log action=list                                        # available log files
log action=read logType=exception lines=100            # tail N lines
log action=search query="DeadlockException" hours=24   # find across files
log action=analyze hours=24                            # frequency analysis
```

Use `max_entry_length=500` to cap verbose stack traces when tailing.

### `code-runner` — execute PHP in Magento context

The swiss-army knife for multi-step operations. Replaces chains of individual tool calls when you need computed or combined data.

```php
code-runner(code: "
    $p = repo(\Magento\Catalog\Api\ProductRepositoryInterface::class)->get('24-MB01');
    return [
        'sku' => $p->getSku(),
        'price' => $p->getPrice(),
        'stock' => get(\Magento\CatalogInventory\Api\StockRegistryInterface::class)
            ->getStockItemBySku('24-MB01')->getQty(),
    ];
")
```

**Helpers available inside `code-runner`:**

| Helper | Returns |
|--------|---------|
| `get($class)` | ObjectManager::get() — singletons |
| `create($class, $args)` | ObjectManager::create() — fresh instance |
| `repo($class)` | Shortcut for repository interfaces |
| `config($path)` | System config value for the given path |

**Parameters:**
- `code` (required) — PHP without `<?php` tags
- `area` — `frontend`, `adminhtml`, `webapi_rest`, `graphql`, `crontab`, `global`
- `allow_write` — default `false`; when false, DB changes are rolled back automatically
- `timeout` — seconds (default 30, cap via `tools.code-runner.max_timeout`)
- `mode` — `execute` (default) or `define` (save reusable functions for the session)

**Reusable functions** via `mode=define`:

```php
code-runner(mode="define", code="
    function getProductBySku($sku) {
        return get(\Magento\Catalog\Api\ProductRepositoryInterface::class)->get($sku);
    }
")

code-runner(code="$p = getProductBySku('24-MB01'); return $p->getName();")
```

Defined functions are validated against a 9-pattern dangerous-code blocklist and cleared on `reinitialize`. Max 20 defined functions per session.

**Call `code-runner-help` whenever you're about to write non-trivial `code-runner` PHP.**

### `batch-execute` — run multiple tools in one call

```
batch-execute operations=[
    {tool: "product-get", args: {sku: "24-MB01"}},
    {tool: "product-get", args: {sku: "24-WB01"}},
    {tool: "product-get", args: {sku: "24-WB02"}}
]
```

Max 20 operations per call. Individual failures don't abort the batch.

---

## Token Efficiency Patterns

Bricklayer gives you multiple knobs to keep context cost low. Use them.

### On list tools

```
module-list verbosity=minimal                              # just names, no vendor/version/status
product-list fields=sku,name,price                         # whitelist columns
eav-attributes entityType=catalog_product count_only=true  # just the count
configuration-list section=catalog count_only=true         # size-check before fetching
```

### On the `log` tool

```
log action=read logType=exception lines=50 max_entry_length=500
```

`max_entry_length=500` truncates each log entry's message — essential when tailing `exception.log` with long stack traces.

### On `search-tools`

```
search-tools query=product detail=names      # lightweight: just tool names
search-tools query=product detail=summary    # names + one-line descriptions
search-tools query=product detail=full       # names + descriptions + schemas (use sparingly)
```

Start with `detail=names`, then escalate only for the specific tools you need.

### Prefer consolidated tools over chains

One call to `check-class` replaces: `di-configuration` + `plugin-list` + `preference-list`. One call to `diagnose-error` replaces: `log action=read` + `module-structure` + `di-configuration` + `plugin-list` + manual analysis. One call to `code-runner` replaces arbitrary multi-step fetches.

### Use `code-runner` for loops

Instead of 10 `product-get` calls, loop inside `code-runner`:

```php
code-runner(code: "
    $repo = repo(\Magento\Catalog\Api\ProductRepositoryInterface::class);
    $out = [];
    foreach (['24-MB01', '24-WB01', '24-WB02'] as $sku) {
        $p = $repo->get($sku);
        $out[$sku] = ['name' => $p->getName(), 'price' => $p->getPrice()];
    }
    return $out;
")
```

### Use `search-docs` before `development-context`

`search-docs` is cheap and points you at the right category. Only call `development-context` once you know which category to load.

---

## Security Model

### Destructive tools blocked by default

`product-delete`, `category-delete`, `customer-delete`, `customer-address-delete`, `order-cancel`, `creditmemo-create`, and the 4 `generate-*` tools ship with `enabled: false` in both developer and production configurations. They must be explicitly enabled:

```bash
vendor/bin/bricklayer config:set tools.product-delete.enabled true
```

### Production mode is fail-closed

`ChecksConfig::isProductionMode()` **fails closed** — if the mode cannot be determined, it assumes production. In production, `requireNonProduction()` blocks destructive tools **unless** the config explicitly sets `enabled: true`. Without a config file or without the entry, they are blocked.

### Code-runner safety

- **Read-only by default** — when `allow_write=false` (default), Bricklayer wraps execution in a DB transaction that is rolled back after the code runs. Set `allow_write=true` in both the config and the individual call to persist changes.
- **Pattern blocklist** — 9 dangerous patterns are rejected: shell execution, file writes, superglobals, cURL, eval, header manipulation, global handler registration, long sleeps, and more.
- **Per-tool kill switch** — disable via `tools.code-runner.enabled=false` to turn the tool off entirely.
- **Timeout enforcement** — `tools.code-runner.max_timeout` caps per-call timeout.

### Query safety

- Database queries are `SELECT`-only with dangerous-pattern detection.
- Table names in schema queries are validated against actual database tables to prevent SQL injection.
- `tools.database-query.max_rows` enforces a hard cap (default 100 dev, 50 prod).
- Sensitive configuration values (`payment/*`, `carriers/*`, `oauth/*`, etc.) are automatically masked in query results.

### Log safety

- `tools.log.max_lines` enforces a hard cap on lines returned per read (default 500).
- `max_entry_length` parameter truncates long stack traces per entry.
- Access is gated via `tools.log.enabled`.

---

## Auto-Reinitialize: Stale Magento State

Bricklayer runs as a long-lived MCP server process that bootstraps Magento's ObjectManager once at startup. When external commands change the application state (`setup:upgrade`, `setup:di:compile`, `module:enable`), the in-memory ObjectManager can go stale.

Bricklayer tracks the mtimes of two sentinel files at startup:

- `app/etc/config.php` — changes on `setup:upgrade`, `module:enable/disable`
- `generated/metadata/global.php` — changes on `setup:di:compile`

On **every tool call**, `RequiresMagento::requireMagento()` calls `MagentoBootstrap::reinitializeIfStale()` which compares current mtimes against the snapshot. If either changed, Magento is reinitialized with a fresh ObjectManager — **no manual intervention required**. The staleness check costs two `filemtime()` calls (microseconds).

A manual `reinitialize` tool is also available for edge cases where sentinel files don't change (e.g. editing a module's `config.xml` without recompiling).

---

## Environment Detection

`MagentoDetector::getEnvironmentType()` inspects the project directory and returns one of:

| Environment | Detection |
|-------------|-----------|
| `ddev` | `.ddev/config.yaml` present |
| `hooli` | `../hooli` + `../docker-compose.yml` |
| `warden` | `.warden/warden-env.yml` or `.env.warden` |
| `docker-compose` | `docker-compose.{yml,yaml}` or `compose.yml` |
| `docker` | `/.dockerenv` file or `DOCKER_CONTAINER` env var |
| `native` | default fallback |

`McpConfigWriter::writeMcpConfig($envType)` uses the detected type to emit the correct `command`/`args` in `.mcp.json`:

```json
// Native
{"command": "php", "args": ["vendor/bin/bricklayer-mcp"]}

// DDEV
{"command": "ddev", "args": ["exec", "php", "vendor/bin/bricklayer-mcp"]}

// Warden
{"command": "warden", "args": ["shell", "-c", "php vendor/bin/bricklayer-mcp"]}

// Docker Compose (with user detection)
{"command": "docker", "args": ["compose", "exec", "-T", "-u", "www-data", "php", "php", "vendor/bin/bricklayer-mcp"]}
```

For dynamic container names, use `bin/bricklayer-mcp-docker` — a bash wrapper that auto-detects the PHP container by matching common patterns (`apache-php`, `php-fpm`, `magento`, `web`, `app`) while excluding utility containers (phpmyadmin, redis, elasticsearch, varnish).

**Container user detection** (in precedence order):
1. `BRICKLAYER_CONTAINER_USER` env var (explicit override)
2. POSIX owner of `composer.json`
3. Hooli `.env` `APACHE_USER` value
4. Default: `root`

---

## MCP Resources (5 exposed)

| Resource URI | Purpose |
|--------------|---------|
| `magento://guidelines/{category}/{name}` | Auto-discovers `.md` files in `config/guidelines/` — no code changes needed when adding guidelines |
| `magento://standards/coding`, `magento://standards/architecture` | MCGA (Magento Coding Guidelines) and architecture patterns |
| `magento://skills/{name}`, `magento://skills/index` | Auto-discovers `SKILL.md` files in `config/skills/` |
| `magento://templates/module` | Module structure templates with required files and boilerplate |
| `magento://reference/events` | Event reference, ACL patterns, layout XML, DI patterns |

Resources are read via the MCP resource API, not tool calls. Most agents don't need them directly — `development-context` and `search-docs` already consume them internally.

## MCP Prompts (8 code-generation workflows)

| Prompt | Purpose |
|--------|---------|
| `create-module` | Full module scaffold with vendor, module, version |
| `create-plugin` | Observer/plugin patterns guide |
| `create-controller` | Front/admin controller scaffolding with routing |
| `create-block` | Block class and template generation |
| `create-catalog-extension` | Product attribute/category extension guide |
| `create-api` | REST API endpoint + service contract setup |
| `create-order-extension` | Order/invoice custom logic patterns |
| `create-test` | PHPUnit and MFTF test generation |

Prompts return multi-turn message arrays that guide agents through code generation workflows.

---

## Common Workflows

### "I need to add a column to the customer table"

```
development-context category=data-patch   # guideline: declarative schema + data patches
database-schema table=customer_entity     # current structure
eav-attributes entityType=customer        # confirm it's not EAV (which uses different flow)
```

### "I'm getting a DI compile error"

```
diagnose-error                             # consolidated: logs + DI + suggestions
# If suggestion points at a specific class:
check-class className="Vendor\Module\Model\Thing"
```

### "I want to add a plugin to Magento\Catalog\Model\Product::getPrice"

```
development-context category=plugin        # load plugin guideline
check-class className="Magento\Catalog\Model\Product"  # existing plugins/preferences/DI
# Now write di.xml + Plugin class per the guideline
```

### "Production is slow"

```
diagnose-performance check=all             # full audit
# Drill into the biggest finding:
diagnose-performance check=indexes         # or check=cache / check=cron-backlog
system-status check=cron                   # is cron stuck?
```

### "I want to create 100 test products"

```
development-context category=coding-standards
# Use code-runner to batch-create — replaces 100 product-create calls:
code-runner(allow_write=true, code: "
    $repo = repo(\Magento\Catalog\Api\ProductRepositoryInterface::class);
    $factory = get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);
    $created = 0;
    for ($i = 1; $i <= 100; $i++) {
        $p = $factory->create();
        $p->setSku(\"test-$i\")
          ->setName(\"Test Product $i\")
          ->setTypeId('simple')
          ->setAttributeSetId(4)
          ->setPrice(rand(10, 100));
        $repo->save($p);
        $created++;
    }
    return ['created' => $created];
")
```

Note the **explicit `allow_write=true`** — without it, all 100 inserts get rolled back.

### "I need to search orders by custom criteria and compute a total"

```
# Don't chain order-list + filter on client side. Use database-query or code-runner:
database-query(query: "
    SELECT COUNT(*) AS cnt, SUM(grand_total) AS total
    FROM sales_order
    WHERE status = 'complete' AND created_at >= '2026-01-01'
")
```

### "I've changed `.bricklayer.json` — should I restart?"

**No.** The next tool call picks up the change automatically via `ConfigLoader::reloadIfStale()`. The same applies after running `bin/magento setup:upgrade` or `setup:di:compile` — `RequiresMagento::requireMagento()` will detect the sentinel file mtime change and reinitialize Magento on the next call.

---

## Extension Points

### Add a tool to an existing class

Add a new public method to any class in `src/Mcp/Tool/` and decorate it with `#[McpTool(name: '...', description: '...')]`. It's auto-registered. If it mutates state, guard it:

```php
if ($error = $this->requireMagento())            return $error;
if ($error = $this->requireToolEnabled('foo'))   return $error;
if ($error = $this->requireNonProduction('foo')) return $error;
```

`ConfigValidator::getKnownTools()` and `ConfigInitializer::discoverConfigurableTools()` will pick up the new tool on the next call — no manual list to update.

### Add a new tool class

Drop a new file in `src/Mcp/Tool/`. `McpServerFactory` auto-discovers it via `setDiscovery(__DIR__, ['Tool', 'Resource', 'Prompt'])`.

### Add a guideline

Drop a `.md` file in the appropriate `config/guidelines/<category>/` directory. `GuidelinesResource` auto-discovers it — no code change needed.

### Add a skill

Create `config/skills/<category>/SKILL.md` with optional YAML frontmatter (`name`, `description`). `SkillsResource` auto-discovers it.

### Add a project-local override (no fork needed)

Drop files under `.bricklayer/` at the Magento root:

```
.bricklayer/
├── skills/
│   └── my-team-pattern/
│       └── SKILL.md
├── guidelines/
│   └── core/
│       └── our-conventions.md
├── project-context.md
└── decision-matrix.md
```

Run `bricklayer update` to regenerate agent files with the overrides applied.

### Add a new `development-context` category

Add an entry to `ContextTools::CATEGORY_MAP` mapping the category name to `{skills, guidelines, description, group}`. Then create the referenced `SKILL.md` and guideline files.

---

## Troubleshooting Reference

| Symptom | Cause | Fix |
|---------|-------|-----|
| `"foo is disabled in configuration."` | `tools.foo.enabled: false` in `.bricklayer.json` | `bricklayer config:set tools.foo.enabled true` |
| `"foo is disabled in production mode. Set tools.foo.enabled=true in .bricklayer.json to override."` | Deploy mode is production and config lacks explicit `enabled: true` | As above. Think hard before enabling a destructive tool in prod. |
| `"Tool 'X' does not honor the 'enabled' flag at runtime"` | X is a read-only introspection tool that doesn't check `ChecksConfig`. The file edit will be silently ignored. | Don't edit it. The warning is telling you the change has no effect. |
| `Environment variable BRICKLAYER_* ... takes precedence` | Env var shadowing the file change | `unset BRICKLAYER_FOO_BAR` or remove from your shell profile |
| Config file changes don't take effect | Rare — likely an env var override | Check `env \| grep BRICKLAYER_`. If empty, the hot-reload is working on the next tool call. |
| "stale" Magento state after `setup:upgrade` | Sentinel file mtime hasn't changed or reinit races the tool call | Call the `reinitialize` tool once explicitly |
| `diagnose-error` returns nothing | The target error is not in the scanned time window | Expand `since=24h` or `since=7d` |
| `code-runner` changes don't persist | Missing `allow_write=true` on both the config and the call | `bricklayer config:set tools.code-runner.allow_write true` AND pass `allow_write=true` to the call |

---

## Appendix: Key Files & Classes

```
src/
├── Application.php                    # Symfony Console app — registers 7 commands
├── Bootstrap/
│   ├── MagentoBootstrap.php           # ObjectManager lifecycle + sentinel-file staleness
│   ├── MagentoDetector.php            # Walks dirs to find Magento root; detects env type
│   └── AreaEmulator.php               # Area code switching (frontend/adminhtml/webapi/...)
├── Command/
│   ├── ConfigSetCommand.php           # `bricklayer config:set` — interactive + scripted
│   ├── InitCommand.php                # `bricklayer init`
│   ├── InstallCommand.php             # `bricklayer install`
│   ├── InspectCommand.php             # `bricklayer inspect`
│   ├── McpServerCommand.php           # `bricklayer mcp`
│   ├── UpdateCommand.php              # `bricklayer update`
│   └── VerifyCommand.php              # `bricklayer verify`
├── Config/
│   ├── ConfigLoader.php               # Loads defaults + file + env vars, hot-reload via mtime
│   ├── ConfigValidator.php            # Reflection-based known-tools discovery
│   ├── ConfigInitializer.php          # Source-scan-based comprehensive config generator
│   └── EnvironmentResolver.php        # BRICKLAYER_* env var → config-key mapping
├── Guidelines/
│   ├── GuidelinesCompiler.php         # Compiles bundled + local guidelines into CLAUDE.md etc.
│   ├── LocalOverrideHelper.php        # Parses SKILL.md frontmatter; strips frontmatter
│   └── ToolScanner.php                # Scans MCP tool metadata for guideline embedding
├── Integration/
│   └── McpConfigWriter.php            # Writes .mcp.json for the detected environment
├── Mcp/
│   ├── McpServerFactory.php           # Wires up MCP server (tools + resources + prompts)
│   ├── Tool/                          # 20 tool classes, 80 tools
│   │   ├── Concern/                   # 5 shared traits (ChecksConfig, RequiresMagento, ...)
│   │   ├── Diagnostic/ExceptionParser.php  # Error-pattern recognition for diagnose-error
│   │   └── ToolRegistry.php           # Reflection-based tool discovery cache
│   ├── Resource/                      # 5 MCP resources (guidelines, skills, templates, ...)
│   └── Prompt/                        # 8 MCP code-generation prompts
└── Exception/                         # BricklayerException + 5 subclasses
    ├── BootstrapException.php
    ├── ConfigurationException.php
    ├── MagentoNotFoundException.php
    └── ToolException.php

config/
├── guidelines/                        # Bundled guidelines (core/, areas/, patterns/, modules/, database/, ecosystem/)
└── skills/                            # Bundled skills (28 categories, each with SKILL.md)

bin/
├── bricklayer                         # Symfony Console CLI entry point
├── bricklayer-mcp                     # Bare MCP server entry (no Symfony Console overhead)
└── bricklayer-mcp-docker              # Bash wrapper that auto-detects the PHP container
```

---

## One-Page Cheat Sheet

```
# Before modifying any class:
check-class className="Full\Qualified\Name"
development-context category=<plugin|preference|eav|observer|...>

# Always for any PHP file:
development-context category=coding-standards

# Before mutating data:
development-context category=data-patch   # for schema changes
eav-attributes entityType=<catalog_product|customer|...>

# Debugging:
diagnose-error                             # always the first step
log action=read logType=exception lines=50 max_entry_length=500

# Performance:
diagnose-performance check=all
system-status check=cron-history

# Multi-step data work (instead of chains):
code-runner-help                           # load helpers + area/allow_write docs
code-runner(code: "...", allow_write=<true|false>)

# Tool discovery:
search-tools query="..." detail=names      # start lightweight
search-tools query="..." detail=full       # only when needed

# Change configuration:
vendor/bin/bricklayer config:set            # interactive
vendor/bin/bricklayer config:set tools.X.Y <value>  # scripted
# Changes apply on next tool call — no restart needed.
```

**Golden rules:**

1. **Load context first**, then check runtime state, then write code.
2. **Check runtime state** via `check-class`, `eav-attributes`, `di-configuration`, `event-list`, etc. — never assume source files are the whole picture.
3. **Prefer consolidated tools** (`check-class`, `diagnose-error`, `diagnose-performance`, `code-runner`, `batch-execute`) over chains of individual calls.
4. **Use token-efficiency knobs** (`fields`, `count_only`, `verbosity`, `max_entry_length`, `detail`) on every list/read tool.
5. **Destructive operations are disabled by default** — enable explicitly via `config:set` when needed.
6. **No restart needed** after config or Magento state changes — hot-reload handles it.
