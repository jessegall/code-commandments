<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Rewriting\Catalog;
use JesseGall\CodeCommandments\Cli\Rewriting\RewriteApplier;
use JesseGall\CodeCommandments\Cli\Rewriting\UnifiedDiff;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;

/**
 * `commandments scribe [path] [--changes|--branch[=BASE]] [--dry-run[=FILE]] [--only=NAME]`
 *
 * Runs the Scribes (see {@see Catalog}) over a path — each emends the source at its
 * concern (Spatie Data magic, redundant arrow-fn return types, …). The whole path is
 * parsed for cross-file correctness; `--changes`/`--branch` scope which files are
 * edited. By DEFAULT it writes; `--dry-run[=FILE]` previews a unified diff. `--only`
 * runs a single Scribe by (partial) name.
 *
 * Apply runs the Scribes in sequence, re-parsing between each, so a later Scribe sees
 * an earlier one's edits.
 */
final class Scribe
{
    public function run(array $args): int
    {
        $options = $this->parse($args);

        if (! is_dir($options['path'])) {
            fwrite(STDERR, "Not a directory: {$options['path']}\n");

            return 2;
        }

        $scribes = $this->select($options['only']);

        if ($scribes === []) {
            fwrite(STDERR, "No scribe matched --only={$options['only']}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($args, $options['path']);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        if ($options['dryRun']) {
            return $this->preview($scribes, $options['path'], $scope, $options['dryRunFile']);
        }

        $written = [];

        foreach ($scribes as $scribe) {
            // Re-scan per scribe so a later one sees an earlier one's edits.
            $rewrites = $scribe->rewrites(Codebase::scan($options['path']), $scope);
            $written = array_merge($written, new RewriteApplier()->apply($rewrites));
        }

        $written = array_values(array_unique($written));

        if ($written === []) {
            $this->out("\033[32m✓ Nothing to emend.\033[0m\n");

            return 0;
        }

        $count = count($written);
        $this->out("\033[32m✓ Emended {$count} " . ($count === 1 ? 'file' : 'files') . ".\033[0m\n");

        foreach ($written as $path) {
            $this->out('  ' . $this->relative($path, $options['path']) . "\n");
        }

        return 0;
    }

    /**
     * @param  list<\JesseGall\CodeCommandments\Cli\Rewriting\Scribe>  $scribes
     */
    private function preview(array $scribes, string $path, Scope $scope, ?string $file): int
    {
        $diff = '';

        foreach ($scribes as $scribe) {
            $diff .= new UnifiedDiff()->of($scribe->rewrites(Codebase::scan($path), $scope), $path);
        }

        if ($diff === '') {
            $this->out("\033[32m✓ Nothing to emend.\033[0m\n");

            return 0;
        }

        if ($file !== null) {
            file_put_contents($file, $diff);
            $this->out("\033[2m↳ dry-run diff written to {$file}\033[0m\n");

            return 0;
        }

        $this->out($diff);

        return 0;
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Cli\Rewriting\Scribe>
     */
    private function select(?string $only): array
    {
        return array_values(array_filter(
            Catalog::all(),
            static fn ($scribe): bool => $only === null || stripos($scribe->name(), $only) !== false,
        ));
    }

    /**
     * @return array{path: string, dryRun: bool, dryRunFile: ?string, only: ?string}
     */
    private function parse(array $args): array
    {
        $path = '.';
        $dryRun = false;
        $dryRunFile = null;
        $only = null;

        foreach ($args as $arg) {
            if ($arg === '--dry-run') {
                $dryRun = true;
            } elseif (str_starts_with($arg, '--dry-run=')) {
                $dryRun = true;
                $dryRunFile = substr($arg, 10);
            } elseif (str_starts_with($arg, '--only=')) {
                $only = substr($arg, 7);
            } elseif (! str_starts_with($arg, '--')) {
                $path = $arg;
            }
        }

        return ['path' => rtrim($path, '/'), 'dryRun' => $dryRun, 'dryRunFile' => $dryRunFile, 'only' => $only];
    }

    private function relative(string $path, string $base): string
    {
        return str_starts_with($path, $base . '/') ? substr($path, strlen($base) + 1) : $path;
    }

    private function out(string $text): void
    {
        fwrite(STDOUT, $text);
    }
}
