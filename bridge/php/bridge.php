<?php

/**
 * Writes PHP source as one generic tree stream (contract/CONTRACT.md); bridge/php/CONTRACT.md has the flags.
 */

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/src/Request.php';
require __DIR__ . '/src/Sources.php';
require __DIR__ . '/src/TreeWriter.php';
require __DIR__ . '/src/OutsideSymbols.php';
require __DIR__ . '/src/Stream.php';

try {
    $request = Request::fromArguments(array_slice($argv, 1));
    if (! $request->serve) {
        (new Stream($request))->write(STDOUT);
        exit(0);
    }
    while (($line = fgets(STDIN)) !== false) {
        if (trim($line) === '') {
            continue;
        }
        (new Stream($request->answering(json_decode($line, true, flags: JSON_THROW_ON_ERROR))))->write(STDOUT);
        fflush(STDOUT);
    }
} catch (\Throwable $failure) {
    fwrite(STDERR, 'php-bridge: ' . $failure->getMessage() . "\n");
    exit(1);
}
