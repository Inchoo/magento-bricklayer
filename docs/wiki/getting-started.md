# Getting Started

## Prerequisites

- PHP 8.1+
- Magento 2.4.4+
- Composer 2.2+
- An AI coding agent with MCP support (Claude Code, Cursor, GitHub Copilot, JetBrains AI, or Gemini CLI)

## Installation

### As a Dev Dependency (Recommended)

```bash
composer require --dev inchoo/magento-bricklayer
```

### Global Installation

```bash
composer global require inchoo/magento-bricklayer
```

## Setup

After installation, run the install command to generate agent configuration files:

```bash
vendor/bin/bricklayer install
```

This generates:
- `.mcp.json` — MCP server configuration for your agent
- `.bricklayer.json` — Per-project tool configuration
- Agent-specific guideline files (`CLAUDE.md`, `.cursorrules`, etc.)

## Verification

Verify your installation is working correctly:

```bash
vendor/bin/bricklayer verify
```

This checks:
- Magento root detection
- ObjectManager initialization
- Tool registration
- Configuration loading
- Log file accessibility
- Exception parser functionality

## First Steps

1. **Open your project** in an AI agent that supports MCP (e.g., Claude Code)
2. The MCP server starts automatically when the agent loads
3. Use `search-tools` to discover available tools by keyword
4. Use `development-context` to load coding guidelines before writing code

### Example Workflow

```
You: "Show me the product catalog structure"
Agent: [calls application-info, category-tree, product-list]

You: "Create a new module for custom pricing"
Agent: [calls development-context with 'module', then generate-module]

You: "Why is the checkout failing?"
Agent: [calls diagnose-error, log with action=search]
```

## Containerized Environments

Bricklayer auto-detects DDEV, Warden, and Docker environments. No additional configuration is needed. The `bricklayer-mcp-docker` wrapper handles container execution transparently.

## Next Steps

- [Configuration](Configuration-Bricklayer-Json) — Customize tool behavior
- [Tools Reference](Tools-Overview) — Explore all 83 tools
- [Security](Security-Overview) — Understand production safety defaults
