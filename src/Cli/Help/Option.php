<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Help;

/**
 * One flag a command understands — the spec as it is typed (`--sin=NAME`, `--branch[=BASE]`) and what
 * it does. Declared beside the command that READS it, so an option can never be documented into
 * existence, nor quietly added without a line here.
 */
final class Option
{
    public function __construct(
        public readonly string $spec,
        public readonly string $does,
    ) {}
}
