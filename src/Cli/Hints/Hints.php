<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Rewriting\RewriteApplier;
use JesseGall\CodeCommandments\Cli\Rewriting\UnifiedDiff;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;

/**
 * `commandments hints [path] [--changes] [--branch[=BASE]] [--dry-run[=FILE]]`
 *
 * Brings every Spatie `Data` class's magic surface in line with the spatie-data
 * skill (see {@see DataHintScribe}): renames non-`from…` object factories to
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
        $options = HintsOptions::fromArgs($args);

        if (! is_dir($options->path)) {
            fwrite(STDERR, "Not a directory: {$options->path}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($args, $options->path);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        $rewrites = new DataHintScribe()->rewrites(Codebase::scan($options->path), $scope);

        if ($rewrites === []) {
            $this->out("\033[32m✓ Data @method hints already current — nothing to rewrite.\033[0m\n");

            return 0;
        }

        if ($options->dryRun) {
            return $this->preview($rewrites, $options->path, $options->dryRunFile);
        }

        $written = new RewriteApplier()->apply($rewrites);

        $count = count($written);
        $this->out("\033[32m✓ Rewrote {$count} " . ($count === 1 ? 'file' : 'files') . ".\033[0m\n");

        foreach ($written as $path) {
            $this->out('  ' . $this->relative($path, $options->path) . "\n");
        }

        return 0;
    }

    /**
     * @param  array<string, string>  $rewrites
     */
    private function preview(array $rewrites, string $base, ?string $file): int
    {
        $diff = new UnifiedDiff()->of($rewrites, $base);

        if ($file !== null) {
            file_put_contents($file, $diff);
            $this->out("\033[2m↳ dry-run diff for " . count($rewrites) . " file(s) written to {$file}\033[0m\n");

            return 0;
        }

        $this->out($diff);

        return 0;
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
