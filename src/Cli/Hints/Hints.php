<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use JesseGall\CodeCommandments\Support\Path;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Scribes\RewriteApplier;
use JesseGall\CodeCommandments\Scribes\UnifiedDiff;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

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

    public function help(): Help
    {
        return Help::of('Auto-fix the Spatie Data magic surface — rename non-`from…` object factories to `from<Type>`, rewrite their call sites to `::from(...)`, and regenerate the `@method from(...)`/`collect(...)` docblock hints.')
            ->form('hints [path]', 'apply the fixes (the default — this WRITES)')
            ->form('hints [path] --dry-run[=FILE]', 'preview a unified diff instead, to the screen or a file')
            ->adopt(Scope::options())
            ->option('--dry-run[=FILE]', 'preview the rewrite as a unified diff instead of applying it')
            ->note('A scoped run (--changes/--branch) is forced to docblock-only: a rename\'s call sites can live '
                . 'outside the scope, so renaming is whole-tree only. `repent` runs this as one of its scribes; `hints` is the focused Data-only entry.');
    }

    public function run(Input $input): int
    {
        $options = HintsOptions::fromInput($input);

        if (! is_dir($options->path)) {
            fwrite(STDERR, "Not a directory: {$options->path}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($input->raw(), $options->path, Workspace::at(new HookIO()->projectRoot()));
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
            $this->out('  ' . Path::relative($path, $options->path) . "\n");
        }

        return 0;
    }

    /**
     * @param  array<string, string>  $rewrites
     */
    private function preview(array $rewrites, string $base, Option $file): int
    {
        $diff = new UnifiedDiff()->of($rewrites, $base);

        foreach ($file as $target) {
            file_put_contents($target, $diff);
            $this->out("\033[2m↳ dry-run diff for " . count($rewrites) . " file(s) written to {$target}\033[0m\n");

            return 0;
        }

        $this->out($diff);

        return 0;
    }


    private function out(string $text): void
    {
        fwrite(STDOUT, $text);
    }
}
