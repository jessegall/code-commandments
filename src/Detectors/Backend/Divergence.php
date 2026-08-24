<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

/**
 * One path found to be another doing strictly less: which is poorer, which it should route through,
 * and what it lacks.
 */
final class Divergence
{
    /**
     * @param  list<string>  $missing  what the poorer path does not reach
     */
    public function __construct(
        public readonly string $poorer,
        public readonly string $richer,
        public readonly array $missing,
    ) {}
}
