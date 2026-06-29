<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Scope\BranchChanges;
use JesseGall\CodeCommandments\Cli\Scope\ChangeScope;
use JesseGall\CodeCommandments\Cli\Scope\EntireCodebase;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Cli\Scope\WorkingTreeChanges;

/**
 * `commandments hints [path] [--changes] [--branch[=BASE]] [--dry-run[=FILE]]`
 *
 * Brings every Spatie `Data` class's magic surface in line with the spatie-data
 * skill (see {@see DataHintRewriter}): renames non-`from…` object factories to
 * `from<Type>` and rewrites their call sites to `::from(...)`, then regenerates the
 * `@method from(...)` / `collect(...)` docblock hints.
 *
 * The whole path is parsed for cross-file correctness. `--changes` / `--branch`
 * scope the run to the files you've touched, but a scoped run is **docblock-only**:
 * it refreshes `@method` hints on those files and never renames (a rename's call
 * sites can live outside the scope). Renaming is a whole-tree operation only.
 *
 * By DEFAULT it writes the changes to disk. `--dry-run` instead prints a unified
 * diff of what it WOULD change (review before applying — the rename can't tell a
 * mis-prefixed factory from a legitimate named constructor); `--dry-run=FILE`
 * writes that diff to a file.
 */
final class Hints
{
    public function run(array $args): int
    {
        $options = $this->parse($args);

        if (! is_dir($options['path'])) {
            fwrite(STDERR, "Not a directory: {$options['path']}\n");

            return 2;
        }

        try {
            $onlyFiles = $this->scope($options)->restrictTo($options['path']);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        $changes = new DataHintRewriter()->rewrite(Codebase::scan($options['path']), $onlyFiles);

        if ($changes === []) {
            $this->out("\033[32m✓ Data @method hints already current — nothing to rewrite.\033[0m\n");

            return 0;
        }

        if ($options['dryRun']) {
            return $this->preview($changes, $options['path'], $options['dryRunFile']);
        }

        foreach ($changes as $path => $content) {
            file_put_contents($path, $content);
        }

        $count = count($changes);
        $this->out("\033[32m✓ Rewrote {$count} " . ($count === 1 ? 'file' : 'files') . ".\033[0m\n");

        foreach (array_keys($changes) as $path) {
            $this->out('  ' . $this->relative($path, $options['path']) . "\n");
        }

        return 0;
    }

    /**
     * @param  array<string, string>  $changes
     */
    private function preview(array $changes, string $base, ?string $file): int
    {
        $diff = '';

        foreach ($changes as $path => $content) {
            $diff .= $this->unifiedDiff($path, $content, $base);
        }

        if ($file !== null) {
            file_put_contents($file, $diff);
            $this->out("\033[2m↳ dry-run diff for " . count($changes) . " file(s) written to {$file}\033[0m\n");

            return 0;
        }

        $this->out($diff);

        return 0;
    }

    /**
     * A unified diff of a file's on-disk content vs the rewritten content, labelled
     * with the real (relative) path.
     */
    private function unifiedDiff(string $path, string $newContent, string $base): string
    {
        $old = (string) tempnam(sys_get_temp_dir(), 'cc-old-');
        $new = (string) tempnam(sys_get_temp_dir(), 'cc-new-');
        file_put_contents($old, (string) @file_get_contents($path));
        file_put_contents($new, $newContent);

        $raw = (string) @shell_exec('diff -u ' . escapeshellarg($old) . ' ' . escapeshellarg($new) . ' 2>/dev/null');

        @unlink($old);
        @unlink($new);

        $relative = $this->relative($path, $base);
        $raw = (string) preg_replace('/^--- .*$/m', "--- a/{$relative}", $raw, 1);

        return (string) preg_replace('/^\+\+\+ .*$/m', "+++ b/{$relative}", $raw, 1);
    }

    /**
     * The file scope: whole tree by default, or — with `--changes`/`--branch` — only
     * the files you've touched (docblock-only). Mirrors `judge`'s scoping.
     *
     * @param  array{changes: bool, branch: ?string, ...}  $options
     */
    private function scope(array $options): ChangeScope
    {
        return match (true) {
            $options['branch'] !== null => new BranchChanges($options['branch']),
            $options['changes'] => new WorkingTreeChanges,
            default => new EntireCodebase,
        };
    }

    /**
     * @return array{path: string, dryRun: bool, dryRunFile: ?string, changes: bool, branch: ?string}
     */
    private function parse(array $args): array
    {
        $path = '.';
        $dryRun = false;
        $dryRunFile = null;
        $changes = false;
        $branch = null;

        foreach ($args as $arg) {
            if ($arg === '--dry-run') {
                $dryRun = true;
            } elseif (str_starts_with($arg, '--dry-run=')) {
                $dryRun = true;
                $dryRunFile = substr($arg, 10);
            } elseif ($arg === '--changes' || $arg === '--git') {
                $changes = true;
            } elseif ($arg === '--branch') {
                $branch = 'main';
            } elseif (str_starts_with($arg, '--branch=')) {
                $branch = substr($arg, 9);
            } elseif (! str_starts_with($arg, '--')) {
                $path = $arg;
            }
        }

        return ['path' => rtrim($path, '/'), 'dryRun' => $dryRun, 'dryRunFile' => $dryRunFile, 'changes' => $changes, 'branch' => $branch];
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
