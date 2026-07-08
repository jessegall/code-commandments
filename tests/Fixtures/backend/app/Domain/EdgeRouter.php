<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * A THREE-`instanceof` chain — `$e instanceof Wire && $e->from instanceof Port && $e->to instanceof Port` —
 * repeated in a predicate and a tagger. A deeper guard, a third distinct shape.
 */
final class EdgeRouter
{
    #[Sinful(RepeatedTypeGuard::class)]
    public function routable($e): bool
    {
        return $e instanceof Wire && $e->from instanceof Port && $e->to instanceof Port;
    }

    #[Sinful(RepeatedTypeGuard::class)]
    public function tag($e): string
    {
        $wired = $e instanceof Wire && $e->from instanceof Port && $e->to instanceof Port;

        return $wired ? 'wired' : 'loose';
    }

    public function summary(int $wired, int $loose): string
    {
        $total = $wired + $loose;

        return "{$total} edges: {$wired} wired, {$loose} loose";
    }

    public function throughput(float $seconds, int $edges): float
    {
        return $seconds > 0.0 ? $edges / $seconds : 0.0;
    }
}
