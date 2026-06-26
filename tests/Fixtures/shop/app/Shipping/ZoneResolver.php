<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Detectors\Backend\MatchDefaultReturnsNullDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Resolves a country code to a shipping zone, returning an empty array for an
 * unrecognised country rather than throwing.
 */
final class ZoneResolver
{
    /**
     * @return array<int, string>
     */
    #[Sinful(MatchDefaultReturnsNullDetector::class)]
    public function rates(string $country): array
    {
        return match ($country) {
            'NL', 'BE', 'DE' => ['eu-standard', 'eu-express'],
            'US', 'CA' => ['na-standard'],
            default => [],
        };
    }
}
