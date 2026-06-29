<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Rewriting;

/**
 * One byte-range replacement in a source string: replace `[start, end]` (inclusive
 * offsets, as PhpParser reports them) with `text`. A pure insertion sets
 * `end = start - 1` so nothing is consumed.
 */
final class Edit
{
    public function __construct(
        public readonly int $start,
        public readonly int $end,
        public readonly string $text,
    ) {}
}
