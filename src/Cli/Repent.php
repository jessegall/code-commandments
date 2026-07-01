<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\RewriteApplier;
use JesseGall\CodeCommandments\Scribes\ScribeChain;
use JesseGall\CodeCommandments\Scribes\UnifiedDiff;
use JesseGall\CodeCommandments\WorkingCopy;

/**
 * `commandments repent [path] [--changes|--branch[=BASE]] [--dry-run[=FILE]] [--only=NAME]`
 *
 * Repents the sins — the CLI that RUNS the Scribes (the "scribe" is the code, `repent`
 * is the verb). It walks the {@see ScribeChain}: the in-place fixers (Spatie Data hints,
 * redundant returns, `<SwitchCase>`, control-flow wrapping) then the component
 * EXTRACTORS, each re-scanning so it sees the previous step's edits. The chain's order
 * is the consumer's to change — see {@see chain} and `.commandments/repent.php`.
 *
 * By DEFAULT it writes; `--dry-run[=FILE]` previews a unified diff. `--only=NAME` runs
 * the chain steps whose name matches (a Scribe or frontend Detector name).
 */
final class Repent
{
    /** The fixpoint cap — a backstop against an oscillating step, far above any real chain depth. */
    private const int MAX_SWEEPS = 10;

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

        // The SAME canon `judge` reads decides which source roots to rewrite — so `repent` never
        // touches `tests/` (or anything outside the canon) that `judge` would never have flagged.
        $roots = new Canon()->rootsFor($options['path'], $options['pathGiven'])->paths;

        return $options['dryRun']
            ? $this->preview($options['path'], $roots, $scope, $options['only'], $options['dryRunFile'])
            : $this->apply($options['path'], $roots, $scope, $options['only']);
    }

    /**
     * @param  list<string>  $roots  the canon source roots to scan and rewrite
     */
    private function apply(string $path, array $roots, Scope $scope, ?string $only): int
    {
        $converged = $this->converge($roots, $scope, $only);
        $written = new RewriteApplier()->apply($converged);

        if ($written === []) {
            $this->out("\033[32m✓ Nothing to repent.\033[0m\n");

            return 0;
        }

        $count = count($written);
        $this->out("\033[32m✓ Repented {$count} " . ($count === 1 ? 'file' : 'files') . ".\033[0m\n");

        foreach ($written as $file) {
            $this->out('  ' . $this->relative($file, $path) . "\n");
        }

        $this->scaffoldConstructs($converged, $written);

        return 0;
    }

    /**
     * Generate the reusable constructs the fixes just applied depend on. A fix that rewrites toward
     * a scaffolded helper (a `v-if` chain into `<SwitchCase>`) needs that helper to exist — so if a
     * written file now uses one, {@see Scaffold} mints it (idempotent, so an existing one is left
     * alone). `scaffold` and `repent` compose; running them in one command means a repented tree
     * actually compiles instead of referencing a construct the project hasn't got yet.
     *
     * @param  array<string, string>  $converged  path => final content
     * @param  list<string>  $written
     */
    private function scaffoldConstructs(array $converged, array $written): void
    {
        $output = implode("\n", array_map(static fn (string $file): string => $converged[$file] ?? '', $written));

        foreach (Catalog::all() as $detector) {
            if (! $detector instanceof Repentable) {
                continue;
            }

            foreach ($detector->sin()->scaffolds() as $scaffold) {
                // The construct is used when its name (the stub's file stem, e.g. `SwitchCase`)
                // appears in what the fix wrote — only then is it needed, so nothing over-generates.
                if (str_contains($output, pathinfo($scaffold->path, PATHINFO_FILENAME))) {
                    new Scaffold()->run(['--sin=' . $detector->sin()->name()]);

                    break;
                }
            }
        }
    }

    /**
     * @param  list<string>  $roots  the canon source roots to scan and rewrite
     */
    private function preview(string $path, array $roots, Scope $scope, ?string $only, ?string $file): int
    {
        // The converged result is diffed against pristine disk (converge writes nothing), so the
        // dry-run is exactly what an apply would produce — a fixpoint, not a single sweep.
        $diff = new UnifiedDiff()->of($this->converge($roots, $scope, $only), $path);

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
     * Run the chain to a FIXPOINT over $path and return the converged `path => content` map,
     * WITHOUT touching disk. Each sweep runs every step reading through the accumulated
     * {@see WorkingCopy} overlay — so a step sees prior steps' AND prior sweeps' edits (and any
     * file an extractor created) — until a whole sweep adds nothing new. A later step can
     * enable an earlier one (a scribe that mints `::from()`/`::collect()` call sites the hint
     * scribe then documents), so one sweep isn't always enough; the loop settles that in a
     * single command instead of leaving residue for a second `repent`. Capped so an oscillating
     * step can't spin forever.
     *
     * @param  list<string>  $roots  the canon source roots to scan and rewrite
     * @return array<string, string>  path => final content
     */
    private function converge(array $roots, Scope $scope, ?string $only): array
    {
        $steps = $this->chain($only)->steps();
        $overlay = new WorkingCopy();

        for ($sweep = 0; $sweep < self::MAX_SWEEPS; $sweep++) {
            $before = $overlay->changes();

            foreach ($steps as $step) {
                $overlay = $overlay->with($step->run($roots, $scope, $overlay));
            }

            if ($overlay->changes() === $before) {
                return $overlay->changes();
            }
        }

        fwrite(STDERR, "\033[33m⚠ repent did not settle within " . self::MAX_SWEEPS . " sweeps; applying what converged.\033[0m\n");

        return $overlay->changes();
    }

    /**
     * The chain to run — the default ordering, handed to the consumer's
     * `.commandments/repent.php` (a `fn (ScribeChain): ScribeChain`) to reorder if they
     * have one, then narrowed by `--only`.
     */
    private function chain(?string $only): ScribeChain
    {
        $chain = ScribeChain::default();
        $config = getcwd() . '/.commandments/repent.php';

        if (is_file($config)) {
            $customise = require $config;

            if (is_callable($customise) && ($customised = $customise($chain)) instanceof ScribeChain) {
                $chain = $customised;
            }
        }

        return $chain->matching($only);
    }

    /**
     * @return array{path: string, dryRun: bool, dryRunFile: ?string, only: ?string, repent: ?string}
     */
    private function parse(array $args): array
    {
        $path = '.';
        $pathGiven = false;
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
            } elseif (str_starts_with($arg, '--sin=')) {
                $only = substr($arg, 6);
            } elseif (! str_starts_with($arg, '--')) {
                $path = $arg;
                $pathGiven = true;
            }
        }

        return ['path' => rtrim($path, '/'), 'pathGiven' => $pathGiven, 'dryRun' => $dryRun, 'dryRunFile' => $dryRunFile, 'only' => $only];
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
