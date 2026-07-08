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

/**
 * A skill's generated files — its SKILL.md plus one `reference/<name>.md` per {@see Reference}.
 *
 * @return array<string, string>  absolute path => rendered content
 */
$filesFor = static function ($skill) use ($root, $renderer, $examples): array {
    $dir = "{$root}/skills/commandments/{$skill->slug}";
    $files = ["{$dir}/SKILL.md" => $renderer->render($skill, $examples)];

    foreach ($skill->references() as $reference) {
        $files["{$dir}/reference/{$reference->name}.md"] = "# {$reference->title}\n\n" . trim($reference->body) . "\n";
    }

    return $files;
};

foreach (Skills::all() as $skill) {
    foreach ($filesFor($skill) as $path => $rendered) {
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
