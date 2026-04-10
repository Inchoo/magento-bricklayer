# Project-Local Overrides

Bricklayer ships with a bundled library of guidelines, skills, and a category map. Most Magento projects have conventions that deviate from those defaults — custom ERP integrations, CSP policies, payment quirks, house style. Local overrides let you add or replace content without forking the package.

Any file the project places under `.bricklayer/` at the Magento root is picked up automatically by `bricklayer update`, `development-context`, and `search-docs`. Nothing needs to be registered in `.bricklayer.json` — the path is the contract.

## Directory Layout

```
{magento_root}/
└── .bricklayer/
    ├── project-context.md          # Free-form markdown appended to every generated agent file
    ├── decision-matrix.md          # Extra rows for the "Before Modifying" table (rows only, no header)
    ├── guidelines/
    │   ├── patterns/
    │   │   └── plugin.md           # OVERRIDE: replaces config/guidelines/patterns/plugin.md
    │   └── project/                # NEW CATEGORY: no bundled equivalent
    │       └── csp-scripts.md      # → compiled into CLAUDE.md as its own section
    └── skills/
        ├── plugin/
        │   └── SKILL.md            # OVERRIDE: replaces config/skills/plugin/SKILL.md
        └── csp-scripts/            # NEW SKILL: no bundled equivalent
            └── SKILL.md            # → callable via development-context category=csp-scripts
```

`bricklayer init` creates `.bricklayer/` for you — subdirectories are created on first use.

## Files

### `.bricklayer/project-context.md`

Free-form markdown that `bricklayer update` appends verbatim to every regenerated agent file under a `## Project-Specific Context` heading. Empty or missing files produce no section.

Use it for project-wide context agents should always know — e.g. "This store uses a custom ERP sync queue, prefer async writes when touching orders."

### `.bricklayer/decision-matrix.md`

Extra rows for the "Before Modifying Magento Code" table that appears near the top of every generated agent file. Lines must be valid markdown table rows — header and separator lines are ignored if present.

```markdown
| Modifying ERP sync logic | `code-runner` to inspect `Vendor\Erp\Model\SyncQueue` | `development-context category=erp-integration` |
| Changing checkout steps | `plugin-list className=Magento\Checkout\Model\Type\Onepage` | `development-context category=checkout` |
```

### `.bricklayer/guidelines/`

A mirror of `config/guidelines/` inside the package. Files here either override bundled content or add new content.

- **Override** — A local file whose relative path matches a bundled file (e.g. `.bricklayer/guidelines/patterns/plugin.md` matches `config/guidelines/patterns/plugin.md`) replaces the bundled one at load time. `development-context` loads the project version; `search-docs` serves the project entry instead of the bundled one.
- **Addition** — A local file with no bundled counterpart (e.g. `.bricklayer/guidelines/project/csp-scripts.md`) is compiled into every regenerated agent file as an extra section. The top-level heading comes from the parent directory (`project` → `## Project`), and the sub-heading comes from the filename (`csp-scripts.md` → `### Csp Scripts`).

### `.bricklayer/skills/`

A mirror of `config/skills/`. Same override/addition semantics as guidelines, but applied per skill directory.

- **Override** (`.bricklayer/skills/plugin/SKILL.md`) — replaces the bundled skill content wherever `development-context category=plugin` would otherwise load it.
- **Addition** (`.bricklayer/skills/csp-scripts/SKILL.md`) — the directory name becomes a new callable category: `development-context category=csp-scripts`. It also appears in the CLAUDE.md categories table under a **Project-specific** group, and in `search-docs` results prefixed with `[Project]`.

## Optional SKILL.md Frontmatter

Local SKILL.md files may start with a small YAML frontmatter block for richer display names and descriptions. The block is stripped before the content is handed to an agent.

```markdown
---
name: CSP Scripts
description: Patterns for managing Content Security Policy inline scripts in Magento 2.
---

# CSP Scripts

...skill content...
```

Only `name` and `description` keys are consumed — any other keys are ignored. Files without frontmatter fall back to the directory name as display name.

## Workflow

```bash
# 1. Create the directory (bricklayer init also creates it for you)
mkdir -p .bricklayer/guidelines/project .bricklayer/skills/csp-scripts

# 2. Drop your project-specific content in
$EDITOR .bricklayer/project-context.md
$EDITOR .bricklayer/guidelines/project/csp-scripts.md
$EDITOR .bricklayer/skills/csp-scripts/SKILL.md

# 3. Regenerate agent files
vendor/bin/bricklayer update
```

Example output:

```
  ✓ Regenerated CLAUDE.md
  Applied local overrides:
    - .bricklayer/project-context.md
    - .bricklayer/guidelines/project/csp-scripts.md
    - .bricklayer/skills/csp-scripts/SKILL.md
  Indexed 2 local file(s) into docs index
```

## How It Surfaces to Agents

- **`development-context category=...`** — local override or new local category returns project content, with any SKILL.md frontmatter stripped. Override responses include `[Project]` in the source comment so agents can tell them apart.
- **`search-docs query=...`** — local entries appear in results prefixed with `[Project]` and a `source: local` field.
- **Generated agent files (CLAUDE.md etc.)** — additions appear as extra sections; new skills appear under a **Project-specific** row in the categories table; `project-context.md` content appears under a `## Project-Specific Context` heading near the end of the file; `decision-matrix.md` rows are appended to the "Before Modifying" table.

## What Not to Put Here

- **Anything secret.** `.bricklayer/` is committed to the project repo by design so every team member and every agent sees the same conventions. Don't put credentials, API keys, or sensitive data in it.
- **Bundled content.** If a guideline belongs to the library, contribute it back upstream instead of pinning it in every project.
- **Copies of the bundled files.** Only put files here when you are actually changing something. Unmodified overrides just add maintenance burden.
