<?php

declare(strict_types=1);

/**
 * Regenerates the "Detectors" table in README.md from Detectors\Catalog, grouped
 * by skill. The content lives between the BEGIN/END markers; everything else in
 * the README is left untouched. Run via `composer readme`.
 */

require __DIR__ . '/../vendor/autoload.php';

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\Detector;

$readmePath = __DIR__ . '/../README.md';
$begin = '<!-- BEGIN: detectors';
$end = '<!-- END: detectors -->';

$summary = static function (Detector $detector): string {
    $doc = (new ReflectionClass($detector))->getDocComment();

    if ($doc === false) {
        return '';
    }

    $text = preg_replace('#/\*\*|\*/|^\s*\*\s?#m', '', $doc);
    $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));
    $first = preg_match('/^(.*?[.])(?:\s|$)/u', $text, $m) === 1 ? trim($m[1]) : $text;

    return str_replace('|', '\|', $first); // escape table-cell separators
};

$shortName = static fn (Detector $detector): string => (new ReflectionClass($detector))->getShortName();

/** @var array<string, list<Detector>> $bySkill */
$bySkill = [];

foreach (Catalog::all() as $detector) {
    $bySkill[$detector->sin()->slug()][] = $detector;
}

ksort($bySkill);

$total = array_sum(array_map('count', $bySkill));
$lines = ["_{$total} detectors across " . count($bySkill) . " skills._\n"];

foreach ($bySkill as $skill => $detectors) {
    $lines[] = "### `{$skill}`\n";
    $lines[] = '| Detector | What it flags |';
    $lines[] = '|---|---|';

    foreach ($detectors as $detector) {
        $lines[] = '| `' . $shortName($detector) . '` | ' . $summary($detector) . ' |';
    }

    $lines[] = '';
}

$content = "\n" . implode("\n", $lines);

$readme = (string) file_get_contents($readmePath);
$beginAt = strpos($readme, $begin);
$endAt = strpos($readme, $end);

if ($beginAt === false || $endAt === false) {
    fwrite(STDERR, "README detector markers not found.\n");

    exit(1);
}

$beginClose = strpos($readme, '-->', $beginAt) + 3;

$updated = substr($readme, 0, $beginClose) . $content . "\n" . substr($readme, $endAt);

if ($updated === $readme) {
    echo "README detectors already current.\n";

    exit(0);
}

file_put_contents($readmePath, $updated);
echo "README detectors regenerated ({$total} detectors).\n";
