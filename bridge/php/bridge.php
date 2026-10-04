<?php

/**
 * Writes PHP source as one generic tree stream (contract/CONTRACT.md); bridge/php/CONTRACT.md has the flags.
 */

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

// One file's tree is encoded whole, and a generated file can hold more than PHP's default limit allows.
ini_set('memory_limit', '-1');

require __DIR__ . '/parser/autoload.php';
require __DIR__ . '/src/BadRequest.php';
require __DIR__ . '/src/Request.php';
require __DIR__ . '/src/Sources.php';
require __DIR__ . '/src/Nullability.php';
require __DIR__ . '/src/WrittenType.php';
require __DIR__ . '/src/NodeSpan.php';
require __DIR__ . '/src/Attachment.php';
require __DIR__ . '/src/Comment.php';
require __DIR__ . '/src/TreeWriter.php';
require __DIR__ . '/src/ParsedFile.php';
require __DIR__ . '/src/UnreadableSymbol.php';
require __DIR__ . '/src/OutsideSymbols.php';
require __DIR__ . '/src/TreeCache.php';
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
