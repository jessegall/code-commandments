<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

use JesseGall\CodeCommandments\Workspace;

use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * Resolves unknown locals with the project's own vue-tsc (not a package dependency).
 * Writes probe, runs vue-tsc, reads types back. Incremental with cache.
 */
final class VueTscOracle implements TypeOracle
{

    public function __construct(
        private readonly string $root,
        private readonly ProcessRunner $runner,
    ) {}

    /**
     * The oracle for the project a scan path lives in — walking up from $path to the nearest
     * ancestor that ships `vue-tsc`, or null when none does (the common case: no checker, no
     * oracle). This is how the CLI opts a project in without any configuration.
     */
    public static function locate(string $path, ?ProcessRunner $runner = null): ?self
    {
        for ($dir = is_dir($path) ? rtrim($path, '/') : dirname($path); ; $dir = $parent) {
            if (self::available($dir)) {
                return new self($dir, $runner ?? new ShellProcessRunner());
            }

            if (($parent = dirname($dir)) === $dir) {
                return null;
            }
        }
    }

    /**
     * Does the target project ship `vue-tsc`? Only then is this oracle wired in.
     */
    public static function available(string $root): bool
    {
        return is_file(self::binary($root));
    }

    public function resolveAll(array $queries): array
    {
        $probes = $this->writeProbes($queries);

        if ($probes === []) {
            return [];
        }

        try {
            $output = $this->runner->run(self::binary($this->root), $this->arguments(), $this->root);
        } finally {
            array_map('unlink', $probes);
        }

        $resolved = [];

        foreach ($probes as $path => $probe) {
            $resolved[$path] = TscDiagnostics::types($this->forFile($probe, $output));
        }

        return $resolved;
    }

    /**
     * A probe copy of every component beside the original, in one sweep — so a SINGLE checker run
     * over the project resolves them all at once.
     *
     * @param  array<string, array{sfc: Sfc, names: list<string>}>  $queries
     * @return array<string, string>  component path => the probe file written for it
     */
    private function writeProbes(array $queries): array
    {
        $probes = [];

        foreach ($queries as $path => $query) {
            $source = new TypeProbe($query['sfc'], $query['names'])->source();

            if ($source !== null) {
                $probes[$path] = $this->writeProbe($query['sfc'], $source);
            }
        }

        return $probes;
    }

    /**
     * Keep only the diagnostics for OUR probe file — the project's own pre-existing errors share
     * the run but are none of our business.
     */
    private function forFile(string $probe, string $output): string
    {
        $base = basename($probe);
        $lines = array_filter(explode("\n", $output), static fn (string $line): bool => str_contains($line, $base));

        return implode("\n", $lines);
    }

    private function writeProbe(Sfc $component, string $source): string
    {
        $path = dirname($component->path) . '/__cc_probe_' . uniqid() . '.vue';
        file_put_contents($path, $source);

        return $path;
    }

    /**
     * @return list<string>
     */
    private function arguments(): array
    {
        return [
            '--noEmit',
            '--skipLibCheck',
            '--pretty', 'false',
            '--noErrorTruncation', // emit COMPLETE types in diagnostics, never `… N more …` (unparseable)
            '--incremental',
            '--tsBuildInfoFile', $this->buildInfo(),
        ];
    }

    private function buildInfo(): string
    {
        $path = Workspace::at(rtrim($this->root, '/'))->shared('.vue-tsc.tsbuildinfo');

        if (! is_dir($directory = dirname($path))) {
            @mkdir($directory, 0777, true);
        }

        return $path;
    }

    private static function binary(string $root): string
    {
        return rtrim($root, '/') . '/node_modules/.bin/vue-tsc';
    }
}
