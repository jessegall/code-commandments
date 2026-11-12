<?php

/**
 * Writes PHP source as one generic tree stream (contract/CONTRACT.md); bridge/php/CONTRACT.md has the flags.
 */

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

// One file's tree is encoded whole, and a generated file can hold more than PHP's default limit allows.
ini_set('memory_limit', '-1');
// The stream is the bridge's output alone: a warning the scanned project's code raises goes to STDERR.
ini_set('display_errors', 'stderr');

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
require __DIR__ . '/src/OutsideType.php';
require __DIR__ . '/src/OutsideSymbols.php';
require __DIR__ . '/src/TreeCache.php';
require __DIR__ . '/src/Stream.php';
require __DIR__ . '/src/Declaration.php';

try {
    // The one ask besides a tree: what php-parser can emit, which a census of recorded streams cannot say.
    if (in_array('--declares', array_slice($argv, 1), true)) {
        fwrite(STDOUT, json_encode(Declaration::written(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
        exit(0);
    }
    $request = Request::fromArguments(array_slice($argv, 1));
    if (! $request->serve) {
        (new Stream($request))->write(STDOUT);
        exit(0);
    }
    // A run killed outright does not always close our input, and a bridge left like that would answer no one: while
    // it waits for a request, once a second it looks whether it was taken in by the system's first process, which is
    // how its parent being gone is seen, and ends then.
    $parent = function_exists('posix_getppid') ? posix_getppid() : null;
    while (true) {
        $waiting = [STDIN];
        $none = null;
        if (stream_select($waiting, $none, $none, 1) === 0) {
            if ($parent !== null && posix_getppid() !== $parent) {
                exit(0);
            }
            continue;
        }
        if (($line = fgets(STDIN)) === false) {
            break;
        }
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
