# Architecture Overview

Magento Bricklayer is a standalone Composer library (not a Magento module) that connects to a Magento installation from outside the module system and exposes its capabilities through the Model Context Protocol (MCP).

## High-Level Architecture

```
┌─────────────────────┐
│    AI Coding Agent   │  (Claude Code, Cursor, Copilot, etc.)
│  (MCP Client)        │
└─────────┬───────────┘
          │ stdin/stdout (MCP Protocol)
┌─────────▼───────────┐
│   Bricklayer MCP     │
│      Server          │
├──────────────────────┤
│  Tool Registry       │  83 tools, auto-discovered
│  Resource Provider   │  Guidelines, Skills, Templates
│  Prompt Provider     │  8 prompts
├──────────────────────┤
│  Config Loader       │  3-tier config with hot reload
│  Bootstrap           │  Magento ObjectManager init
│  Area Emulator       │  Area code switching
└─────────┬───────────┘
          │ ObjectManager / DI
┌─────────▼───────────┐
│  Magento 2 Instance  │
│  (Database, Config,  │
│   EAV, Modules, etc.)│
└──────────────────────┘
```

## Core Components

### Bootstrap System

`MagentoBootstrap` initializes Magento's ObjectManager from outside the module system (following the n98-magerun2 pattern). It handles:

- **Magento root detection** via `MagentoDetector`
- **ObjectManager initialization** with proper autoloader setup
- **Sentinel file tracking** — monitors `app/etc/config.php` and `generated/metadata/global.php` mtimes for automatic staleness detection and reinitialize
- **Area emulation** via `AreaEmulator` — switches between `adminhtml`, `frontend`, `webapi_rest`, `graphql`, and `crontab` areas

### Configuration System

`ConfigLoader` implements a three-tier configuration strategy:

1. **Built-in defaults** — Safe defaults for all 83 tools
2. **Project configuration** — `.bricklayer.json` in project root
3. **Environment variables** — Highest priority overrides

Features:
- **Hot reload** — Detects `.bricklayer.json` mtime changes and reloads without server restart
- **Deploy-mode-aware defaults** — Production mode blocks destructive tools by default
- **Per-tool granular control** — Each tool can be individually enabled/disabled

### Tool System

`ToolRegistry` provides centralized tool management:

- **Auto-discovery** via PHP 8.1 `#[McpTool]` attributes
- **21 tool classes** organized into 11 groups
- **Progressive disclosure** — Tier 1 (17 tools) visible in `tools/list`; Tier 2 (66 tools) discoverable via `search-tools`
- **Shared traits** — `RequiresMagento`, `ChecksConfig`, `FiltersFields`, `ReadsLogFiles`

### MCP Resources

6 resource providers serve documentation and templates:

| Resource | Content |
|----------|---------|
| `GuidelinesResource` | 33 markdown development guidelines |
| `SkillsResource` | 28 skill guides with code examples |
| `CodingStandardsResource` | PSR-12 and Magento standards |
| `ReferenceResource` | Event names, DI patterns, ACL |
| `TemplateResource` | Code templates for scaffolding |

All resources use `FileLoaderTrait` for auto-discovery from the `config/` directory.

## Component Diagram

```
src/
├── Application.php              # Symfony Console app
├── Bootstrap/
│   ├── MagentoBootstrap.php    # ObjectManager init + staleness
│   ├── MagentoDetector.php     # Root directory detection
│   └── AreaEmulator.php        # Area code switching
├── Command/                     # 6 CLI commands
├── Config/
│   ├── ConfigLoader.php        # 3-tier config + hot reload
│   ├── ConfigValidator.php     # Schema validation
│   ├── ConfigInitializer.php   # .bricklayer.json generation
│   └── EnvironmentResolver.php # Docker/DDEV/Warden detection
├── Guidelines/
│   ├── GuidelinesCompiler.php  # Compile for agent files
│   └── ToolScanner.php         # Auto-discover tools
├── Integration/
│   └── McpConfigWriter.php     # Write .mcp.json + agent files
└── Mcp/
    ├── McpServerFactory.php    # MCP server creation
    ├── Tool/                   # 21 tool classes (83 tools)
    │   ├── Concern/            # Shared traits
    │   ├── Diagnostic/         # Error parsing helpers
    │   └── ToolRegistry.php    # Central registry
    ├── Resource/               # 6 resource providers
    └── Prompt/                 # 8 prompt providers
```

## Related Pages

- [Bootstrap Process](architecture/bootstrap) — Detailed bootstrap flow
- [Tool System](architecture/tool-system) — Tool registration, groups, and progressive disclosure
- [Configuration System](configuration/bricklayer-json) — Config loading and validation
