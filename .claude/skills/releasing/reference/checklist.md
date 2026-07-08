# Release checklist

1. `vendor/bin/phpunit tests` green. (The gate is phpunit; do not self-judge this repo.)
2. New/changed detector or sin? A detector auto-enrols (`Detectors\Catalog` globs
   `*Detector.php`) and its sin auto-enrols (`Sins\Catalog`) — nothing to register. A draft
   detector marked `Unpublished` stays out of both until you remove the marker.
3. `composer readme` (regenerate the sins/detectors/scribes tables) and `composer sins`
   (regenerate each `SKILL.md`) if a sin's description/skill or the command surface changed.
   `ReadmeIsCurrentTest` / `GeneratedSkillsAreCurrentTest` fail if you skip this.
4. Fix every sin/warning on the files you touched.
5. Commit — NO `Co-Authored-By` trailer. Patch tag for a fix, minor for a feature
   (never major without asking); never move an existing tag.
6. Push commit + tag together: `git push origin main --tags`.
