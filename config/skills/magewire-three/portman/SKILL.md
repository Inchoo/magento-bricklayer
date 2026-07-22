---
name: magewire-three-portman
description: Maintain Magewire 3's Livewire-derived PHP with Portman. Use when contributing to Magewire itself, syncing the pinned Livewire source, editing augmentations, changing namespace or class transformations, rebuilding dist, reviewing generated changes, or diagnosing a Portman build.
---

# Magewire 3 Portman Workflow

Portman is framework-maintainer tooling. Application modules and normal Magewire components do not
need it. Load `magento://skills/magewire-three/architecture` before changing framework internals.

## Understand File Ownership

Magewire uses `magewirephp/portman` to port selected Livewire V3 PHP into Magento. The repository's
`portman.config.php` is authoritative because all paths and transformations are configurable.

For the current Magewire 3.2 layout:

| Path | Role | Edit directly? |
|---|---|---|
| `portman/lib/Livewire/` | Downloaded, pinned upstream input | No |
| `portman/Livewire/` | Magewire augmentations merged into upstream classes | Yes |
| `dist/` | Generated, transformed PHP used at runtime | No |
| `lib/Magewire/` | Hand-written Magewire framework code | Yes |
| `lib/MagewireBc/` | Hand-written V1 compatibility code | Yes |
| `lib/Magento/` | Hand-written Magento integration | Yes |
| `src/` | Magento module code, DI, controllers, and views | Yes |

Never fix a bug only in `dist/`; the next build overwrites it. Never edit the downloaded source
cache; the next source download overwrites it. Find the source, augmentation, transformation, or
hand-written class that owns the behavior.

## Build Pipeline

Portman performs four relevant stages:

1. Download the locked Composer package and select files using configured glob/ignore rules.
2. Parse matching upstream and augmentation PHP files into ASTs.
3. Merge augmentation members, then apply namespace, class, method, and property transformations.
4. Pretty-print the result into `dist/` and run configured post-processors.

In Magewire, this turns selected `Livewire\\` classes into `Magewirephp\\Magewire\\` classes,
renames framework entry points where configured, and removes Laravel-only APIs. Magewire-specific
runtime code that has no upstream equivalent remains hand-written outside the generated tree.

## Commands

Run commands from the Magewire repository root unless its contributor documentation says otherwise:

```bash
vendor/bin/portman
vendor/bin/portman download-source
vendor/bin/portman build
vendor/bin/portman watch
```

The executable may be at `../../bin/portman` when working inside an installed
`vendor/magewirephp/magewire` directory. `watch` requires `chokidar-cli`.

Do not run `download-source` casually: it refreshes generated input and can produce a large diff.
Confirm the target Livewire constraint and exact `version-lock` first.

## Add or Override Ported Behavior

An augmentation mirrors the upstream relative path and uses the upstream namespace. For example,
an addition to the downloaded `ComponentHook.php` belongs at:

```text
portman/Livewire/ComponentHook.php
```

```php
<?php

namespace Livewire;

class ComponentHook
{
    public function magewireSpecificBehavior(): void
    {
        // This member is merged before namespace transformation.
    }
}
```

Keep the augmentation minimal. Do not copy the full upstream class. Matching members override or
augment the parsed source according to Portman's merger behavior, so a smaller file produces a
clearer generated diff and fewer upgrade conflicts.

Choose the owning mechanism deliberately:

| Need | Change |
|---|---|
| Add or override a member on a ported class | Matching `portman/Livewire/` augmentation |
| Include another upstream class | Adjust `ignore`/glob rules in `portman.config.php` |
| Rename or remove an upstream API systematically | Transformation configuration |
| Add Magewire-only runtime code | Appropriate hand-written `lib/` or `src/` owner |
| Copy a file without AST transformation | Configured additional-file input, when supported |

## Sync a Livewire Release

1. Read the upstream release and compare it with Magewire's active Features and Mechanisms.
2. Update both the acceptable source constraint and exact lock in `portman.config.php`.
3. Run `download-source`, then inspect the source-cache diff before building.
4. Run `build` and inspect every generated `dist/` change.
5. Resolve augmentation conflicts in `portman/Livewire/`, not in generated output.
6. Search generated code for Laravel services, helpers, attributes, and Features that Magento does
   not provide.
7. Run focused framework tests, static analysis, coding standards, and browser lifecycle tests.
8. Commit the config, augmentation/hand-written changes, source lock changes required by project
   policy, and generated output as one coherent update.

A successful build only proves that parsing and transformation completed. It does not prove that an
upstream behavior is valid in Magento.

## Review Generated Changes

Review by responsibility rather than treating `dist/` as an opaque artifact:

- Every generated change must trace to an upstream delta, augmentation, or configured transform.
- New imports must not pull Laravel containers, routing, sessions, Blade, or global helpers into
  Magento runtime paths.
- Removed upstream methods must still match Magewire's callers and public API promises.
- Lifecycle, event, snapshot, synthesizer, and nesting changes need browser update-request coverage.
- Copyright/file doc blocks and namespace transforms must remain consistent.
- Unexpected formatting-only churn usually indicates a tool/configuration change and should be
  explained or removed.

Do not manually normalize generated code. Configure the generator or post-processor so rebuilding
is deterministic.

## Configuration Rules

- `directories.source` defines package, version, base path, file glob, and ordered ignore rules.
- A negated ignore entry re-includes a path excluded by an earlier rule.
- `directories.augmentation` holds AST merge inputs.
- `directories.output` is disposable generated output.
- Namespace keys ending in `\\` define namespace transforms; nested `children` rules target
  classes or paths.
- Method/property removals are compatibility decisions and need call-site searches.
- Post-processors can create broad diffs; enable them only as a deliberate repository decision.

Use the exact schema supported by the installed Portman version. Do not infer configuration keys
from an example written for another release.

## Troubleshooting

| Problem | Investigation |
|---|---|
| Augmentation is ignored | Relative path, source namespace, selected source glob, and ignore rules |
| Class is missing from `dist/` | Source download, base path, glob, ignore order, and build output path |
| Old code returns after a build | A direct edit was made in `dist/`; move it to its true source owner |
| Build parses but runtime fails | Unported Laravel dependency or a removed Magewire adaptation |
| Huge unexplained diff | Version lock, Portman version, PHP parser/formatter version, post-processors |
| Watch does not rebuild | `chokidar-cli`, watched paths, and active config file |
| Wrong config is loaded | `PORTMAN_CONFIG_FILE`, working directory, and repository `.env` |

## Maintainer Gate

Before proposing a Portman change, be able to answer:

1. Which files are upstream input, local source, and generated output?
2. Why is this an augmentation or transformation instead of hand-written Magewire code?
3. Which Livewire version is pinned, and why?
4. What generated behavior changed after namespace/framework adaptation?
5. Which automated and browser tests cover the changed runtime path?
