<!--
Thanks for contributing! Please read CONTRIBUTING.md before opening this PR.
PRs target the `develop` branch. One logical change per PR — small PRs merge faster.
-->

## Summary

<!-- What does this PR change and why? One or two sentences. -->

## Type of change

<!-- Check all that apply. -->

- [ ] New MCP tool
- [ ] Change to an existing tool
- [ ] Bug fix
- [ ] Refactoring / internal change (no behavior change)
- [ ] Documentation
- [ ] CI / tooling
- [ ] New feature / capability (something that doesn't exist yet — describe below)

## Related issue

<!--
New tools require a prior issue establishing the runtime-introspection case
(what runtime state does this expose that no single file shows?).
-->

Fixes #

## New tool checklist

<!-- Delete this section if the PR does not add a tool. -->

<!-- See "Tool implementation conventions" in CONTRIBUTING.md for details on each point. -->

- [ ] An issue exists explaining why this needs a tool (the info can't be read from files alone) — linked above
- [ ] The new tool class is registered in `ToolRegistry::TOOL_GROUPS`
- [ ] The tool is hidden by default (`meta: ['hidden' => true]`) — if you think it should be always visible, say why in the summary
- [ ] The tool description is short (≤ 80 words) and explains when to use the tool
- [ ] Common behavior (error responses, pagination, field filtering, …) reuses the traits in `src/Mcp/Tool/Concern/` instead of custom code
- [ ] If the tool writes or deletes data, it calls `requireNonProduction()` and is disabled by default

## Security impact

<!--
Required if this touches code-runner's validation gate, the production hard block,
or the requireToolEnabled / requireNonProduction semantics — state the impact explicitly.
Write "None" otherwise. Never disclose vulnerabilities here; see SECURITY.md.
-->

None

## Quality gates

- [ ] `composer check` passes locally (PHPCS PSR-12, PHPStan, full PHPUnit suite)
- [ ] New code ships with tests; `tests/Unit/` mirrors the `src/` layout
- [ ] No syntax newer than the `composer.json` PHP floor (currently `>=8.1`)
- [ ] `CHANGELOG.md` entry added for user-facing changes
