<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

/**
 * Result of a successful upstream walk — the method where a DTO should be
 * introduced.
 */
final readonly class OriginTrace
{
    public function __construct(
        public string $originClassFqcn,
        public string $originMethod,
        public string $file,
        public int $line,
        public int $hops,
        public string $reason,
    ) {}
}
