---
name: Magento Bricklayer (contributing)
description: Orientation for an AI agent working ON the magento-bricklayer source — how the codebase is laid out, how tools/prompts/resources are registered and auto-discovered, how to add or change one, the QA gates, and the design rule every change is judged against. This is for modifying Bricklayer itself, not for using it against a Magento store (that is the README + wiki).
---

# Magento Bricklayer — Working on the Codebase

Bricklayer is a Symfony Console application + MCP (Model Context Protocol) server that
exposes a running Magento 2 installation to AI coding agents. It is a **standalone Composer
library** (`Inchoo\MagentoBricklayer`), not a Magento module — it has no `module.xml`,
registers nothing in Magento's DI graph, and bootstraps Magento the way a CLI tool does.

This file orients an agent that is **editing Bricklayer's own source**. If you are looking
for how to *use* Bricklayer's tools against a Magento store, that lives in `README.md` and
`docs/wiki/` — not here.

---

## Rule zero: never hardcode a count

Bricklayer's most important internal discipline is **zero drift**: every count (tools,
prompts, resources, categories, gated tools…) is derived from source at runtime, never
maintained by hand. `ToolRegistry::count()` feeds the server instructions; `ConfigValidator::getKnownTools()`
and `ConfigInitializer::discoverConfigurableTools()` discover the tool set by reflecting
`#[McpTool]` attributes and scanning `requireToolEnabled()` call sites. No hand-written list
exists, so nothing can fall out of sync with the code.

**This applies to you too.** Don't write a tool/prompt/resource/category count into this
file or anywhere in the docs — it isn't needed to work on the code and it goes stale on the
next change. Describe structure, not quantity. If you genuinely need a current number (e.g.
asserting one in a test, or touching server-instruction logic), derive it:

```bash
# tool classes / total tools
grep -rl '#\[McpTool' src/Mcp/Tool --include='*.php' | wc -l        # classes (approx; see note)
grep -rho '#\[McpTool' src/Mcp/Tool --include='*.php' | wc -l       # total tools (approx)

# visible vs hidden  (hidden tools carry meta: ['hidden' => true])
grep -rc "'hidden' => true" src/Mcp/Tool/*.php

# prompts / resources
grep -rho '#\[McpPrompt'   src/Mcp/Prompt   | wc -l
grep -rho '#\[McpResource\b' src/Mcp/Resource | wc -l

# config-gated tools (distinct names)
grep -rhoE "requireToolEnabled\(\s*'[a-z0-9-]+'" src/Mcp/Tool | sort -u | wc -l
```

> **Grep is approximate for attributes.** PHP 8 attributes can span multiple lines and a bare
> `grep '#[McpTool'` will also match the one inside a docblock comment in
> `src/Mcp/Prompt/AbstractPrompt.php` (it has no real prompt methods — it's a transparent
> base). For an exact count, parse with attribute-awareness (walk the parens, skip comment
> lines) rather than counting raw matches. When in doubt, reflect.

---

## Source map

```
magento-bricklayer/
├── bin/
│   ├── bricklayer              # Symfony Console entry (config:set, init, install, mcp, inspect, update, verify)
│   ├── bricklayer-mcp          # bare MCP server entry (no Console overhead) — what agents launch
│   └── bricklayer-mcp-docker   # bash wrapper: auto-detects the PHP container, runs the server inside it
├── src/
│   ├── Application.php          # registers the console commands
│   ├── Bootstrap/              # MagentoBootstrap (init + staleness), MagentoDetector, AreaEmulator
│   ├── Command/                # one class per CLI command + AbstractBricklayerCommand base
│   ├── Config/                 # ConfigLoader, ConfigValidator, ConfigInitializer, EnvironmentResolver,
│   │                           #   IteratesToolPhpFiles (shared tool-file walker)
│   ├── Exception/              # Bricklayer / Bootstrap / Configuration / MagentoNotFound
│   ├── Guidelines/             # GuidelinesCompiler (generates CLAUDE.md/.cursorrules/…),
│   │                           #   LocalOverrideHelper (.bricklayer/ frontmatter), ToolScanner (reflection)
│   ├── Integration/            # McpConfigWriter (writes .mcp.json / .idea/mcp.json)
│   ├── Support/                # CollectsMarkdownFiles (namespace-neutral .md walker)
│   └── Mcp/
│       ├── McpServerFactory.php # builds the server: dynamic instructions, ServerCapabilities, pagination=200
│       ├── Tool/               # ← the bulk of the surface: one class per domain
│       │   ├── *Tools.php       # one class per domain (CatalogTools, OrderTools, …)
│       │   ├── ToolRegistry.php # singleton: TOOL_GROUPS map + reflection scan + count()
│       │   ├── TimeUnits.php    # relative-time strings → seconds/cutoff/hours
│       │   ├── Concern/         # shared tool traits (see "Trait toolbox")
│       │   └── Diagnostic/      # ExceptionParser for diagnose-error
│       ├── Prompt/             # #[McpPrompt] classes + AbstractPrompt base
│       └── Resource/           # #[McpResource]/#[McpResourceTemplate] classes + FileLoaderTrait
├── config/
│   ├── guidelines/             # markdown guideline corpus, grouped: areas/ core/ database/ ecosystem/ modules/ patterns/
│   └── skills/                 # SKILL.md knowledge modules served by development-context
├── docs/wiki/                  # human-facing developer docs (git subtree, Makefile push/pull/diff) — NOT agent-facing
├── tests/Unit/                 # mirrors src/ layout; *Test.php; PHPUnit 10/11
├── composer.json               # deps + QA scripts; PSR-4 Inchoo\MagentoBricklayer\ => src/
├── phpstan.neon.dist           # level 8 over src + tests
├── phpunit.xml.dist
├── README.md                   # human/usage docs (large)
└── SKILL.md                    # this file
```

Every PHP file starts with the same header and `declare(strict_types=1)`:

```php
<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;
```

---

## How registration and discovery work

There is **no manual wiring**. The MCP SDK's attribute discoverer scans the configured
directories and registers every method carrying an MCP attribute. Three attributes, imported
from `Mcp\Capability\Attribute\*`:

| Attribute | Where | Registers |
|-----------|-------|-----------|
| `#[McpTool(name, description, meta?)]` | `src/Mcp/Tool/*.php` | a callable tool |
| `#[McpPrompt(...)]` | `src/Mcp/Prompt/*.php` | a prompt template |
| `#[McpResource(...)]` / `#[McpResourceTemplate(...)]` | `src/Mcp/Resource/*.php` | a static / templated resource |

Two consequences a contributor must internalize:

- **The discoverer only indexes methods declared directly on a concrete class.** That is why
  `AbstractPrompt` can be an `abstract class` with shared formatting helpers and stay invisible
  to prompt discovery — it has no `#[McpPrompt]` methods of its own.
- **Visibility is just metadata.** A tool with `meta: ['hidden' => true]` is omitted from
  `tools/list` but remains fully callable by name. Visible (tier-1) tools simply omit the
  `meta` argument. `search-tools` is the gateway agents use to find hidden ones.

`ToolRegistry` (`src/Mcp/Tool/ToolRegistry.php`) is a separate, reflection-driven index used
for grouping and counting. It holds `TOOL_GROUPS` — a map of group name → tool classes — and
exposes `all()` / `count()` / `getToolGroups()`. The current groups are: `introspection`,
`catalog`, `orders`, `customers`, `database`, `logs`, `diagnostic`, `graphql`, `development`,
`code-generation`, `context`. `search-tools` filters on these.

---

## Adding or changing a tool

Tools are grouped by domain into a single class (`CatalogTools`, `OrderTools`, …). To add one:

1. **Pick the right class** for the domain. Add a `public function` returning `array`.
2. **Declare the attribute.** Visible tool:
   ```php
   #[McpTool(
       name: 'product-get',
       description: 'Get product by SKU. Use fields to limit response.'
   )]
   public function getProduct(string $sku = '', int $storeId = 0, string $fields = ''): array
   ```
   Hidden (tier-2) tool — add the `meta` arg:
   ```php
   #[McpTool(
       name: 'product-list',
       description: '…',
       meta: ['hidden' => true]
   )]
   ```
   Keep descriptions **≤ ~80 words** and front-load a "when to use" trigger — agents only
   reliably reach for tools whose description tells them when to.
3. **Apply the right guards, in order, at the top of the method.** This order is a convention
   the whole codebase follows:
   ```php
   if ($error = $this->requireMagento())            return $error; // Magento bootstrapped?
   if ($error = $this->requireToolEnabled('foo'))   return $error; // enabled in .bricklayer.json?
   if ($error = $this->requireNonProduction('foo')) return $error; // destructive + production?
   ```
    - **Pure read/introspection** (plugin-list, di-configuration, check-class…): only
      `requireMagento()`. No config gate — reading resolved DI or a plugin chain is non-destructive.
    - **Write or cost-sensitive** (creates/updates, `database-query`, `log`, `diagnose-performance`,
      `code-runner`): add `requireToolEnabled('foo')`. The act of adding this call is what makes
      the tool config-gated — `ConfigInitializer` discovers it by scanning for the call site, so
      **no list anywhere needs updating.**
    - **Destructive** (deletes, refunds, codegen): also add `requireNonProduction('foo')` **and**
      add the tool name to `ConfigInitializer::DESTRUCTIVE_TOOLS` so it ships `enabled: false`.
4. **Mix in the traits you need** (see below) via `use` at class scope.
5. **Register the class in `ToolRegistry::TOOL_GROUPS`** under the appropriate group, so
   `search-tools` can group/discover it.
6. **Write a `*Test.php`** under the mirrored `tests/Unit/Tool/` path.

For **consolidated tools** (one tool, many behaviours) follow the existing pattern: a routing
parameter + a `match` expression — e.g. `graphql-inspect target=…`, `system-status check=…`,
`log action=…`, `application-info include=…`. Prefer consolidating over adding a new visible
tool; the visible surface is deliberately small.

---

## Trait toolbox (`src/Mcp/Tool/Concern/`)

The Concern traits provide the cross-cutting behaviour. Reach for these instead of
re-implementing — consistency here is enforced by review.

| Trait | Provides |
|-------|----------|
| `RequiresMagento` | `requireMagento()` — lazy bootstrap check + auto-reinit when sentinel files change |
| `ChecksConfig` | `requireToolEnabled()`, `requireNonProduction()`, `isProductionMode()`; config staleness auto-reload via `getConfigLoader()` |
| `RespondsWithErrors` | the canonical error envelope (this replaced the old `ToolException`, now removed) |
| `PaginatesResults` | the canonical paginated list envelope (`has_more`, etc.) — use on every list tool |
| `FiltersFields` | the optional `fields` whitelist parameter |
| `MasksSensitiveConfig` | single source of truth for the sensitive-prefix list + path masking — use anywhere config values can surface |
| `ReadsLogFiles` | tail-reading, entry parsing, time-cutoff, truncation; honors `log.max_lines` |
| `RequiresValidVerbosity` | validates the `minimal`/`standard`/`detailed` enum |
| `ResolvesPackagePaths` | package-root / Magento-root constructor logic (for testability) |
| `SecureArea` | runs admin-context operations inside `isSecureArea=true` and unwinds after |

Cross-namespace helpers worth knowing: `TimeUnits` (`Mcp/Tool/`), `CollectsMarkdownFiles`
(`Support/`), `IteratesToolPhpFiles` (`Config/`), `AbstractBricklayerCommand` (`Command/`),
`AbstractPrompt` (`Mcp/Prompt/`), `LocalOverrideHelper` (`Guidelines/`).

---

## The other MCP subsystems (where to edit)

**Prompts** (`src/Mcp/Prompt/`) — `#[McpPrompt]` methods on concrete classes grouped by domain
(`ModulePrompts`, `PluginPrompts`, …). `AbstractPrompt` is the shared text-formatting base and
must stay free of `#[McpPrompt]` methods. To add a prompt, add a method to the right class.

**Resources** (`src/Mcp/Resource/`) — `#[McpResource]` for fixed resources,
`#[McpResourceTemplate]` for templated ones. `FileLoaderTrait` + `Support\CollectsMarkdownFiles`
power the file-backed ones. The guidelines and skills resources **auto-discover `.md` files**
under `config/guidelines/` and `config/skills/` — so adding a guideline or skill file needs
**no code change**, only a new markdown file.

**Guidelines** (`config/guidelines/`) — the markdown corpus compiled into agent files
(`CLAUDE.md`, `.cursorrules`, …) by `GuidelinesCompiler`. Grouped into `areas/ core/ database/
ecosystem/ modules/ patterns/`. Pure content; edit the markdown.

**Skills** (`config/skills/`) — `SKILL.md` knowledge modules served on demand by the
`development-context` tool.

**Context categories** — the `development-context` map lives in `CATEGORY_MAP` in
`src/Mcp/Tool/ContextTools.php`. Each key maps to `{skills[], guidelines[], description, group}`.
On a call it loads the mapped files from `config/skills/` + `config/guidelines/`, strips SKILL.md
YAML frontmatter, prefers any `.bricklayer/` override, and appends category-specific
`_next_steps`. Add a category by adding a map entry plus the referenced markdown (the full
override contract is below).

---

## Project-local overrides (`.bricklayer/`)

A `.bricklayer/` directory at the **Magento root** (not in the package) lets a project extend
bundled content without forking. **The path is the contract** — nothing is registered in
`.bricklayer.json`. `GuidelinesCompiler` reads it, `ContextTools`/`SearchTools` resolve it, and
all of it flows through `Guidelines\LocalOverrideHelper` (frontmatter parse/strip).

| Path | Effect |
|------|--------|
| `.bricklayer/project-context.md` | appended to every generated agent file under `## Project-Specific Context` |
| `.bricklayer/decision-matrix.md` | extra rows merged into the "Before Modifying Magento Code" table (malformed lines skipped) |
| `.bricklayer/guidelines/<path>.md` | matches a bundled path → **override**; no match → **new** section |
| `.bricklayer/skills/<category>/SKILL.md` | matches a bundled category → **override**; else a **new** local-only category callable via `development-context category=<dir>` |

Local SKILL.md may carry YAML frontmatter (`name:`/`description:`), stripped before content
reaches agents and used as display metadata. `GuidelinesCompiler` tracks `appliedLocalOverrides[]`
vs `appliedLocalAdditions[]`; `update` reports both. The testability seam for this whole
subsystem is the optional `$magentoRoot`/`$packageRoot` on the `GuidelinesCompiler`,
`ContextTools`, and `SearchTools` constructors — that is what the tests drive.

---

## CLI commands (`src/Command/`)

Registered in `Application.php`; `AbstractBricklayerCommand` provides shared Magento-root
resolution + IO.

| Command | Class | Responsibility |
|---------|-------|----------------|
| `install` | `InstallCommand` | detect env, write `.mcp.json` + agent guideline files (CLAUDE.md / .cursorrules / `.idea/mcp.json` / …) + `.bricklayer.json`, then run `verify` |
| `init` | `InitCommand` | write `.bricklayer.json` with deploy-mode-aware defaults; create the `.bricklayer/` dir |
| `config:set` | `ConfigSetCommand` | read/write a single `.bricklayer.json` value; interactive picker when run bare; warns on an ineffective key or a shadowing `BRICKLAYER_*` env var |
| `mcp` | `McpServerCommand` | run the server over stdio (honors `--magento-root` even without `pcntl`); `bin/bricklayer-mcp` is the bare-script equivalent |
| `inspect` | `InspectCommand` | print Magento version/edition/mode/module count/store hierarchy; `--json`, `--no-bootstrap` |
| `update` | `UpdateCommand` | regenerate agent files from bundled + `.bricklayer/` content; report applied overrides |
| `verify` | `VerifyCommand` | health check — each check is a private `check*` method (bootstrap, deploy mode, server build, agent configs, `.bricklayer.json`, PsySH, DB, log dir, code-runner gating, diagnose-error) |

`ConfigSetCommand`'s value parser: `true`/`false` → bool, `null` → null, numeric → int/float,
`[…]`/`{…}` → JSON, else string. Negative integers need the `--` separator (a Symfony Console
quirk), e.g. `config:set -- tools.x.max_rows -1`.

---

## Environment detection & config writing (`Bootstrap/`, `Integration/`, the docker wrapper)

`install` and the config writers target six environments. `MagentoDetector::getEnvironmentType()`
returns the **first** match:

| Env | Detected by |
|-----|-------------|
| `ddev` | `.ddev/config.yaml` |
| `hooli` | parent dir has both `hooli/` and `docker-compose.yml` |
| `warden` | `.warden/warden-env.yml` or `.env.warden` |
| `docker-compose` | `docker-compose.yml` / `docker-compose.yaml` / `compose.yml` |
| `docker` | `/.dockerenv` exists or `DOCKER_CONTAINER` is set |
| `native` | fallback |

`McpConfigWriter::writeMcpConfig($envType)` turns that into the `command`/`args` written to
`.mcp.json` (native → `php vendor/bin/bricklayer-mcp`; ddev → `ddev exec …`; warden →
`warden shell -c …`; docker-compose → `docker compose exec -T -u <user> …`). The container user
is resolved in `McpConfigWriter`, precedence: `BRICKLAYER_CONTAINER_USER` → owner of
`composer.json` (`fileowner` + `posix_getpwuid`) → Hooli `.env` `APACHE_USER`. For dynamic
container names, `bin/bricklayer-mcp-docker` finds the PHP container at runtime from `docker ps`
names — excluding `phpmyadmin|mysql|mariadb|redis|elastic|opensearch|varnish|mailhog|rabbitmq|database|nginx-proxy`,
then matching `apache-php|php-fpm|php|magento|web|app`. Changing detection or the emitted command
means touching these three together (`MagentoDetector`, `McpConfigWriter`, the wrapper).

---

## Config and security model (implementation view)

Configuration resolves **env var > `.bricklayer.json` > deploy-mode default**.

- `ConfigLoader` reads `.bricklayer.json` at the Magento root and, on every tool call (via
  `ChecksConfig::getConfigLoader()`), runs `reloadIfStale()` — an `filemtime()` check — so a
  mid-session edit takes effect on the next call with **no server restart**.
- `ConfigInitializer::generate()` writes the file. It calls `discoverConfigurableTools()`
  (scan for `requireToolEnabled()` call sites, via the shared `IteratesToolPhpFiles`), then
  `buildToolEntry()` per tool: a tool in `DESTRUCTIVE_TOOLS` ships `enabled:false`; `code-runner`
  is `enabled: !isProduction` with `allow_write:false`/`max_timeout:60`; `database-query.max_rows`
  is `50` in production / `100` otherwise; `log.max_lines:500`. One entry is written per gated
  tool — the file never contains a dead key.
- `EnvironmentResolver` maps a dotted key to an env var with a **fixed transform**:
  ```
  ENV = 'BRICKLAYER_' . strtoupper(str_replace(['.', '-'], '_', $key))
  ```
  So `tools.code-runner.enabled` → `BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED`. **The `TOOLS_`
  segment is required.** The short form `BRICKLAYER_CODE_RUNNER_ENABLED` resolves to a
  non-existent key and silently does nothing — this was a real bug (hyphens weren't being
  translated) fixed in v1.15.0; keep doc examples on the full-path form.

Security layers, innermost-relevant for contributors:

- **Fail closed.** `ChecksConfig::isProductionMode()` returns `true` if the deploy mode can't be
  read (the `catch` returns `true`). New destructive paths inherit this only if they call the
  guards above — so always use the guard helpers, never re-derive the mode yourself.
- **`code-runner`** is hard-blocked in production (no config override), runs through PsySH when
  `\Psy\Shell` exists (degraded `eval` fallback otherwise, with the active engine reported in
  `runtime`), defaults to read-only (`allow_write:false`, DB transaction rolled back), and
  rejects the dangerous-code blocklist (`DANGEROUS_PATTERNS` in `CodeRunnerTools`).
  Its six bare helpers (`get`/`create`/`repo`/`config`/`query`/`runLog`) must work in **both**
  runtimes and stay bound to the live ObjectManager across calls — a regression here is what
  v1.15.1 fixed, so any change touching helper registration needs a test under both engines.

---

## The design rule every change is judged against

> **Can an agent already do this without Bricklayer — via `code-runner` or plain file-reading?
> If yes, the feature needs strong justification.**

Bricklayer's irreplaceable value is **runtime introspection of a live install**: resolved DI
across all modules, real plugin execution order, deployed EAV, actual DB state, error
diagnostics with DI/plugin context. That is what file-reading and an LLM's priors cannot give.
CRUD wrappers (product-create, order-cancel, …) are conveniences around what `code-runner`
already does and carry their weight only as ergonomic shortcuts. When reviewing or proposing a
new tool, apply the runtime-vs-static test first. Bricklayer stays narrow on runtime behaviour
and leaves static analysis (LSP/Intelephense), code-location (Magector), and library docs
(Context7) to the complementary tools around it — do not absorb their jobs.

---

## QA gates

Defined as composer scripts (`composer.json`):

```bash
composer test           # phpunit
composer phpstan        # phpstan analyse src tests --level=8
composer phpcs          # phpcs --standard=PSR12 src tests
composer phpcbf         # auto-fix PSR-12
composer check          # phpcs + phpstan + test  (run before proposing a change)
```

`phpstan.neon.dist` is level 8 over `src` + `tests`, ignoring "Magento class not found" errors
(Magento isn't on the analysis path). `phpunit.xml.dist` runs the `Unit` suite from `tests/Unit`,
which **mirrors the `src/` tree** (`tests/Unit/Tool/`, `tests/Unit/Config/`, `tests/Unit/Command/`,
…) with `*Test.php` files.

> **Bootstrap gotcha.** `phpunit.xml.dist` sets `bootstrap="../../../vendor/autoload.php"` —
> i.e. it expects the package to live inside a Magento install's `vendor/inchoo/magento-bricklayer/`.
> Running the suite from a standalone clone may require pointing the bootstrap at the package's
> own `vendor/autoload.php`. Check this before assuming a test failure is real.

Tests lean on the testability seams in the code: tool/compiler/context constructors accept
optional `$magentoRoot`/`$packageRoot`, `McpServerFactory` exposes a protected `getToolCount()`
for doubles, and discovery is reflection-based so tests can assert against real attributes.

---

## Conventions and gotchas

- **`declare(strict_types=1)` + the Inchoo copyright header** on every PHP file; PSR-12; PSR-4
  autoload `Inchoo\MagentoBricklayer\ => src/`.
- **Counts are dynamic by design.** Don't introduce a hardcoded tool/category/prompt count
  anywhere — wire it to `ToolRegistry::count()` / a reflection scan instead. The server
  instructions in `McpServerFactory::getServerInstructions()` inject `{$toolCount}` and must
  stay that way.
- **`ERROR_PATTERNS`** is still **inline** in `DiagnosticTools.php` (a planned move to JSON did
  not ship). The `#[ToolGroup]` attribute does **not** exist and `ToolScanner` is
  **still present** — if a task description references either, it's describing a refactor that
  was never applied.
- **Sentinel-file staleness**: `MagentoBootstrap::SENTINEL_FILES` is exactly two —
  `app/etc/config.php` (module list; changes on `setup:upgrade`, `module:enable/disable`) and
  `generated/metadata/global.php` (compiled DI; changes on `setup:di:compile`).
  `RequiresMagento::requireMagento()` calls `reinitializeIfStale()` on every tool call (one
  `filemtime()` per file) and rebuilds the ObjectManager if either moved. The manual
  `reinitialize` tool (`DevelopmentTools`) covers edits that touch neither sentinel (e.g.
  `config.xml` without recompiling) and is also where defined `code-runner` functions are
  cleared, via a direct `CodeRunnerTools::clearDefinedFunctions()` call — there is no callback
  registry.
- `tests/Unit/Command/InstallCommandTest.php` mentions `ToolException` **on purpose** — it's a
  regression guard asserting the class no longer exists (`assertFalse(class_exists(...))`), not
  stale code. Leave it.
- The MCP SDK is pinned `mcp/sdk: ^0.3 || ^1.0` deliberately to avoid breaking protocol changes;
  `magento/framework` is intentionally **not** a dependency (runtime detection avoids version
  conflicts).

---

## Dependencies

`php >=8.1`, `mcp/sdk ^0.3 || ^1.0`, `psy/psysh ^0.12`, `symfony/console ^5.4 || ^6.0 || ^7.0`,
`psr/log ^2.0 || ^3.0`; dev: `phpunit ^10 || ^11`, `phpstan ^1.10`, `squizlabs/php_codesniffer ^3.7`.

The `mcp/sdk` pin and the deliberate absence of `magento/framework` are explained under
"Conventions and gotchas". For the current shape of the surface (how many tools, prompts,
categories, …) reflect or run the commands in "Rule zero" — it is intentionally not written
down here.
