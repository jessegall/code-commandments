<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Scribes\RewriteApplier;
use JesseGall\CodeCommandments\Scribes\UnifiedDiff;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;

/**
 * Auto-fixes Spatie Data magic surface: renames non-`from…` factories to
 * `from<Type>`, rewrites call sites, regenerates `@method` hints. Scoped runs are
 * docblock-only; renames require whole-tree. Supports dry-run preview.
 */
final class Hints implements Command
{
    public function names(): array
    {
        return ['hints'];
    }

    public function run(Input $input): int
    {
        $options = HintsOptions::fromInput($input);

        if (! is_dir($options->path)) {
            fwrite(STDERR, "Not a directory: {$options->path}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($input->raw(), $options->path);
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
