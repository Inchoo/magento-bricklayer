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
