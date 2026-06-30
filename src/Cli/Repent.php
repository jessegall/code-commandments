<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase as AstCodebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Detectors\Catalog as Detectors;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Catalog as Scribes;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\RewriteApplier;
use JesseGall\CodeCommandments\Scribes\UnifiedDiff;
use JesseGall\CodeCommandments\Vue\Codebase as VueCodebase;
use JesseGall\CodeCommandments\Vue\Detector;

/**
 * `commandments repent [path] [--changes|--branch[=BASE]] [--dry-run[=FILE]] [--only=NAME]`
 *
 * Repents the sins — the CLI that RUNS the Scribes (the "scribe" is the code, `repent`
 * is the verb). Two kinds of rewriter, one command:
 *   - the **maintenance Scribes** ({@see Scribes}) over the PHP AST — Spatie Data magic,
 *     redundant arrow-fn return types — scope-aware via `--changes`/`--branch`;
 *   - the **Repentable detectors'** scribes over the Vue components — extract a
 *     component, hoist a `v-if` chain to `<SwitchCase>` — fed each detector's own
 *     findings (it never re-queries).
 *
 * By DEFAULT it writes; `--dry-run[=FILE]` previews a unified diff. `--only=NAME` runs
 * a single rewriter (partial match on a Scribe name or a frontend Detector name).
 */
final class Repent
{
    public function run(array $args): int
    {
        $options = $this->parse($args);

        if (! is_dir($options['path'])) {
            fwrite(STDERR, "Not a directory: {$options['path']}\n");

            return 2;
        }

        try {
            $scope = Scope::fromArgs($args, $options['path']);
        } catch (ScopeUnavailable $unavailable) {
            fwrite(STDERR, $unavailable->getMessage() . "\n");

            return 2;
        }

        return $options['dryRun']
            ? $this->preview($options['path'], $scope, $options['only'], $options['dryRunFile'])
            : $this->apply($options['path'], $scope, $options['only']);
    }

    private function apply(string $path, Scope $scope, ?string $only): int
    {
        $written = [];

        foreach ($this->maintenance($only) as $scribe) {
            // Re-scan per scribe so a later one sees an earlier one's edits.
            $written = array_merge($written, new RewriteApplier()->apply($scribe->rewrites(AstCodebase::scan($path), $scope)));
        }

        $components = VueCodebase::scan($path);

        foreach ($this->repentable($only) as $detector) {
            $rewrites = $this->scribe($detector)->rewrite($detector->find($components));
            $written = array_merge($written, new RewriteApplier()->apply($rewrites));
        }

        $written = array_values(array_unique($written));

        if ($written === []) {
            $this->out("\033[32m✓ Nothing to repent.\033[0m\n");

            return 0;
        }

        $count = count($written);
        $this->out("\033[32m✓ Repented {$count} " . ($count === 1 ? 'file' : 'files') . ".\033[0m\n");

        foreach ($written as $file) {
            $this->out('  ' . $this->relative($file, $path) . "\n");
        }

        return 0;
    }

    private function preview(string $path, Scope $scope, ?string $only, ?string $file): int
    {
        $diff = '';

        foreach ($this->maintenance($only) as $scribe) {
            $diff .= new UnifiedDiff()->of($scribe->rewrites(AstCodebase::scan($path), $scope), $path);
        }

        $components = VueCodebase::scan($path);

        foreach ($this->repentable($only) as $detector) {
            $diff .= new UnifiedDiff()->of($this->scribe($detector)->rewrite($detector->find($components)), $path);
        }

        if ($diff === '') {
            $this->out("\033[32m✓ Nothing to repent.\033[0m\n");

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
     * The maintenance Scribes matching --only.
     *
     * @return list<\JesseGall\CodeCommandments\Scribes\Scribe>
     */
    private function maintenance(?string $only): array
    {
        return array_values(array_filter(
            Scribes::all(),
            static fn ($scribe): bool => $only === null || stripos($scribe->name(), $only) !== false,
        ));
    }

    /**
     * The Repentable frontend detectors matching --only (by detector OR scribe name).
     *
     * @return list<Detector&Repentable>
     */
    private function repentable(?string $only): array
    {
        return array_values(array_filter(
            Detectors::frontend(),
            fn (Detector $detector): bool => $detector instanceof Repentable
                && ($only === null
                    || stripos($this->basename($detector), $only) !== false
                    || stripos($this->scribe($detector)->name(), $only) !== false),
        ));
    }

    private function scribe(Detector&Repentable $detector): RepentScribe
    {
        $spec = $detector->scribe();

        if (is_string($spec)) {
            return new $spec();
        }

        return $spec instanceof RepentScribe ? $spec : $spec();
    }

    private function basename(object $object): string
    {
        $parts = explode('\\', $object::class);

        return end($parts);
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
            } elseif (str_starts_with($arg, '--detector=')) {
                $only = substr($arg, 11);
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
