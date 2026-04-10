# 1.14.0
* **Project-local overrides** — A new `.bricklayer/` directory at the Magento root lets projects add or replace bundled content without forking the package. The path is the contract; no registration in `.bricklayer.json` required.
  * `.bricklayer/project-context.md` — free-form markdown appended to every generated agent file under a `## Project-Specific Context` heading
  * `.bricklayer/decision-matrix.md` — extra rows merged into the "Before Modifying Magento Code" table; malformed lines skipped
  * `.bricklayer/guidelines/{path}.md` — files matching a bundled path override it; files with no bundled counterpart are compiled as new sections with headings derived from the directory and filename
  * `.bricklayer/skills/{category}/SKILL.md` — overrides by category, or new local-only categories directly callable via `development-context category={directory-name}` and listed under a **Project-specific** row in the CLAUDE.md categories table
  * Optional YAML frontmatter (`name:` and `description:`) for SKILL.md files — stripped before content is returned to agents, used as display metadata in `search-docs` and the categories table
  * Local entries tagged `[Project]` in `search-docs` results; override entries replace bundled ones in the merged index, no duplicates
* **`bricklayer update` reports local changes** — after regenerating agent files, the command lists every applied local override/addition and the number of local docs-index entries
* **`bricklayer init` creates `.bricklayer/`** — the project override directory is created automatically so developers know where to drop local content; subdirectories are created on first use, no placeholder files generated
* **New `Inchoo\MagentoBricklayer\Guidelines\LocalOverrideHelper`** — shared static helpers for parsing SKILL.md YAML frontmatter and stripping it from content. Used by `GuidelinesCompiler`, `ContextTools`, and `SearchTools` (no duplicated parser code)
* `GuidelinesCompiler`, `ContextTools`, and `SearchTools` constructors now accept optional `$magentoRoot` and `$packageRoot` arguments for testability (defaults preserve previous behavior)
* Added `vendor/bricklayer config:set` interactive command to easily set and change config values in `.bricklayer.json`
* Added Bricklayer SKILL.md file for agents to understand how Bricklayer works

# 1.13.2
* Fixed array_map error in InstallCommand when single AI agent is selected

# 1.13.1
* Added ArrayManager for checkout jsLayout manipulation in LayoutProcessor skill example

# 1.13.0
* **`check-class` tool** — New Tier 1 tool combining plugin-list, di-configuration, and preference-list in one call for any class. The essential pre-modification check.
* **Behavioral triggers in server instructions** — Server instructions now explain WHY agents should check runtime state before modifying code, not just how to use tools
* **"Before Modifying Magento Code" section in agent guidelines** — Generated CLAUDE.md/.cursorrules now include a task→tool→skill decision matrix that tells agents what to check before writing code
* **`_skill_hint` in introspection tool responses** — Plugin-list, di-configuration, preference-list, eav-attributes, database-schema, graphql-inspect, route-list, api-endpoints, and diagnose-performance now hint which `development-context` category to load next
* **`_next_steps` in development-context responses** — After loading guidelines, agents are told which introspection tools to call for the specific task
* **Rewrote Tier 1 tool descriptions** — Descriptions now include WHEN to use each tool (e.g., "Check BEFORE writing a plugin"), not just what the tool does
* **Removed generic architecture section** from generated agent guidelines (duplicated training data)
* **Simplified Context Quick Reference** — Replaced 20-row file-pattern table with reference to the new decision matrix
* **README rewritten** — Added "How Agents Use Bricklayer" section, documented check→learn→write workflow, updated tool counts and feature descriptions

# 1.12.1
* **Bug fix**: Fixed preamble duplication in `code-runner` `mode=define` — multi-function define calls no longer cause "Cannot redeclare" fatal errors
* **Bug fix**: Fixed `$mode` variable shadowing in `CodeRunnerTools` — response `mode` field now correctly reports `execute` instead of the Magento deploy mode
* Added `diagnose-performance` to search indexes in `search-docs` and `search-tools` (diagnostic and performance categories)
* Added config gating to `diagnose-performance` tool — now respects `tools.diagnose-performance.enabled` in `.bricklayer.json`
* Removed phantom Integration test suite declaration from `phpunit.xml.dist`
* Reduced coupling: `PerformanceTools` now uses a single lazy-initialized `DevelopmentTools` instance instead of creating new instances per check
* Added unit tests for `FiltersFields` trait, `RequiresMagento` trait, and `ToolScanner`

# 1.12.0
* **Config staleness detection** — `.bricklayer.json` changes are now detected automatically via mtime tracking; the MCP server reloads config on the next tool call without requiring a restart
* **Reusable code-runner functions** — Added `mode=define` to `code-runner` for saving named PHP functions that persist across calls within a session; cleared on `reinitialize`; capped at 20 functions
* **`diagnose-performance` tool** — New diagnostic tool with 6 checks: `indexes`, `cache`, `flat-tables`, `cron-backlog`, `config`, `queries`; returns findings with severity levels (info/warning/critical) and fix suggestions
* Added `PerformanceTools` to the `diagnostic` tool group in `ToolRegistry`
* Updated `code-runner-help` with `mode` parameter documentation and reusable functions section

# 1.11.2
* Improved test coverage

# 1.11.1
* **Security**: Fixed `isProductionMode()` to fail closed — returns `true` when Magento bootstrap is unavailable, blocking destructive tools by default instead of allowing them
* Tightened MCP SDK version constraint from `>=0.3` to `^0.3 || ^1.0` to prevent accepting breaking future major versions
* Improved `checkDiagnoseError` in VerifyCommand to actually instantiate and test `ExceptionParser` instead of always reporting pass
* Clarified `graphql-inspect` tool description to note that `resolvers` target returns guidance only
* Added inline comment in `MagentoBootstrap::reinitialize()` explaining why `require_once` works correctly during reinit

# 1.11.0
* Added `reinitialize` MCP tool to rebuild the Magento ObjectManager on demand after external state changes (setup:upgrade, setup:di:compile, module:enable)
* Added automatic staleness detection — the MCP server now tracks `app/etc/config.php` and `generated/metadata/global.php` mtimes and auto-reinitializes when they change on disk
* Added `MagentoBootstrap::reinitialize()` method for programmatic ObjectManager rebuild
* Added `MagentoBootstrap::isStale()` and `reinitializeIfStale()` for sentinel-based staleness checks
* Updated `RequiresMagento` trait to auto-reinitialize before every tool call when staleness is detected
* **Progressive tool disclosure** — Added `meta: ['hidden' => true]` to tier 2 tools so only 16 essential tools appear in `tools/list`; all 78 tools remain callable and discoverable via `search-tools`
* **Compressed tool descriptions** — Rewrote all tool descriptions to ≤80 words, removing redundant explanations already conveyed by parameter names and types
* **Tool consolidation** — Reduced tool count by merging related tools into single entry points with routing parameters:
  - 5 GraphQL tools → `graphql-inspect` (parameter: `target` = types|queries|mutations|resolvers, optional `name` for type detail)
  - 4 Log tools → `log` (parameter: `action` = read|list|search|analyze)
  - 5 System status tools → `system-status` (parameter: `check` = cache|indexers|deploy-mode|cron|cron-history)
  - `store-configuration` merged into `application-info` (parameter: `include` = stores)
* **Pagination metadata** — Added `has_more` boolean to all list tool responses (product-list, order-list, customer-list, category-products, invoice-list, shipment-list, creditmemo-list, customer-orders) for reliable pagination signaling
* **Pruned guidelines duplication** — Removed tool documentation tables from guidelines templates (CLAUDE.md, .cursorrules, etc.) since `tools/list` already provides this information
* **Minimal server instructions** — Compressed MCP server instructions from multi-paragraph prose to 5 actionable lines
* **Context-aware hints** — Added conditional `_hint` field to tool responses guiding agents toward logical next steps:
  - `product-get` / `customer-get` — hints when custom EAV attributes are present
  - `system-status` — hints when indexers are invalid or cache types are disabled
  - `diagnose-error` — hints to check `plugin-list` or `di-configuration` when relevant

# 1.10.2
* Added standalone `magewire` development-context category for generic Magewire component development outside Hyvä Checkout
* Refocused `hyva-checkout` category on checkout-specific patterns with condensed Magewire quick-reference

# 1.10.1
* **Security**: Fixed `requireNonProduction` bypass when no `.bricklayer.json` exists — destructive tools are now blocked by default in production mode
* **Security**: Fixed SQL injection vector in `DatabaseTools::getTableSchema` — table names are now validated against actual database tables before use in queries
* Removed unimplemented `production_safety` config field (`strict`/`standard`/`unrestricted`) — per-tool `enabled` flags already provide full control
* Removed `getProductionSafety()` from `ConfigLoader` and updated `VerifyCommand` to report disabled tool count instead
* Added `max_lines` config enforcement to `LogTools::readLog` — now respects `tools.log-reader.max_lines` setting (matching `DatabaseTools` `max_rows` pattern)
* Removed unused `ToolException` import from `RequiresMagento` trait
* Added file overwrite detection to `CodeGenerationTools::writeFiles` — existing files now cause a conflict error instead of silent overwrite
* Added `force` parameter to all 4 code generation tools (`generate-module`, `generate-model`, `generate-controller`, `generate-api`)
* Added `new`/`exists` file status annotations in dry-run mode for code generation tools
* Added unit tests for `ChecksConfig` trait (production safety, tool enable/disable, destructive tool defaults)
* Added unit tests for `ConfigInitializer` (production/developer config, file generation, deploy mode detection)

# 1.10.0
* Added production safety system with per-tool enable/disable via `.bricklayer.json`
* Added ChecksConfig trait providing `requireToolEnabled()` and `requireNonProduction()` for all tool classes
* Added `production_safety` config flag with strict/standard/unrestricted levels
* Enforced config checks on DatabaseTools — `database-query` and `database-schema` now respect `tools.database-query.enabled`
* Enforced config checks on LogTools — all 4 log tools now respect `tools.log-reader.enabled`
* Enforced config checks on all 10 CatalogTools write operations (product-create, product-update, product-delete, etc.)
* Enforced config checks on all 8 OrderTools write operations (order-cancel, invoice-create, creditmemo-create, etc.)
* Enforced config checks on all 6 CustomerTools write operations (customer-create, customer-delete, etc.)
* Enforced config checks on all 4 CodeGenerationTools (generate-module, generate-model, etc.)
* Added production mode blocking for destructive tools: product-delete, category-delete, customer-delete, customer-address-delete, order-cancel, creditmemo-create
* Added production mode blocking for all code generation tools (file writes to live servers)
* Added `max_rows` config enforcement on DatabaseTools (previously hardcoded, now reads `tools.database-query.max_rows`)
* Added sensitive config path masking in database-query results (payment/*, carriers/*, oauth/*, etc.)
* Added path traversal protection to CodeGenerationTools `writeFiles()` with `realpath()` boundary check
* Refactored CodeRunnerTools to use shared ChecksConfig trait (removes duplicate ConfigLoader logic)
* Extended ConfigLoader defaults to include all 26 write/destructive tools for per-tool configuration
* Added `bricklayer init` command to generate `.bricklayer.json` with deploy-mode-aware defaults
* Added ConfigInitializer for shared config generation logic across init, install, and verify commands
* Added automatic `.bricklayer.json` generation in `bricklayer verify` when config file is missing
* Integrated `init` into `bricklayer install` flow — config file is now generated alongside `.mcp.json` and agent files

# 1.9.5
* Extracted ToolRegistry singleton for centralized tool scanning (replaces static caches in BatchTools/SearchTools)
* Extracted shared traits: FiltersFields, SecureArea, RequiresMagento
* Replaced hardcoded version constant with dynamic reading from composer.json
* Replaced static DOCUMENTATION_INDEX with runtime generation from CATEGORY_MAP
* Standardized error responses across CatalogTools, CustomerTools, OrderTools, CodeRunnerTools
* Applied RequiresMagento trait to all 15 Magento-dependent tool classes
* Used match expressions in BatchTools result summarization
* Fixed unit tests for BatchTools, SearchTools, and MagentoDetector

# 1.9.4
* Added root directory check to config loader

# 1.9.3
* Fixed layer resolver instantiation on code-runner

# 1.9.2
* Added application state reset before code-runner actions to remove stale app context

# 1.9.1
* Added automated verify command for installation verification
* Removed unused doc index files

# 1.9.0
* Fixed module generator
* Added MCP efficiency features (batch-execute, count_only, fields filter, verbosity, truncation)
* Added search-tools tool for discovering tools by keyword
* Added configuration-list, di-configuration, plugin-list, event-list, preference-list tools
* Added eav-entity-types tool
* Improved code-runner with code-runner-help companion tool
* Improved log tools with max_entry_length truncation support
* Added unit tests for batch tools, search tools, field filter, count only, truncation, and verbosity

# 1.8.4
* Fixed docker user for mcp server

# 1.8.3
* Fixed doc indexing on installation

# 1.8.1
* Added interactive env selection
* Added composer post install script

# 1.8.0
* Added verify command/tool for installation verification
* Fixed GraphQL available methods
* Added unit tests for diagnostic tools and exception parser
* Improved code-runner tool
* Improved log file reading with ReadsLogFiles concern

# 1.7.0
* Added autodiscovery for new functionalities
* Added ToolScanner for automatic tool detection
* Refactored guidelines resource and skills resource
* Improved context tools with dynamic category loading

# 1.6.3
* Updated guidelines compiler

# 1.6.2
* Simplified codebase
* Simplified error diagnostics and error discovery
* Removed unused exception classes
* Reduced code complexity across bootstrap, config, and tool classes

# 1.6.1
* Added missing tool to CLAUDE.md generator

# 1.6.0
* Improved code-runner tool with enhanced helpers and capabilities
* Added code-runner unit tests

# 1.5.0
* Added diagnose-error diagnostics tool with exception parser
* Added ReadsLogFiles concern for shared log reading
* Fixed route-list tool
* Added missing categories to search-docs tool
* Removed unneeded composer.lock file
* Fixed version number

# 1.4.1
* Fixed route-list tool, added missing categories to search-docs tool

# 1.4.0
* Added Hooli support
* Split skills and guidelines to smaller chunks for less context usage
* Added new split skill files for checkout, payment, theme, Hyva, import/export, and UI components

# 1.3.0
* Added development-context tool
* Added instructions for code to pass coding standards checks
* Enhanced guidelines and skills with additional patterns and examples

# 1.2.0
* Added general Hyva knowledge
* Added Hyva UI components knowledge
* Added Hyva checkout knowledge
* Updated readme

# 1.1.0
* Added interactive install
* Added missing features
* Added checkout customization, payment integration, and theme development skills
* Removed legacy test directory

# 1.0.1
* Added container auto discovery

# 1.0.0
* Initial release
