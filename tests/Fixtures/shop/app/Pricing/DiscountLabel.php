<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Detectors\Backend\NestedTernaryDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class DiscountLabel
{
    #[Sinful(NestedTernaryDetector::class)]
    public function forPercent(int $percent): string
    {
        return $percent >= 50 ? 'half off' : ($percent >= 20 ? 'sale' : 'list price');
    }
}
