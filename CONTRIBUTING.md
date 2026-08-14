# Contributing to Magento Bricklayer

Thank you for contributing! 

Bricklayer is an MCP server for AI-assisted Magento 2 development, distributed as a Composer library (`inchoo/magento-bricklayer`). This document covers what the project accepts and how to get a change merged. The mechanics of working *on* the codebase — layout, registration, derivation recipes, QA gates — live in [`SKILL.md`](SKILL.md), written as a skill for coding agents. Load it into your agent before it touches the source (see [Working with a coding agent](#working-with-a-coding-agent)); it also reads fine as plain markdown if you work without one.

Participation in this project is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).

## The acceptance bar for new tools

Bricklayer exists to give AI agents access to what Magento resolves **at runtime**: merged DI configuration, plugin ordering, preferences, EAV attributes that live only in the database, theme-resolved layout, message-queue topology. Static file reading structurally cannot see this — Magento assembles it across all modules at runtime.

Every proposed tool is judged against one question:

> **Can an agent already get this information reliably from static file reading alone?**

If the answer is yes, the tool needs strong justification to be accepted. Convenience wrappers around things `code-runner` or existing tools already handle usually won't make it in.

Corollaries:

- **Open an issue before writing a new tool.** Describe what runtime state it exposes that no single file shows. This saves you from building something that won't be merged.
- **Consolidate rather than proliferate.** One tool with a routing parameter (`graphql-inspect target=types|queries|mutations`) beats several near-identical tools — every tool schema costs agent context.
- Read-only introspection is the core. Write operations are the exception and carry extra requirements (see below).

## Development setup

Bricklayer runs inside a Magento 2 project — the server bootstraps Magento's ObjectManager, and without an installation to introspect there is nothing to develop against. Set up your working copy accordingly.

You need a Magento installation to develop against. Any local setup works (`ddev`, `warden`, `docker-compose`, native); if you don't have one at hand, [Mage-OS](https://mage-os.org) installs without Adobe authentication keys.

Clone your fork next to the Magento project:

```bash
git clone https://github.com/<your-fork>/magento-bricklayer.git
```

Wire the clone into the Magento project as a Composer path repository — path repositories symlink by default, so edits in the clone apply to the Magento project immediately. In the Magento project's `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "../magento-bricklayer" }
    ]
}
```

Then, from the Magento root:

```bash
composer require inchoo/magento-bricklayer
vendor/bin/bricklayer install
```

### Working with a coding agent

[`SKILL.md`](SKILL.md) at the repo root is an agent skill: it teaches a coding agent how this codebase is laid out, how tools/prompts/resources are registered and discovered, the QA gates, and the design rule every change is judged against. Load it before letting an agent modify the source — an agent working without it will re-derive (or guess) the conventions.

`SKILL.md` is plain markdown with `name`/`description` frontmatter. Load it through whatever skill or context mechanism your agent supports, or point the agent at the file directly at the start of the session.

Note the distinction: `SKILL.md` is for working **on** Bricklayer's source. The agent files that `bricklayer install` generates (`CLAUDE.md`, `AGENTS.md`, `.cursorrules`, …) are for *using* Bricklayer against a Magento store — don't confuse the two.

## Quality gates

Every pull request must pass `composer check`:

1. **PHP_CodeSniffer** — PSR-12.
2. **PHPStan** — at the level pinned in `phpstan.neon.dist` (level 8 at the time of writing).
3. **PHPUnit** — the full unit suite. New code ships with tests; `tests/Unit/` mirrors the `src/` layout, so put the test where the class lives.

Code must run on every PHP version from the `composer.json` floor upward (currently `>=8.3`, aligned with Magento 2.4.7+; deprecation fixes target the newest supported PHP) — don't use syntax newer than the floor allows. CI runs the same gates across the supported PHP range.

## Tool implementation conventions

Registration is attribute-driven; there is no manual registration list.

- A tool is a public method on a class in `src/Mcp/Tool/*Tools.php` carrying `#[McpTool(name: ..., description: ...)]`. Discovery is automatic.
- **One manual step:** a new tool *class* must be added to `ToolRegistry::TOOL_GROUPS`, or its tools land in group `other` and `search-tools` group filtering degrades.
- **Ship hidden by default.** New tools carry `meta: ['hidden' => true]` (Tier 2): fully callable by name, discoverable via `search-tools`, omitted from `tools/list`. Tier-1 visibility is reserved for the common path — make the case in your PR if you believe a tool belongs there.
- **Descriptions ≤ 80 words**, written for an agent deciding whether to call the tool: lead with when to use it, name the parameters that matter.
- **Config gating:** a tool that should be switchable calls `$this->requireToolEnabled('tool-name')` first. The gated set is discovered from call sites at install time — there is no list to keep in sync.
- **Write/destructive behavior** additionally calls `requireNonProduction('tool-name')` and defaults to disabled in generated config. Default to read-only wherever possible.
- **Use the shared Concern traits** (`src/Mcp/Tool/Concern/`) instead of reimplementing: `RespondsWithErrors` for the uniform error shape, `FiltersFields` for `fields` support, `PaginatesResults` for list tools, `RequiresValidVerbosity` for `verbosity` params, `RequiresMagento` for the bootstrap guard, `MasksSensitiveConfig` when output could contain credentials.
- **Don't "fix" the failure semantics in `ChecksConfig`.** `requireToolEnabled()` fails open (a broken config file must not brick read-only tooling); `isProductionMode()` fails closed (destructive tools stay blocked when the deploy mode is unknowable). The asymmetry is deliberate; changes to it need discussion first.

## Pull requests

- Target `develop` (the default branch).
- One logical change per PR. Small PRs merge faster.
- For new tools, link the issue that established the runtime-introspection case.
- Add a `CHANGELOG.md` entry for user-facing changes.
- Run `composer check` locally before pushing.

## Reporting bugs

Include enough to reproduce:

- Bricklayer version (`vendor/bin/bricklayer --version`), Magento version and deploy mode, PHP version
- Environment (`ddev`, `warden`, `docker`, native)
- `vendor/bin/bricklayer verify` output
- The tool call (name and parameters) and the response you received

## Security

`code-runner` executes arbitrary PHP inside a bootstrapped Magento by design — the security model is load-bearing, not incidental. **Never report security issues through public GitHub issues.** See [`SECURITY.md`](SECURITY.md) for the private reporting channel.

Changes touching `code-runner`'s validation gate, the production hard block, or the `requireToolEnabled` / `requireNonProduction` semantics receive extra scrutiny — state the security impact explicitly in the PR description.

## License

Bricklayer is MIT-licensed. By contributing, you agree that your contributions are licensed under the [MIT License](LICENSE).
