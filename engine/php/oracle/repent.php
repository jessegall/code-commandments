<?php

/**
 * Writes what the PHP tool's repent rewrites in each fixture, one JSON line per file: every step of the chain run
 * alone over the fixture as it is, then the whole chain swept to its fixed point, and the steps that broke.
 * Paths are relative to the fixture. Usage: php repent.php <fixture>... > repent.jsonl
 */

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Cli\Repent;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Scribes\ScribeChain;
use JesseGall\CodeCommandments\WorkingCopy;
use JesseGall\PhpTypes\Option;
use ReflectionMethod;
use ReflectionProperty;

require __DIR__ . '/../../../vendor/autoload.php';

/** @param  array<string, mixed>  $line */
function write(array $line): void
{
    echo json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), "\n";
}

/** @param  array<string, string>  $files */
function writeFiles(string $fixture, string $root, string $step, array $files): void
{
    ksort($files);
    foreach ($files as $path => $content) {
        write(['fixture' => $fixture, 'step' => $step, 'path' => substr($path, strlen($root) + 1), 'content' => $content]);
    }
}

foreach (array_map(realpath(...), array_slice($argv, 1)) as $root) {
    $fixture = basename($root);
    // The fixture's own config and custom rules shape the chain, as they do when repent runs inside it.
    chdir($root);

    // Every package counts as installed, as the fixture tests every package's rules.
    $chain = ScribeChain::default(static fn (): bool => true);
    foreach ($chain->steps() as $step) {
        try {
            writeFiles($fixture, $root, $step->name(), $step->run([$root], Scope::everything(), new WorkingCopy()));
        } catch (\Throwable $failure) {
            write(['fixture' => $fixture, 'step' => $step->name(), 'error' => $failure->getMessage()]);
        }
    }

    $repent = new Repent();
    (new ReflectionProperty($repent, 'ignorePackages'))->setValue($repent, true);
    $converged = (new ReflectionMethod($repent, 'converge'))->invoke($repent, [$root], Scope::everything(), Option::none());
    writeFiles($fixture, $root, '*', $converged->files);
    foreach ($converged->skipped as $skipped) {
        write(['fixture' => $fixture, 'step' => '*', 'skipped' => $skipped]);
    }
}
