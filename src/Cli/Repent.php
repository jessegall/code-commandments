<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Cli\Scope\ScopeUnavailable;
use JesseGall\CodeCommandments\Scribes\RewriteApplier;
use JesseGall\CodeCommandments\Scribes\ScribeChain;
use JesseGall\CodeCommandments\Scribes\UnifiedDiff;

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

        foreach ($this->chain($only)->steps() as $step) {
            // Each step re-scans, so it sees every earlier step's applied edits.
            $written = array_merge($written, new RewriteApplier()->apply($step->run($path, $scope)));
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

        foreach ($this->chain($only)->steps() as $step) {
            $diff .= new UnifiedDiff()->of($step->run($path, $scope), $path);
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
