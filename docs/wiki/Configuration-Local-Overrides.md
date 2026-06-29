# Project-Local Overrides

Bricklayer ships with a bundled library of guidelines, skills, and a category map. Most Magento projects have conventions that deviate from those defaults — custom ERP integrations, CSP policies, payment quirks, house style. Local overrides let you add or replace content without forking the package.

Any file the project places under `.bricklayer/` at the Magento root is picked up automatically by `bricklayer update`, `development-context`, and `search-docs`. Nothing needs to be registered in `.bricklayer.json` — the path is the contract.

## Directory Layout

**Every entry is optional.** Bricklayer scans for whatever exists and ignores the rest — there is no required file and no manifest. `bricklayer init` creates the empty `.bricklayer/` directory; you add only the pieces you need, and create subdirectories yourself on first use.

### Minimal (the common case)

Most projects only ever use two things: a project map and a few skills documenting their custom code. This is the shape of a typical real-world `.bricklayer/`:

```
{magento_root}/
└── .bricklayer/
    ├── project-context.md              # Project map appended to every generated agent file
    └── skills/
        ├── erp-integration/
        │   └── SKILL.md                # NEW category → development-context category=erp-integration
        ├── company-conventions/
        │   └── SKILL.md                # NEW category → development-context category=company-conventions
        └── vendor-theme-stack/
            └── SKILL.md                # NEW category → development-context category=vendor-theme-stack
```

No `guidelines/` and no `decision-matrix.md` are present here — and that is fine. They are advanced extras, not requirements.

### Full (every feature)

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

In practice this is a **project map** — the orientation an agent needs before touching anything, expressed as compact prose and tables. A useful `project-context.md` covers:

- **What the store is** — brand, markets/store views, a one-line summary.
- **Frontend stack** — Hyvä vs Luma, theme inheritance chain and where each theme lives (`app/design/...`, `vendor/...`).
- **Key custom modules** — vendor vs local (`app/code`), what each owns, and which to reach for.
- **Integrations** — ERPs, payment/shipping providers, queues, and the modules that wrap them.
- **House rules** — conventions that deviate from Magento defaults (async writes, scope assumptions, naming).

```markdown
# Project Context — acme-store

Magento 2 storefront for **Acme** (DE + AT). Luma-based custom theme stack.

## Frontend

| Theme | Parent | Location |
|---|---|---|
| `Acme/storefront` | `Vendor/base` | `app/design/frontend/Acme/storefront` |

## Key modules

| Module | Location | Role |
|---|---|---|
| `Vendor_ErpSync` | vendor | Pulls order status from ERP via cron |
| `Acme_ErpStock` | `app/code/Acme/ErpStock` | Local stock pull, reuses Vendor's client |
```

Keep it current — it is regenerated into the agent file on every `bricklayer update`, so the agent always sees the latest map.

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

A mirror of `config/skills/`. Same override/addition semantics as guidelines, but applied per skill directory (`{category}/SKILL.md`).

- **Override** (`.bricklayer/skills/plugin/SKILL.md`) — the directory name matches a bundled category, so it replaces the bundled skill content wherever `development-context category=plugin` would otherwise load it.
- **Addition** (`.bricklayer/skills/erp-integration/SKILL.md`) — the directory name has no bundled equivalent, so it becomes a **new callable category**: `development-context category=erp-integration`. It also appears in the CLAUDE.md categories table under a **Project-specific** group, and in `search-docs` results with a `source: local` field.

**This is the main reason to use `.bricklayer/`.** Local-only skills are how you teach an agent about code that isn't in the bundled library — a custom ERP/e-invoicing integration, a vendor + local-override module pair, payment quirks, company/house conventions. One skill directory per concern, each callable on demand. The `SKILL.md` body is the deep documentation; write it the way you'd brief a new developer: which modules, where they live, how the data flows, what to load before touching them.

The category name (directory name) is what the agent types, so keep it short and kebab-case (`erp-integration`, not `Our ERP Integration`).

## Optional SKILL.md Frontmatter

Local SKILL.md files may start with a small YAML frontmatter block. The block is stripped before the content is handed to an agent.

```markdown
---
name: ERP integration
description: Two-module stack — Vendor_ErpSync pulls order status from the ERP via cron; Acme_ErpStock adds a local stock pull reusing the vendor client. Default-scope only. Load before touching either module.
type: skill
---

# ERP integration

...skill content...
```

What each key does:

| Key | Effect | Fallback if absent |
|-----|--------|--------------------|
| `name` | Display name in `search-docs` results | Title-cased directory name (`erp-integration` → `Erp Integration`) |
| `description` | Shown as the category's row in the CLAUDE.md categories table **and** tokenized into search keywords for `search-docs` / `development-context category=list` | Falls back to the display name |

Only `name` and `description` are read — **any other keys (like `type:`) are silently ignored**, so a richer block from another tool causes no harm. Files without frontmatter still work; they just fall back to the directory name.

> **Write a real `description`.** Its words become the search keywords that let an agent *discover* the skill via `search-docs` before it knows the category exists. A vague one-liner is hard to find; a specific sentence naming the modules, ERP, and scope is easy to surface. The `description` must be a **single line** — only the first line after `description:` is parsed, so don't wrap it across multiple lines.

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
