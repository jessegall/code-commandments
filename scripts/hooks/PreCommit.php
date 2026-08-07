<?php

declare(strict_types=1);

/**
 * The ONE pre-commit hook. Enforces the documentation commandment on our own changed src
 * (blocking the commit on any doc sin), then regenerates every auto-generated artifact from
 * its source of truth and re-stages whatever changed — so a commit always ships up-to-date
 * docs, the same contracts `ReadmeIsCurrentTest` and `GeneratedSkillsAreCurrentTest` enforce
 * in CI, just caught at commit time instead of after.
 *
 *   - README.md excerpts + README.{sins,scribes,skills}.md tables  ← Detectors\Catalog  (generate-readme.php)
 *   - skills/commandments/.../SKILL.md  ← Sins/ + Skills/ + fixtures  (generate-skills.php)
 *   - AGENTS.md + CLAUDE.md  ← Skills\Briefing + each Agent's instructions  (commandments sync)
 *
 * That last one is this package consuming ITSELF: `sync` publishes our own skills into
 * `.agents/skills/` and links them where each agent looks, so a rule we ship is a rule we
 * can load while working here. Its tracked outputs are regenerated alongside the rest.
 *
 * Register it ONCE (writes .git/hooks/pre-commit → this script):
 *
 *   php scripts/hooks/PreCommit.php --install
 *
 * Add another generated artifact? Append an entry to {@see GENERATORS} — never touch the
 * git hook again.
 */

/** Each generated artifact: the argv that regenerates it, and the paths it writes into. */
const GENERATORS = [
    ['run' => ['scripts/generate-readme.php'], 'paths' => ['README.md', 'README.sins.md', 'README.scribes.md', 'README.skills.md']],
    ['run' => ['scripts/generate-skills.php'], 'paths' => ['skills/commandments']],
    ['run' => ['bin/commandments', 'sync'], 'paths' => ['AGENTS.md', 'CLAUDE.md', '.gitignore']],
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

// Enforce the documentation commandment on our own src — the rule we ship, applied to us.
passthru('php ' . escapeshellarg("{$root}/bin/commandments") . ' judge src --skill=backend/documentation --changes --no-checklist', $docSins);

if ($docSins !== 0) {
    fwrite(STDERR, "\n✗ commit blocked: fix the documentation sins above (or --no-verify to bypass).\n");
    exit(1);
}

$restaged = [];

foreach (GENERATORS as ['run' => $argv, 'paths' => $paths]) {
    $command = 'php ' . implode(' ', [escapeshellarg("{$root}/{$argv[0]}"), ...array_map('escapeshellarg', array_slice($argv, 1))]);

    passthru("{$command} > /dev/null 2>&1", $generated);

    if ($generated !== 0) {
        fwrite(STDERR, '✗ ' . implode(' ', $argv) . " failed\n");
        exit(1);
    }

    foreach ($paths as $path) {
        exec('git diff --quiet -- ' . escapeshellarg("{$root}/{$path}"), $_, $dirty);

        if ($dirty !== 0) {
            passthru('git add ' . escapeshellarg("{$root}/{$path}"));
            $restaged[] = $path;
        }
    }
}

if ($restaged !== []) {
    echo "↻ regenerated and re-staged: " . implode(', ', $restaged) . "\n";
}
