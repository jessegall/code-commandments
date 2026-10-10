---
name: releasing
description: How a change ships from this package — a commit with no attribution trailer on your own branch, the generated documents current, the touched packages' tests green in the capped dev container; a fix is released once those are green, without asking, through the v* tag that runs the release workflow that builds, budgets, sums and publishes every binary. Read before committing or preparing a release.
---

# Releasing

## Purpose

The conventions for shipping a change: the commit, the generated documents, the tests, and what a release is
and who makes one.

## Commit conventions

- **No attribution trailer** — no `Co-Authored-By`, no generated-by footer, no session link.
- **Commit on main.** Work happens on main; a fix is released once its scoped tests are green, without asking:
  bump the journal plugin, commit, tag `vX.Y.Z`, push main and the tag, and upgrade the plugin once the release
  workflow has run. Never run the full suite to release; the tests of what the release carries are its gate.
- The pre-commit hook (`scripts/hooks/pre-commit`) regenerates the skills, the README tables and the command
  references and re-stages what changed; `composer sins` does the same by hand. `cli/doc` and `skill/render`'s
  tests fail when a generated document is stale.
- The tests of every package you touched are green: `scripts/dev go test ./the/packages/...`, never Go on the
  host.
- Fix every finding on files you touched, even pre-existing ones.

## What a release is

A `v*` tag runs `.github/workflows/release.yml`: it publishes the C# bridge for every
platform (`scripts/release/roslyn`; the macOS ones on macOS, which signs them), cross-compiles the tool's static
binary for every platform (`scripts/release/build`), holds each binary to its budget in
`scripts/release/size-budget.json` and writes `SHA256SUMS` over them all (`scripts/release/check`), and creates
the GitHub release. Composer's shim fetches the tool for the installed version from it, and the tool fetches the
C# bridge beside itself, each checked against `SHA256SUMS`. A budget is raised only by hand, with the reason in
the commit.

## What to read when

| Read | When |
|---|---|
| `reference/checklist.md` | The end-to-end checklist. |
