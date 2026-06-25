<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Doctrines;

/**
 * A finding tagged with its doctrine placement and method region, ready for the
 * cascade. `finding` is the opaque original (a {@see \JesseGall\CodeCommandments\Results\Finding}
 * in the live pipeline, or any payload in tests) handed back unchanged for the
 * survivors. A null `doctrine`/`band` marks a singleton — never suppressed.
 */
final readonly class Ranked
{
    public function __construct(
        public mixed $finding,
        public ?string $doctrine,
        public ?int $band,
        public string $path,
        public int $startLine,
        public int $endLine,
    ) {}
}
