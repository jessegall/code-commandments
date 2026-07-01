<?php

declare(strict_types=1);

/**
 * Regenerates the auto-generated blocks in README.md — the "Detectors" table (from
 * Detectors\Catalog, grouped by skill) and the "Auto-fixing" tables (the maintenance
 * Scribes plus every Repentable detector's fix). Each block lives between its own
 * BEGIN/END markers; everything else in the README is left untouched. Run via
 * `composer readme`.
 */

require __DIR__ . '/../vendor/autoload.php';

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Catalog as Scribes;
use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Skills\Catalog as SkillsCatalog;

/** Package/framework-bound rules (Spatie Data, Laravel, concurrent) sort AFTER the universal ones. */
$bound = static fn ($detector): int => (int) ($detector->sin() instanceof RequiresPackage);

$readmePath = __DIR__ . '/../README.md';

/** A string made table-cell safe (escape the column separator). */
$cell = static fn (string $text): string => str_replace('|', '\|', trim($text));

/** The first sentence of a class's docblock, `{@see …}` tags reduced to their short name. */
$summary = static function (object|string $subject) use ($cell): string {
    $doc = (new ReflectionClass($subject))->getDocComment();

    if ($doc === false) {
        return '';
    }

    $text = (string) preg_replace('#/\*\*|\*/|^\s*\*\s?#m', '', $doc);
    $text = (string) preg_replace_callback(
        '/\{@see\s+\\\\?([^}\s]+)\}/',
        static fn (array $m): string => (static fn (array $p): string => (string) end($p))(explode('\\', $m[1])),
        $text,
    );
    $text = trim((string) preg_replace('/\s+/', ' ', $text));
    $first = preg_match('/^(.*?[.])(?:\s|$)/u', $text, $m) === 1 ? $m[1] : $text;

    return $cell($first);
};

$shortName = static fn (object|string $subject): string => (new ReflectionClass($subject))->getShortName();

// ---- Detectors table (split by engine) ------------------------------------

/** @var array<string, list<\JesseGall\CodeCommandments\Detector>> $bySkill */
$bySkill = [];

foreach (Catalog::all() as $detector) {
    $bySkill[$detector->sin()->slug()][] = $detector;
}

uksort($bySkill, static fn (string $a, string $b): int => [$bound($bySkill[$a][0]), $a] <=> [$bound($bySkill[$b][0]), $b]);

$detectorTotal = array_sum(array_map('count', $bySkill));

/**
 * The `#### slug` + tables for the skill groups of one engine.
 *
 * @return list<string>
 */
$engineDetectors = static function (string $engine) use ($bySkill, $shortName, $summary): array {
    $lines = [];

    foreach ($bySkill as $skill => $detectors) {
        if (! str_starts_with((string) $skill, "{$engine}/")) {
            continue;
        }

        $lines[] = "#### `{$skill}`\n";
        $lines[] = '| Sin | What it flags |';
        $lines[] = '|---|---|';

        foreach ($detectors as $detector) {
            $lines[] = '| `' . $shortName($detector->sin()) . '` | ' . $summary($detector) . ' |';
        }

        $lines[] = '';
    }

    return $lines;
};

$lines = ["_{$detectorTotal} sins across " . count($bySkill) . " skills._\n", "### Backend\n"];
$lines = [...$lines, ...$engineDetectors('backend'), "### Frontend\n", ...$engineDetectors('frontend')];

$detectorsBlock = "\n" . implode("\n", $lines) . "\n";

// ---- Auto-fixing tables (split by engine) ---------------------------------

$repentables = array_values(array_filter(Catalog::all(), static fn ($d): bool => $d instanceof Repentable));

usort($repentables, static fn ($a, $b): int => [$bound($a), $a->sin()->slug(), $a->sin()->name()] <=> [$bound($b), $b->sin()->slug(), $b->sin()->name()]);

$maintenance = Scribes::all();

/**
 * The auto-fixable-sins table for one engine, or [] when it has none.
 *
 * @return list<string>
 */
$engineFixables = static function (string $engine) use ($repentables, $shortName, $cell): array {
    $rows = array_values(array_filter($repentables, static fn ($d): bool => str_starts_with($d->sin()->slug(), "{$engine}/")));

    if ($rows === []) {
        return [];
    }

    $lines = ['| Sin | The fix `repent` applies |', '|---|---|'];

    foreach ($rows as $detector) {
        $sin = $detector->sin();
        $lines[] = '| `' . $shortName($sin) . '` | ' . $cell($sin->rule) . ' |';
    }

    return $lines;
};

$scribeLines = [
    '_`repent` auto-fixes ' . count($repentables) . ' sins, plus ' . count($maintenance) . ' whole-tree maintenance passes._',
    '',
    '### Maintenance passes',
    '',
    'Whole-tree PHP rewrites, run on every `repent`:',
    '',
    '| Scribe | What it does |',
    '|---|---|',
];

foreach ($maintenance as $scribe) {
    $scribeLines[] = '| `' . $shortName($scribe) . '` | ' . $summary($scribe) . ' |';
}

$scribeLines = [
    ...$scribeLines,
    '',
    '### Backend',
    '',
    ...$engineFixables('backend'),
    '',
    '### Frontend',
    '',
    ...$engineFixables('frontend'),
];

$scribesBlock = "\n" . implode("\n", $scribeLines) . "\n";

// ---- Skills table (split by engine) ---------------------------------------

$skillList = SkillsCatalog::all();
usort($skillList, static fn ($a, $b): int => $a->slug <=> $b->slug);

/**
 * The compact `| Class | Slug | What it teaches |` rows for one engine.
 *
 * @return list<string>
 */
$engineSkills = static function (string $engine) use ($skillList, $shortName, $cell): array {
    $lines = ['| Class | Slug | What it teaches |', '|---|---|---|'];

    foreach ($skillList as $skill) {
        if (str_starts_with($skill->slug, "{$engine}/")) {
            $lines[] = '| `' . $shortName($skill) . '` | `' . $skill->slug . '` | ' . $cell((string) preg_replace('/\s+/', ' ', $skill->summary())) . ' |';
        }
    }

    return $lines;
};

$skillLines = [
    '_' . count($skillList) . ' skills._',
    '',
    '### Backend',
    '',
    ...$engineSkills('backend'),
    '',
    '### Frontend',
    '',
    ...$engineSkills('frontend'),
];

$skillsBlock = "\n" . implode("\n", $skillLines) . "\n";

// ---- Splice all blocks in place -------------------------------------------

/**
 * Replace the content between a `<!-- BEGIN: $name … -->` and `<!-- END: $name -->`.
 */
$replaceSection = static function (string $readme, string $name, string $content): string {
    $beginAt = strpos($readme, "<!-- BEGIN: {$name}");
    $endAt = strpos($readme, "<!-- END: {$name} -->");

    if ($beginAt === false || $endAt === false) {
        fwrite(STDERR, "README {$name} markers not found.\n");

        exit(1);
    }

    $beginClose = strpos($readme, '-->', $beginAt) + 3;

    return substr($readme, 0, $beginClose) . $content . substr($readme, $endAt);
};

$readme = (string) file_get_contents($readmePath);
$updated = $replaceSection($readme, 'detectors', $detectorsBlock);
$updated = $replaceSection($updated, 'scribes', $scribesBlock);
$updated = $replaceSection($updated, 'skills', $skillsBlock);

if ($updated === $readme) {
    echo "README already current.\n";

    exit(0);
}

file_put_contents($readmePath, $updated);
echo "README regenerated ({$detectorTotal} detectors, " . count($repentables) . " auto-fixes).\n";
