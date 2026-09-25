<?php

/**
 * Prints every finding the PHP engine's Python detectors make under a path, one `path:line Sin` per line, the path
 * relative to the one given, sorted: the PHP half of the Go port's parity check.
 *
 * Run from the repository root: php python/testdata/findings.php <path>
 */

declare(strict_types=1);

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Py\Codebase;

require __DIR__ . '/../../vendor/autoload.php';

ini_set('memory_limit', '-1');

$root = (string) realpath($argv[1] ?? '.');
$codebase = Codebase::scan($root);
$findings = [];

foreach (Catalog::python() as $detector) {
    $sin = preg_replace('/Detector$/', '', (new ReflectionClass($detector))->getShortName());

    foreach ($detector->find($codebase) as $finding) {
        $findings[] = substr($finding->file(), strlen($root) + 1) . ':' . $finding->line() . ' ' . $sin;
    }
}

$findings = array_values(array_unique($findings));
sort($findings);
echo implode("\n", $findings), "\n";
