<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * A bounds guard repeated with its conjuncts REORDERED — `inside` reads `min` then `max`, `clamp` reads
 * `max` then `min`. Order-independent canonicalisation collapses them to one guard, so both flag.
 */
final class RangeFilter
{
    #[Sinful(RepeatedGuard::class)]
    public function inside($value, $bound): bool
    {
        return $value >= $bound->min && $value <= $bound->max;
    }

    #[Sinful(RepeatedGuard::class)]
    public function clamp($value, $bound): mixed
    {
        if ($value <= $bound->max && $value >= $bound->min) {
            return $value;
        }

        return $value < $bound->min ? $bound->min : $bound->max;
    }

    public function span($bound): float
    {
        return (float) ($bound->max - $bound->min);
    }

    public function midpoint($bound): float
    {
        return ($bound->min + $bound->max) / 2.0;
    }

    public function label(string $unit, int $count): string
    {
        return $count . ' ' . $unit . ($count === 1 ? '' : 's');
    }
}
