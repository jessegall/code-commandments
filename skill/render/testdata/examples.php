<?php

/**
 * Writes the worked examples the PHP fixtures carve for the skills, per detector, as examples.json: the golden the Go
 * carving and renderer are held to. A detector is keyed by its sin's skill and its short name, which alone repeats
 * across engines. Run from the repository root: php skill/render/testdata/examples.php
 */

declare(strict_types=1);

use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Testing\SkillExamples;

require __DIR__ . '/../../../vendor/autoload.php';

$examples = [];

foreach (SkillExamples::from(dirname(__DIR__, 3) . '/tests/Fixtures') as $detector => $list) {
    foreach ($list as $example) {
        $examples[(new $detector())->sin()->slug() . ':' . ClassName::short($detector)][] = ['language' => $example->language->value, 'bad' => $example->bad(), 'good' => $example->good()];
    }
}

ksort($examples);
file_put_contents(__DIR__ . '/examples.json', json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
