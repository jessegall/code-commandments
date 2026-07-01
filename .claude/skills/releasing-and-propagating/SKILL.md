---
name: releasing-and-propagating
description: How to ship — commit with NO Co-Authored-By, a NEW semver tag on every commit (patch=fix, minor=feature, never major without asking), push commit+tag together, `composer readme`/`composer sins` if autogen tables changed; then propagate per consumer (`composer update` → auto-sync → commit-only, hooks-bypassed, WIP untouched — do not push the consumers). Read before committing/releasing.
---

# Releasing + propagating (outbound)

## Purpose

The conventions for shipping a change in the package and pushing it out to the consumer
projects. Getting the tag/commit/propagation flow right matters — agents work in the
consumers while we sync underneath them, so never disturb their working changes.

## Commit + release conventions

- **No `Co-Authored-By` trailer** in commit messages. (The commit-msg git hook rejects it.)
- **Every commit gets a NEW semver tag**: **patch** for fixes/small edits, **minor** for
  new features. **Never bump major — ask first.**
- **Push the commit AND the tag together**, same action
  (`git push origin main && git push origin vX.Y.Z`).
- Run **`composer readme`** (and **`composer sins`** when a sin's description/skill changed)
  if you touched the detector/sin or command tables — `ReadmeIsCurrentTest` /
  `GeneratedSkillsAreCurrentTest` fail otherwise. (Composer scripts: `readme`, `sins`, `judge`.)
- `vendor/bin/phpunit tests` green. Self-judge findings against the package's own `src/`
  are NOT blockers — `--no-verify` is fine HERE only.

## Propagate to consumers

Consumers require the package by version from Packagist, so propagation is per-consumer:

1. **Bump + sync.** In each consumer run `composer update jessegall/code-commandments` — its
   composer post-update hook re-runs `commandments sync` (skills + CLAUDE.md briefing) itself.
   For **smart-farmers**, composer only runs inside Docker:
   `docker compose exec -T server composer update jessegall/code-commandments`.
2. **Commit ONLY the propagation paths** (`composer.lock`, and any tracked `.commandments/`
   change) — NOT the consumer agent's working changes. Bypass ALL hooks so the post-commit
   reset can't wipe a working agent's confessions:
   `git -c core.hooksPath=/dev/null commit --no-verify` (plain `--no-verify` does NOT skip the
   post-commit hook). `vendor/` and `.claude/` are gitignored in the consumers, so usually only
   `composer.lock` is tracked-changed.
3. **COMMIT-ONLY — never push the consumers.** Jesse reviews and pushes those himself.

## What to read when

| Read | When |
|---|---|
| `reference/checklist.md` | The end-to-end release + propagate checklist. |
