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
