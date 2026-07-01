<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

/**
 * The parsed options of a `hints` run — everything EXCEPT the scope flags
 * (`--changes`/`--branch`), which {@see \JesseGall\CodeCommandments\Cli\Scope\Scope::fromArgs} owns.
 */
final class HintsOptions
{
    public function __construct(
        public readonly string $path,
        public readonly bool $dryRun,
        public readonly ?string $dryRunFile,
    ) {}

    public static function fromArgs(array $args): self
    {
        $path = '.';
        $dryRun = false;
        $dryRunFile = null;

        foreach ($args as $arg) {
            if ($arg === '--dry-run') {
                $dryRun = true;
            } elseif (str_starts_with($arg, '--dry-run=')) {
                $dryRun = true;
                $dryRunFile = substr($arg, 10);
            } elseif (! str_starts_with($arg, '--')) {
                $path = $arg;
            }
        }

        return new self(rtrim($path, '/'), $dryRun, $dryRunFile);
    }
}
