<?php

declare(strict_types=1);

/**
 * Experiment: dump every PHP file's AST to JSON, keyed for per-file invalidation.
 *
 * Each source file `src/Foo/Bar.php` is parsed once and written to
 * `.ast-cache/<sha1(path)>.json` as:
 *
 *     {
 *       "path":          "src/Foo/Bar.php",     // relative to the repo root
 *       "hash":          "<sha1 of file bytes>", // change => stale => re-parse
 *       "parserVersion": "<php-parser version>", // bump => whole cache stale
 *       "ast":           [ ...nikic AST nodes... ]
 *     }
 *
 * Round-trips back with PhpParser\JsonDecoder. A later run only re-parses files
 * whose `hash` changed (per-file invalidation) — the point of the experiment.
 *
 * Usage:
 *   php scripts/ast-dump.php [targetDir ...]    # default: src
 *   php scripts/ast-dump.php --stats            # only print counts, write nothing
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpParser\NodeDumper;
use PhpParser\ParserFactory;

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$statsOnly = in_array('--stats', $args, true);
$targets = array_values(array_filter($args, static fn (string $a): bool => $a !== '--stats'));

if ($targets === []) {
    $targets = ['src'];
}

$cacheDir = $root . '/.ast-cache';

if (! $statsOnly && ! is_dir($cacheDir) && ! mkdir($cacheDir, 0755, true) && ! is_dir($cacheDir)) {
    fwrite(STDERR, "Could not create {$cacheDir}\n");
    exit(1);
}

$parser = (new ParserFactory)->createForNewestSupportedVersion();
$parserVersion = \Composer\InstalledVersions::getPrettyVersion('nikic/php-parser') ?? 'unknown';

/** Collect every .php file under the target dirs. */
$files = [];
foreach ($targets as $target) {
    $base = $root . '/' . ltrim($target, '/');

    if (is_file($base)) {
        $files[] = $base;
        continue;
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

$ok = 0;
$failed = 0;
$bytesIn = 0;
$bytesOut = 0;
$start = microtime(true);

foreach ($files as $absPath) {
    $relPath = ltrim(str_replace($root, '', $absPath), '/');
    $content = file_get_contents($absPath);

    if ($content === false) {
        $failed++;
        continue;
    }

    try {
        $ast = $parser->parse($content);
    } catch (Throwable $e) {
        fwrite(STDERR, "parse failed: {$relPath} — {$e->getMessage()}\n");
        $failed++;
        continue;
    }

    $bytesIn += strlen($content);
    $ok++;

    if ($statsOnly) {
        continue;
    }

    $payload = json_encode([
        'path' => $relPath,
        'hash' => sha1($content),
        'parserVersion' => $parserVersion,
        'ast' => $ast,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        fwrite(STDERR, "json_encode failed: {$relPath}\n");
        $failed++;
        continue;
    }

    $out = $cacheDir . '/' . sha1($relPath) . '.json';
    file_put_contents($out, $payload);
    $bytesOut += strlen($payload);
}

$elapsed = microtime(true) - $start;

printf("Parsed %d file(s), %d failed in %.3fs (parser %s)\n", $ok, $failed, $elapsed, $parserVersion);
printf("Source: %.1f KB", $bytesIn / 1024);

if (! $statsOnly) {
    printf("  →  JSON: %.1f KB  (%.1fx)  in %s/\n", $bytesOut / 1024, $bytesIn > 0 ? $bytesOut / $bytesIn : 0, str_replace($root . '/', '', $cacheDir));
} else {
    printf("  (stats only — nothing written)\n");
}
