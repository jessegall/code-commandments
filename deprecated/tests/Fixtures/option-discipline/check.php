<?php

// Dev eyeball: print OptionDiscipline's verdict for every file in this fake
// project. Usage:  php tests/Fixtures/option-discipline/check.php
//
// The Justified / NullRight / Exempt / AdoptSuppressed scenarios must print
// nothing — that silence is the point.

require __DIR__ . '/../../../vendor/autoload.php';

use JesseGall\CodeCommandments\Prophets\Backend\OptionDisciplineProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;

$src = __DIR__ . '/src';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() === 'php') {
        $files[] = $f->getPathname();
    }
}
sort($files);

$prophet = new OptionDisciplineProphet();
$prophet->setCodebaseIndex(CodebaseIndex::build($files));

$count = 0;
foreach ($files as $file) {
    $judgment = $prophet->judge($file, (string) file_get_contents($file));
    foreach ([...$judgment->sins, ...$judgment->warnings] as $finding) {
        $rel = ltrim(str_replace($src, '', $file), '/');
        printf("%s:%s — %s\n", $rel, $finding->line ?? '?', $finding->message);
        $count++;
    }
}

printf("\n%d finding(s).\n", $count);
exit($count > 0 ? 1 : 0);
