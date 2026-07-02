<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

/**
 * The seam over an external process — the one genuinely un-unit-testable thing (spawning
 * `vue-tsc`) isolated behind an interface, so {@see VueTscOracle} is driven by a fake in tests
 * and the shell only in production ({@see ShellProcessRunner}).
 */
interface ProcessRunner
{
    /**
     * Run $binary with $arguments in $cwd; return its combined stdout + stderr (compiler
     * diagnostics land on either, so both are captured).
     *
     * @param  list<string>  $arguments
     */
    public function run(string $binary, array $arguments, string $cwd): string;
}
