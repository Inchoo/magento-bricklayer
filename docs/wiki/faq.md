# FAQ

## General

### What is Magento Bricklayer?

An AI-assisted development toolkit for Magento 2 that implements the Model Context Protocol (MCP). It allows AI coding agents to inspect, query, and modify Magento installations.

### Which AI agents are supported?

Claude Code, Cursor, GitHub Copilot, JetBrains AI (PhpStorm), and Gemini CLI.

### Is Bricklayer a Magento module?

No. It's a standalone Composer library that connects to Magento from outside the module system. It initializes the ObjectManager directly without being registered as a module.

### Which Magento versions are supported?

Magento 2.4.4 through 2.4.8 with PHP 8.1 through 8.4.

## Installation

### Why do I see "Magento root not found"?

Bricklayer searches for `app/etc/env.php` starting from the current working directory. Ensure you're running commands from within or above your Magento root directory.

### How do I use Bricklayer with DDEV?

DDEV is auto-detected. After `composer require --dev inchoo/magento-bricklayer`, run `ddev exec vendor/bin/bricklayer install`. The generated `.mcp.json` will use `ddev exec` as the command wrapper.

### How do I use Bricklayer with Warden?

Same as DDEV — Warden is auto-detected via the `WARDEN_ENV_NAME` environment variable.

## Tools

### Why do I only see 16 tools?

Bricklayer uses progressive disclosure. Only 16 essential tools are listed in `tools/list`. Use `search-tools` to discover the full set of 79 tools by keyword.

### Can I use Bricklayer in production?

Yes, with safety restrictions. Destructive tools, code generation, and code execution are blocked by default in production mode. Read-only introspection and query tools remain available.

### Why is `code-runner` not working in production?

`code-runner` is **hard-blocked** in production mode. This cannot be overridden even with explicit configuration in `.bricklayer.json`. This is a deliberate security measure.

### How do I enable a tool that's disabled by default?

Add it to your `.bricklayer.json`:

```json
{
    "tools": {
        "product-delete": { "enabled": true }
    }
}
```

Note: This only works in developer mode for destructive tools.

## Configuration

### Do I need to restart the MCP server after changing `.bricklayer.json`?

No. The server detects file changes via mtime tracking and reloads automatically on the next tool call.

### How do I regenerate agent configuration files?

```bash
vendor/bin/bricklayer update
```

### What happens if `.bricklayer.json` is missing?

Built-in defaults are used. In developer mode, all tools are enabled. In production mode, destructive tools are disabled.

## Troubleshooting

### Tools return "Magento context unavailable"

Run `vendor/bin/bricklayer verify` to diagnose. Common causes:
- Missing `app/etc/env.php`
- Failed ObjectManager initialization
- Magento in maintenance mode

### "Tool is disabled" error

Check your `.bricklayer.json` and deploy mode. In production, destructive tools require explicit `"enabled": true`.

### Stale data after `setup:upgrade`

Bricklayer auto-detects staleness via sentinel files. If it doesn't pick up changes, call the `reinitialize` tool manually.

### Agent doesn't see Bricklayer tools

Verify that `.mcp.json` exists in your project root and the MCP server command is correct:
```bash
vendor/bin/bricklayer verify
```
