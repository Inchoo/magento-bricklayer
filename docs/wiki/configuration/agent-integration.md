# Agent Integration

Bricklayer generates configuration files for multiple AI coding agents, enabling MCP communication and providing Magento-specific development guidelines.

## Supported Agents

| Agent | MCP Config | Guidelines File | Status |
|-------|------------|----------------|--------|
| Claude Code | `.mcp.json` | `CLAUDE.md` | Fully Supported |
| Cursor | `.mcp.json` | `.cursorrules` | Fully Supported |
| GitHub Copilot | `.mcp.json` | `.github/copilot-instructions.md` | Supported |
| JetBrains AI (PhpStorm) | `.mcp.json` | `.junie/guidelines.md` | Supported |
| Gemini CLI | `.mcp.json` | `AGENTS.md` | Supported |

## .mcp.json

The shared MCP configuration file tells agents how to start the Bricklayer MCP server:

```json
{
    "mcpServers": {
        "magento-bricklayer": {
            "command": "vendor/bin/bricklayer-mcp",
            "args": []
        }
    }
}
```

For containerized environments (DDEV, Warden), the command is adjusted automatically:

```json
{
    "mcpServers": {
        "magento-bricklayer": {
            "command": "ddev",
            "args": ["exec", "vendor/bin/bricklayer-mcp"]
        }
    }
}
```

## Guidelines Files

Each agent's guidelines file contains:

- MCP server instructions with behavioral triggers (check runtime state before modifying code)
- "Before Modifying Magento Code" decision matrix (task → tool → development-context category)
- `development-context` tool category reference table
- Token efficiency patterns for the agent
- Shell command reference (DDEV/Warden-aware)

## Generating Configuration

```bash
# Generate all config files
vendor/bin/bricklayer install

# Regenerate after bundled content or project-local overrides change
vendor/bin/bricklayer update
```

`update` regenerates every agent file that already exists in the project and applies any overrides/additions from `.bricklayer/` (see [Project-Local Overrides](local-overrides.md)).

## Custom Agent Support

`McpConfigWriter` generates agent files based on the `agents` array in `.bricklayer.json`:

```json
{
    "agents": ["claude-code", "cursor"]
}
```

Only specified agents will have their guideline files generated.
