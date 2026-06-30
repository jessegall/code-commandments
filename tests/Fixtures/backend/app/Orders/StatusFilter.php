<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\InArrayMirrorsEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Tests a raw status string against a hand-listed set that mirrors the
 * OrderStatus enum — the enum already seals the set.
 */
final class StatusFilter
{
    #[Sinful(InArrayMirrorsEnumDetector::class)]
    public function isOpen(string $status): bool
    {
        return in_array($status, ['pending', 'paid'], true);
    }

    public function isWeekend(string $day): bool
    {
        return in_array($day, ['saturday', 'sunday'], true);
    }
}
