# Changelog

## 1.16.0

A feature release adding runtime introspection for the view layer and message-queue wiring — three read-only tools that surface resolved/merged state no single source file shows. No breaking changes.

**New tools** (hidden, discover via `search-tools`)
- **`layout-inspect`** — resolve a layout handle into its runtime-merged block/container tree (merged across every module and the active theme), showing applied `referenceBlock`/`referenceContainer`/`move`/`remove` directives, declared and theme-resolved `.phtml` template paths, and the page layout. Omit the handle to list every registered handle for an area. Config-gated, enabled by default.
- **`ui-component-inspect`** — resolve an admin grid/form UI component's runtime-merged configuration (component tree, data source, columns/fieldsets, child components) merged across modules. Config-gated, enabled by default.
- **`message-queue-inspect`** — the runtime-merged consumer → topic → queue → exchange topology with publisher and handler bindings, assembled across `communication.xml`, `queue_consumer.xml`, `queue_topology.xml`, and `queue_publisher.xml`. Ungated.

**Removed**
- The static `layouts_reference` MCP resource (a hand-maintained handle catalogue) is retired in favour of `layout-inspect`'s runtime list mode — registered handles are now read from the live install.

**Counts:** 83 tools (was 80), 21 tool classes, 35 runtime-configurable tools, 3 reference resources.

## 1.15.1

- **`code-runner` bare helper functions now work in the PsySH runtime** — `get()`, `create()`, `repo()`, `config()`, `query()`, and `runLog()` (documented in `code-runner-help`) previously fataled with `Call to undefined function get()` under PsySH, where they existed only as the `$get`/`$create`/… scope closures. Both the bare and `$`-prefixed forms now work in both runtimes and stay bound to the live ObjectManager across calls.
- Fixed a latent guard bug in the `eval` fallback where the helper-function `function_exists()` check resolved against the wrong namespace, which could fatally re-declare helpers on a second invocation in a long-lived process.

## 1.15.0

A code-audit release: correctness and security fixes, `code-runner` now uses PsySH as intended, and duplicated internal logic consolidated. No breaking changes.

**Security & safety**
- **Environment-variable tool overrides now work for every tool.** `BRICKLAYER_TOOLS_*` settings were silently ignored for hyphenated tools (e.g. `code-runner`, `product-delete`), so disabling a tool via an env var had no effect. Use the full-path form, e.g. `BRICKLAYER_TOOLS_CODE_RUNNER_ENABLED=false`.
- **`code-runner` is now reliably blocked in production** even when the deploy mode can't be read (fails closed instead of open).

**Fixes**
- **`code-runner` actually runs through PsySH** when installed — it previously fell back to a basic `eval` engine on every call while wrongly reporting "PsySH not available". Responses now report the active `runtime` (`psysh` or `eval`).
- **`diagnose-error`** counts error history from the log the error was actually found in, instead of always `exception.log`.
- **GraphQL introspection** labels input types correctly and returns type descriptions (both were broken).
- **`database-schema`** now respects its own enable/disable setting (it was tied to `database-query`).
- **The `mcp` command** honors `--magento-root` on systems without `pcntl`.
- Smaller fixes: safer log-file handling, accurate text truncation, recursive module validation, correct config-write success reporting, dynamic (non-hardcoded) tool/category counts, data-URI image type detection.

**Internal (no behavior change)**
- Consolidated duplicated logic — error responses, pagination, sensitive-value masking, time parsing, command setup, and log/markdown scanning — into shared traits and helpers.
- Wired up the previously-orphaned PhpStorm config writer, so `install` now generates `.idea/mcp.json`.

## 1.14.2

- **New guideline: Pool pattern** (`patterns/pool.md`) — DI-based strategy resolution with extensible array injection, replacing if/else branching on type identifiers. Covers third-party extensibility and chain of responsibility with sortOrder.
- **New guideline: Value Object / DTO** (`patterns/value-object.md`) — Immutable data carriers with `public readonly` properties, `fromJson()`/`toJson()` serialization for JSON columns, and comparison with Data Interfaces.
- **New guideline: Context Object** (`patterns/context-object.md`) — Bundling repeated request-scoped parameters (customerId, storeId, currencyCode) into a single immutable object with a Builder.
- **Updated: Plugin pattern** — Added "Plugin Chain on Repositories" section showing how to chain multiple `beforeSave` plugins with sortOrder for cross-cutting concerns.
- **Updated: Coding Standards** — Added "Constants as Final Classes" section for grouping enum-like constants in `final class` instead of interfaces.

## 1.14.1

- Added Symfony Console 7 compatibility (`symfony/console: ^5.4 || ^6.0 || ^7.0`).

## 1.14.0

- **Project-local overrides** — New `.bricklayer/` directory at the Magento root lets projects add or replace bundled content without forking the package:
  - `project-context.md` — appended to every generated agent file under a `## Project-Specific Context` heading
  - `decision-matrix.md` — extra rows merged into the "Before Modifying" table
  - `guidelines/{path}.md` — override bundled guidelines by matching relative path, or add new sections with no bundled counterpart
  - `skills/{category}/SKILL.md` — override bundled skills, or add new local-only categories directly callable via `development-context category={directory}`
  - Optional YAML frontmatter (`name:`, `description:`) on local SKILL.md files for rich display; stripped before content is returned to agents
  - Local entries tagged `[Project]` in `search-docs` results
- `bricklayer update` now reports applied local overrides/additions and the number of local docs-index entries
- `bricklayer init` now creates `.bricklayer/` automatically
- New `LocalOverrideHelper` utility — shared static helpers for YAML frontmatter parsing and stripping, used by `GuidelinesCompiler`, `ContextTools`, and `SearchTools`
- `GuidelinesCompiler`, `ContextTools`, and `SearchTools` constructors now accept optional `$magentoRoot` / `$packageRoot` for testability
- 29 new unit tests across `LocalOverridesTest`, `LocalGuidelineAdditionsTest`, `LocalSkillResolutionTest`, `LocalDocsIndexTest` — suite total: 353 tests, 999 assertions
- Documentation audit — removed stale `--config-only` / `--docs-only` flag references (neither exists in `UpdateCommand`), removed stale "documentation index" phrasing, added missing `verify` command documentation to the README, corrected `contributing.md` class counts (19 tool classes, 5 resource providers), added new `configuration/local-overrides.md` wiki page

## 1.13.2

- Fixed array_map error in InstallCommand when a single AI agent is selected

## 1.13.1

- Added ArrayManager for checkout jsLayout manipulation in the LayoutProcessor skill example

## 1.13.0

- New `check-class` Tier 1 tool — combined plugin-list, di-configuration, and preference-list in one call
- Behavioral triggers in server instructions — agents are told WHY to check runtime state before modifying code
- "Before Modifying Magento Code" decision matrix in generated agent guidelines (CLAUDE.md, .cursorrules, etc.)
- `_skill_hint` in introspection tool responses — points agents to the relevant `development-context` category
- `_next_steps` in `development-context` responses — suggests introspection tools to call before writing code
- Rewrote Tier 1 tool descriptions with "Check BEFORE..." behavioral triggers
- Removed generic architecture section from generated agent guidelines
- Simplified Context Quick Reference to point at the new decision matrix
- README rewritten with "How Agents Use Bricklayer" section and check→learn→write workflow

## 1.12.1

- Fixed preamble duplication in `code-runner` `mode=define`
- Fixed `$mode` variable shadowing in `CodeRunnerTools`
- Added `diagnose-performance` to search indexes
- Added config gating to `diagnose-performance`
- Removed phantom Integration test suite from `phpunit.xml.dist`
- Reduced coupling in `PerformanceTools`
- Added unit tests for `FiltersFields`, `RequiresMagento`, and `ToolScanner`

## 1.12.0

- Config staleness detection via `.bricklayer.json` mtime tracking with automatic hot reload
- Reusable code-runner functions (`mode=define`) persisting across calls (max 20 per session)
- New `diagnose-performance` tool with 6 checks: indexes, cache, flat-tables, cron-backlog, config, queries
- Updated `code-runner-help` with mode parameter documentation

## 1.11.2

- Improved test coverage

## 1.11.1

- **Security:** Fixed `isProductionMode()` to fail closed when bootstrap unavailable
- Tightened MCP SDK version constraint to `^0.3 || ^1.0`
- Improved `VerifyCommand` exception parser testing
- Clarified `graphql-inspect` resolver target documentation

## 1.11.0

- `reinitialize` MCP tool for ObjectManager rebuild
- Automatic staleness detection via sentinel file mtime tracking
- Progressive tool disclosure (tier 1 visible, tier 2 discoverable)
- Compressed tool descriptions to 80 words max
- Tool consolidation: 5 GraphQL tools, 4 log tools, 5 system tools merged
- Pagination metadata (`has_more`) on all list tools
- Context-aware hints (`_hint` field) in tool responses
- Pruned guidelines duplication, compressed server instructions

## 1.10.2

- Standalone `magewire` development-context category

## 1.10.1

- **Security:** Fixed `requireNonProduction` bypass, SQL injection in `DatabaseTools`
- File overwrite detection and `force` parameter for code generation
- Config enforcement for log tools (`max_lines`)
- New unit tests for `ChecksConfig` and `ConfigInitializer`

## 1.10.0

- Production safety system with per-tool enable/disable
- `ChecksConfig` trait for all tool classes
- Production mode blocking for destructive and code generation tools
- Sensitive config path masking in database queries
- Path traversal protection in code generation
- `bricklayer init` command for `.bricklayer.json` generation

## 1.9.5

- `ToolRegistry` singleton for centralized tool management
- Extracted shared traits: `FiltersFields`, `SecureArea`, `RequiresMagento`
- Dynamic version reading from `composer.json`
- Standardized error responses across all tools

## 1.9.0

- MCP efficiency features: `batch-execute`, `count_only`, `fields`, `verbosity`, `truncation`
- `search-tools` for tool discovery
- DI inspection tools: `configuration-list`, `di-configuration`, `plugin-list`, `event-list`, `preference-list`
- `eav-entity-types` tool
- Improved `code-runner` with `code-runner-help` companion

## 1.8.0

- Verify command for installation verification
- GraphQL method fixes
- Diagnostic tool unit tests
- Improved code-runner and log reading

## 1.7.0

- Auto-discovery for tools, guidelines, and skills
- `ToolScanner` for automatic tool detection
- Dynamic context category loading

## 1.6.0 – 1.6.3

- Enhanced code-runner with helpers
- Codebase simplification and error diagnostic improvements

## 1.5.0

- `diagnose-error` tool with exception parser
- `ReadsLogFiles` concern
- Route and search-docs fixes

## 1.4.0

- Hooli agent support
- Skills split into smaller chunks for reduced context usage

## 1.3.0

- `development-context` tool
- Coding standards guidelines

## 1.2.0

- Hyva theme, UI components, and checkout knowledge

## 1.1.0

- Interactive install, checkout/payment/theme skills

## 1.0.0

- Initial release
