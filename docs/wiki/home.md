# Magento Bricklayer

Magento Bricklayer is an AI-assisted development toolkit for Magento 2 that implements the Model Context Protocol (MCP). It enables AI coding agents to interact with Magento installations through introspection, code generation, data management, and diagnostics.

## Key Features

- **80 MCP tools** for Magento introspection, CRUD operations, code generation, and diagnostics
- **Progressive disclosure** — 17 essential tools visible at startup; 63 discoverable via `search-tools`
- **30 development guidelines** and **28 skill guides** for Magento best practices
- **Production safety** — deploy-mode-aware defaults with per-tool granular control
- **Multi-agent support** — Claude Code, Cursor, GitHub Copilot, JetBrains AI, Gemini CLI

## Quick Links

| Section | Description |
|---------|-------------|
| [Getting Started](getting-started) | Installation, setup, and first steps |
| [Architecture](architecture/overview) | System design, bootstrap, and MCP protocol |
| [Tools Reference](tools/overview) | All 80 tools organized by group |
| [Configuration](configuration/bricklayer-json) | `.bricklayer.json` options and environment variables |
| [Guidelines & Skills](guidelines/overview) | Development context and coding standards |
| [CLI Commands](cli-commands) | `bricklayer` command reference |
| [Security](security/overview) | Production safety, code execution, and query protection |
| [Code Generation](code-generation/overview) | Module, model, controller, and API scaffolding |
| [Diagnostics](diagnostics/overview) | Error diagnosis and performance analysis |
| [Contributing](contributing) | Development setup, testing, and extending |
| [Changelog](changelog) | Version history |
| [FAQ](faq) | Common questions and troubleshooting |

## Supported Environments

| Magento Version | PHP Version | Status |
|-----------------|-------------|--------|
| 2.4.4 – 2.4.8  | 8.1 – 8.4  | Supported |

| Environment | Detection |
|-------------|-----------|
| Native      | Automatic |
| DDEV        | Automatic |
| Warden      | Automatic |
| Docker      | Automatic |

## License

MIT License — Copyright 2026 Inchoo
