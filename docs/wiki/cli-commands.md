# CLI Commands

Bricklayer provides a Symfony Console application with 7 commands.

## `bricklayer install`

Generate agent configuration files for your project.

```bash
vendor/bin/bricklayer install
```

**What it does:**
- Detects your Magento installation and deploy mode
- Generates `.mcp.json` for MCP server configuration
- Generates `.bricklayer.json` with **one entry per runtime-configurable tool** (discovered by scanning the source for `requireToolEnabled()` call sites, so the file never contains dead keys)
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

**What it generates** (developer/default mode): 35 entries — one per runtime-configurable tool. Destructive tools (`*-delete`, `order-cancel`, `creditmemo-create`, all 4 `generate-*` tools) default to `enabled: false`; everything else defaults to `enabled: true`. Tool-specific options (`code-runner.allow_write`, `code-runner.max_timeout`, `database-query.max_rows`, `log.max_lines`) are set to safe defaults.

**Production mode** additionally disables `code-runner` and lowers `database-query.max_rows` to 50.

The tool list is derived from source via a regex scan for `requireToolEnabled()` call sites, so adding a new configurable tool automatically makes it appear in `init` output — no manual list to maintain.

**Options:**
- `--magento-root=PATH` — specify Magento root (auto-detected by default)
- `--force` — overwrite an existing `.bricklayer.json`

## `bricklayer config:set`

Update a single value in `.bricklayer.json` with validation, round-trip verification, and a guided interactive mode.

```bash
# Interactive — walks through tool selection, setting, and value entry
vendor/bin/bricklayer config:set

# Scripted — dot-notation key + value
vendor/bin/bricklayer config:set tools.product-delete.enabled true
vendor/bin/bricklayer config:set tools.database-query.max_rows 250
vendor/bin/bricklayer config:set tools.code-runner.allow_write false
vendor/bin/bricklayer config:set tools.log.max_lines 1000
```

**Interactive mode** (no arguments) lists all 35 runtime-configurable tools with their current values shown inline, lets you select a tool, then a setting, then prompts for the new value with type-aware validation:

```
Select a tool to configure
--------------------------
 35 runtime-configurable tools available.

 Tool:
  [0 ] category-assign-products  (enabled=true)
  [1 ] category-create  (enabled=true)
  ...
  [4 ] code-runner  (enabled=true, allow_write=false, max_timeout=60)
  [5 ] creditmemo-create  (enabled=false)
  ...
  [12] database-query  (enabled=true, max_rows=100)
  ...
  [19] log  (enabled=true, max_lines=500)
  ...
 > 12

Select setting for "database-query"
-----------------------------------

 Setting:
  [0] enabled = true  [bool] — Master enable/disable switch for this tool
  [1] max_rows = 100  [int] — Maximum rows returned per query
 > 1

Enter new value
---------------
 Key:     tools.database-query.max_rows
 Current: 100
 Type:    int

 New value [100]:
 > 250

   ✓ Set tools.database-query.max_rows: 100 → 250
   ✓ Verified: value is readable via ConfigLoader

 ! [NOTE] Changes apply on the next MCP tool call — bricklayer hot-reloads
 !        .bricklayer.json automatically based on file mtime, so no agent
 !        restart is required.
```

**Scripted mode** parses values automatically: `true`/`false` → bool, `null` → null, numeric → int/float, `[...]`/`{...}` → JSON-decoded, everything else → string.

**Safety checks** on every successful set:

| Check | What it does |
|-------|--------------|
| **Validation** | Refuses to write if the resulting config fails `ConfigValidator` (e.g. negative `max_rows`, non-boolean `enabled`) |
| **Round-trip verification** | Re-loads the file through `ConfigLoader` and confirms the new value is readable |
| **Non-configurable tool warning** | Warns if you try to set `enabled` on a read-only introspection tool that does not honor the flag at runtime (e.g. `product-get`) |
| **Environment-variable shadow warning** | Warns if a matching `BRICKLAYER_*` env var is set that would override your file change |
| **Hot-reload note** | Reminds you that the running MCP server picks up changes on its next tool call — no agent restart needed |

**Options:**
- `--magento-root=PATH` — specify Magento root (auto-detected by default)
- `--no-interaction` — forces scripted mode; errors cleanly if `key` or `value` is missing

Auto-creates `.bricklayer.json` with deploy-mode-aware defaults if the file is missing.

Negative integer values need the `--` separator (standard Symfony Console behavior):

```bash
vendor/bin/bricklayer config:set -- tools.database-query.max_rows -1  # will fail validation
```

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

Regenerate agent configuration files (CLAUDE.md, .cursorrules, etc.) from the current bundled content plus any project-local overrides in `.bricklayer/`.

```bash
vendor/bin/bricklayer update
```

**Options:**
- `--magento-root=PATH` — specify Magento root (auto-detected by default)

When `.bricklayer/` contains local overrides or additions (see [Project-Local Overrides](configuration/local-overrides.md)), the command reports which local files were applied alongside the regenerated agent files.

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
