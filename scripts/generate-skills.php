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

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use JesseGall\CodeCommandments\Skills\SkillRenderer;
use JesseGall\CodeCommandments\Testing\FixtureExamples;
use JesseGall\CodeCommandments\Testing\VueFixtureExamples;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;

$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);

$examples = FixtureExamples::extract(Codebase::scan("{$root}/tests/Fixtures/backend"), Detectors::backend())
    + VueFixtureExamples::extract(VueCodebase::scan("{$root}/tests/Fixtures/frontend"), Detectors::frontend());

$renderer = new SkillRenderer();
$stale = [];
$written = 0;

foreach (Skills::all() as $skill) {
    $path = "{$root}/skills/commandments/{$skill->slug}/SKILL.md";
    $rendered = $renderer->render($skill, $examples);
    $current = is_file($path) ? file_get_contents($path) : null;

    if ($current === $rendered) {
        continue;
    }

    if ($check) {
        $stale[] = $skill->slug;
        continue;
    }

    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, $rendered);
    $written++;
}

if ($check) {
    if ($stale === []) {
        echo "✓ All SKILL.md are current.\n";
        exit(0);
    }

    fwrite(STDERR, "✗ Stale SKILL.md (run `composer sins`):\n  - " . implode("\n  - ", $stale) . "\n");
    exit(1);
}

echo "SKILL.md regenerated ({$written} written, " . count(Skills::all()) . " skills).\n";
