# Changelog

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
