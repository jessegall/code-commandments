<?php

declare(strict_types=1);

/**
 * Dev helper: run the FULL backend prophet registry over a directory of .php
 * files and print every finding. Used to iterate a "golden" corpus slice to
 * ZERO findings (and to confirm a "messy" slice lights up).
 *
 *   php tests/Fixtures/corpus/check.php tests/Fixtures/corpus/<subsystem>/golden
 *
 * Exit code = number of findings (0 = silent).
 */

use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ProphetRegistry;

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

$dir = $argv[1] ?? null;

if ($dir === null || ! is_dir($dir)) {
    fwrite(STDERR, "usage: php check.php <dir-of-php-files>\n");
    exit(255);
}

$files = array_values(array_filter((array) glob(rtrim($dir, '/') . '/*.php')));
$index = CodebaseIndex::build($files);

$registry = new ProphetRegistry();
$config = ConfigLoader::load($root . '/config/commandments.php');
$backend = $config['scrolls']['backend'] ?? [];
$registry->registerMany('backend', $backend['prophets'] ?? []);
$registry->setScrollConfig('backend', $backend);

$prophets = $registry->getProphets('backend');

foreach ($prophets as $prophet) {
    if ($prophet instanceof NeedsCodebaseIndex) {
        $prophet->setCodebaseIndex($index);
    }
}

$count = 0;

foreach ($files as $file) {
    $content = (string) file_get_contents($file);

    foreach ($prophets as $prophet) {
        $judgment = $prophet->judge($file, $content);

        foreach ([...$judgment->sins, ...$judgment->warnings] as $finding) {
            $count++;
            printf(
                "%-36s %s:%s  %s\n",
                class_basename($prophet),
                basename($file),
                $finding->line ?? '?',
                mb_substr($finding->message, 0, 100),
            );
        }
    }
}

echo $count === 0 ? "\nSILENT — 0 findings\n" : "\n{$count} finding(s)\n";

exit(min($count, 254));
