# Release + propagate checklist

## Ship the package

1. `vendor/bin/phpunit tests` green. (Self-judge findings against the package's own `src/`
   are not blockers — `--no-verify` is fine here.)
2. New/changed detector or sin? A detector auto-enrols (`Detectors\Catalog` globs
   `*Detector.php`) and its sin auto-enrols (`Sins\Catalog`) — nothing to register.
3. `composer readme` (regenerate the sins/detectors/scribes tables) and `composer sins`
   (regenerate each `SKILL.md`) if a sin's description/skill or the command surface changed.
   `ReadmeIsCurrentTest` / `GeneratedSkillsAreCurrentTest` fail if you skip this.
4. Commit — NO `Co-Authored-By` trailer. Patch tag for a fix, minor for a feature
   (never major without asking).
5. Push commit + tag together: `git push origin main && git push origin vX.Y.Z`.

## Propagate to each consumer (commit-only, never push)

6. Bump: in the consumer run `composer update jessegall/code-commandments`. Its composer
   post-update hook re-runs `commandments sync` (skills + CLAUDE.md) itself.
   - **smart-farmers**: composer only runs in Docker —
     `docker compose exec -T server composer update jessegall/code-commandments`.
7. Commit ONLY propagation paths (`composer.lock`, tracked `.commandments/` changes), NOT
   the consumer agent's WIP. Bypass ALL hooks:
   `git -c core.hooksPath=/dev/null commit --no-verify -m "chore: bump code-commandments to vX.Y.Z"`.
   (`vendor/` and `.claude/` are gitignored in consumers, so usually only `composer.lock` is tracked.)
8. Leave the consumer's own working changes untouched, and do NOT push — Jesse reviews and
   pushes the consumer commits himself.
