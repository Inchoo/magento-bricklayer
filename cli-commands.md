# CLI Commands

Bricklayer provides a Symfony Console application with 6 commands.

## `bricklayer install`

Generate agent configuration files for your project.

```bash
vendor/bin/bricklayer install
```

**What it does:**
- Detects your Magento installation and deploy mode
- Generates `.mcp.json` for MCP server configuration
- Generates `.bricklayer.json` with deploy-mode-aware defaults
- Creates agent-specific guideline files based on detected agents

**Generated files by agent:**

| Agent | Files |
|-------|-------|
| Claude Code | `.mcp.json`, `CLAUDE.md` |
| Cursor | `.mcp.json`, `.cursorrules` |
| GitHub Copilot | `.mcp.json`, `.github/copilot-instructions.md` |
| JetBrains AI | `.mcp.json`, `.junie/guidelines.md` |
| Gemini CLI | `.mcp.json`, `AGENTS.md` |

## `bricklayer init`

Generate only the `.bricklayer.json` configuration file.

```bash
vendor/bin/bricklayer init
```

Useful when you need to regenerate the configuration without touching agent files.

## `bricklayer mcp`

Start the MCP server. Typically called automatically by AI agents.

```bash
vendor/bin/bricklayer mcp
```

The server communicates via stdin/stdout using the MCP protocol.

## `bricklayer inspect`

Display information about the Magento installation.

```bash
vendor/bin/bricklayer inspect
```

Shows: Magento version, PHP version, deploy mode, module count, store hierarchy.

## `bricklayer update`

Regenerate configuration and documentation files.

```bash
vendor/bin/bricklayer update
vendor/bin/bricklayer update --config-only
```

Use `--config-only` to regenerate only the `.bricklayer.json` and agent files without touching other resources.

## `bricklayer verify`

Verify the installation and report any issues.

```bash
vendor/bin/bricklayer verify
```

**Checks performed:**
- Magento root directory detection
- ObjectManager bootstrap
- Tool class registration
- Configuration file loading and validation
- Log file read access
- Exception parser instantiation
- Disabled tool count report
