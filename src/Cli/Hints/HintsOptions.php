<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\PhpTypes\Option;

/**
 * The parsed options of a `hints` run — everything EXCEPT the scope flags
 * (`--changes`/`--branch`), which {@see \JesseGall\CodeCommandments\Cli\Scope\Scope::fromArgs} owns.
 */
final class HintsOptions
{
    public function __construct(
        public readonly string $path,
        public readonly bool $dryRun,
        public readonly Option $dryRunFile,
    ) {}

    public static function fromInput(Input $input): self
    {
        return new self(
            path: rtrim($input->firstArgument()->unwrapOr('.'), '/'),
            dryRun: $input->hasFlag('dry-run'),
            dryRunFile: $input->option('dry-run'),
        );
    }
}
