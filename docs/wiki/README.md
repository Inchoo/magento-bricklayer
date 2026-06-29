# Magento Bricklayer — Wiki

This directory is the source of truth for the [GitHub wiki](https://github.com/Inchoo/magento-bricklayer/wiki). It is stored in GitHub-native **flat** form: one Markdown file per wiki page, named exactly as the published page (`Home.md`, `_Sidebar.md`, `Tools-Overview.md`, `Architecture-Overview.md`, …). Links point directly at those page names, so editing here is what-you-see-is-what-ships.

## Editing

1. Edit (or add) a page under `docs/wiki/` using the flat naming convention:
   - `Home.md` — landing page (GitHub requires this exact name).
   - `_Sidebar.md` — navigation (GitHub requires this exact name).
   - Multi-word pages use `Title-Case-Hyphenated.md`, grouped by prefix (`Tools-*`, `Architecture-*`, `Configuration-*`).
   - Use `[text](Page-Name)` for internal links (the page name, no `.md`).
2. Update `_Sidebar.md` if you added or renamed a page.

## Publishing

```bash
cd packages/inchoo/magento-bricklayer
make wiki-push
```

This clones the wiki repository, mirrors `docs/wiki/*.md` into it, and pushes — a plain copy, no conversion step. Run `make wiki-diff` to see what changed versus `origin/develop`.
