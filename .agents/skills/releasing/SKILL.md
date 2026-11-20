---
name: releasing
description: How a change ships from this package. Commit without an attribution trailer, regenerate documents, and test the touched packages in the capped dev container. GitHub builds and checks the exact release commit before creating the version tag and publishing the assets. Read before committing or preparing a release.
---

# Releasing

## Purpose

The conventions for shipping a change: the commit, the generated documents, the tests, and what a release is
and who makes one.

## Commit conventions

- **No attribution trailer** — no `Co-Authored-By`, no generated-by footer, no session link.
- **Commit on main.** Work happens on main; a fix is released once its scoped tests are green, without asking:
  bump the journal plugin, commit, push main, and dispatch the Release workflow with the version and full commit SHA.
  GitHub creates the public tag after the build and asset checks pass. Upgrade the plugin after verifying the release.
  Run the tests of what the release carries.
- The pre-commit hook (`scripts/hooks/pre-commit`) regenerates the skills, the README tables and the command
  references and re-stages what changed; `composer sins` does the same by hand. `cli/doc` and `skill/render`'s
  tests fail when a generated document is stale.
- The tests of every package you touched are green: `scripts/dev go test ./the/packages/...`, never Go on the
  host.
- Fix every finding on files you touched, even pre-existing ones.

## What a release is

A `v*` tag is a version of the plugin: `.journal-plugin/plugin.json` names it in `version`, and the binary every
manifest fetches in `env.COMMANDMENTS_RELEASE`. The generator (`composer sins`, the pre-commit hook) writes both into
`journal-plugin.json` and `journal-plugin.5.json`. There are two kinds:

- **A release that builds** pins its own version (`version` and `COMMANDMENTS_RELEASE` name the same release). Any change
  outside the plugin's own files needs one. `.github/workflows/release.yml` publishes the C# bridge for every platform
  (`scripts/release/roslyn`, reused from the last release when the bridge did not change), cross-compiles the tool's
  static binary for every platform (`scripts/release/build`), holds each binary to its budget in
  `scripts/release/size-budget.json` and writes `SHA256SUMS` (`scripts/release/check`). A separate job verifies the
  checksums of those artifacts before creating the tag and GitHub release.
- **A plugin-only release** bumps only `version` and keeps pinning the last release that built. The workflow
  checks that nothing but `.journal-plugin/` and the `journal-plugin*.json` manifests changed since that release, and
  checks that the pinned release has assets before creating the new tag. The journal reads plugin versions from tags,
  and the plugin fetches the pinned binary.
  It is done in seconds, so a change to how the plugin handles events never waits for a build.

Composer's shim fetches the binary the package's manifest pins, and the tool fetches the C# bridge beside itself, each
checked against `SHA256SUMS`. A budget is raised only by hand, with the reason in the commit.

## What to read when

| Read | When |
|---|---|
| `reference/checklist.md` | The end-to-end checklist. |
