<?php

declare(strict_types=1);

/**
 * The ONE pre-commit hook. Regenerates every auto-generated artifact from its source
 * of truth and re-stages whatever changed, so a commit always ships up-to-date docs —
 * the same contracts `ReadmeIsCurrentTest` and `GeneratedSkillsAreCurrentTest` enforce
 * in CI, just caught at commit time instead of after.
 *
 *   - README.md "Detectors" table  ← Detectors\Catalog        (generate-readme.php)
 *   - skills/commandments/.../SKILL.md  ← Sins/ + Skills/ + fixtures  (generate-skills.php)
 *
 * Register it ONCE (writes .git/hooks/pre-commit → this script):
 *
 *   php scripts/hooks/PreCommit.php --install
 *
 * Add another generated artifact? Append a line to {@see GENERATORS} — never touch the
 * git hook again.
 */

/** path (relative to the repo root) that an artifact is generated into => its generator script. */
const GENERATORS = [
    'README.md' => 'scripts/generate-readme.php',
    'skills/commandments' => 'scripts/generate-skills.php',
];

$root = rtrim((string) shell_exec('git rev-parse --show-toplevel 2>/dev/null'), "\n");

if ($root === '') {
    fwrite(STDERR, "not a git repository\n");
    exit(1);
}

if (in_array('--install', $argv, true)) {
    $hook = "{$root}/.git/hooks/pre-commit";
    file_put_contents($hook, "#!/usr/bin/env bash\nexec php \"\$(git rev-parse --show-toplevel)/scripts/hooks/PreCommit.php\"\n");
    chmod($hook, 0o755);
    echo "✓ pre-commit hook installed (runs scripts/hooks/PreCommit.php)\n";
    exit(0);
}

$restaged = [];

foreach (GENERATORS as $path => $generator) {
    passthru('php ' . escapeshellarg("{$root}/{$generator}") . ' > /dev/null 2>&1', $generated);

    if ($generated !== 0) {
        fwrite(STDERR, "✗ {$generator} failed\n");
        exit(1);
    }

    exec('git diff --quiet -- ' . escapeshellarg("{$root}/{$path}"), $_, $dirty);

    if ($dirty !== 0) {
        passthru('git add ' . escapeshellarg("{$root}/{$path}"));
        $restaged[] = $path;
    }
}

if ($restaged !== []) {
    echo "↻ regenerated and re-staged: " . implode(', ', $restaged) . "\n";
}
