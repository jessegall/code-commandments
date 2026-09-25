<?php

declare(strict_types=1);

/**
 * Regenerates every `skills/commandments/<slug>/SKILL.md` from the catalog: the
 * {@see Skill} (entry descriptor + teaching body + related links) and its
 * {@see Sin}s, whose "Bad → good" examples are sourced from the fixture
 * (`#[Sinful]` + `#[Righteous]`). Run via `composer sins`. A skill file is a pure
 * PROJECTION — never hand-edit it; edit the class and regenerate.
 */

require __DIR__ . '/../vendor/autoload.php';

use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\SkillRenderer;
use JesseGall\CodeCommandments\Testing\SkillExamples;

$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);

$examples = SkillExamples::from("{$root}/tests/Fixtures");

$renderer = new SkillRenderer();
$stale = [];
$written = 0;

/**
 * A skill's generated files — its SKILL.md plus the `reference/` documents it spills into, keyed by
 * absolute path. The renderer names them relative to the skill directory; where that directory is, is
 * the caller's business.
 *
 * @return array<string, string>  absolute path => rendered content
 */
$filesFor = static function ($skill) use ($root, $renderer, $examples): array {
    $dir = "{$root}/skills/commandments/{$skill->slug}";
    $files = [];

    foreach ($renderer->documents($skill, $examples) as $relative => $rendered) {
        $files["{$dir}/{$relative}"] = $rendered;
    }

    return $files;
};

foreach (Skills::all() as $skill) {
    $files = $filesFor($skill);

    foreach ($files as $path => $rendered) {
        $current = is_file($path) ? file_get_contents($path) : null;

        if ($current === $rendered) {
            continue;
        }

        if ($check) {
            $stale[] = substr($path, strlen("{$root}/skills/commandments/"));
            continue;
        }

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $rendered);
        $written++;
    }

    // A skill that loses a rule loses the reference document that rule filled. Nothing else writes
    // into `reference/`, so anything there we did not just generate is a leftover — and a leftover
    // gets published and read as if it were current.
    foreach (glob("{$root}/skills/commandments/{$skill->slug}/reference/*.md") ?: [] as $path) {
        if (isset($files[$path])) {
            continue;
        }

        if ($check) {
            $stale[] = substr($path, strlen("{$root}/skills/commandments/")) . ' (orphaned)';
            continue;
        }

        unlink($path);
        $written++;
    }
}

// Command references inside the skills are the Go binary's help, projected by
// scripts/refresh-command-docs.php.

if ($check) {
    if ($stale === []) {
        echo "✓ All SKILL.md are current.\n";
        exit(0);
    }

    fwrite(STDERR, "✗ Stale SKILL.md (run `composer sins`):\n  - " . implode("\n  - ", $stale) . "\n");
    exit(1);
}

echo "SKILL.md regenerated ({$written} written, " . count(Skills::all()) . " skills).\n";
