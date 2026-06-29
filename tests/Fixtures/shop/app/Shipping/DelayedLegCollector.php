<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Detectors\Backend\FeatureEnvyDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Walks the itinerary's own legs to gather the delayed ones — a query over the
 * itinerary's collection that belongs on it (`$itinerary->delayedLegs()`).
 */
final class DelayedLegCollector
{
    /**
     * @return list<string>
     */
    #[Sinful(FeatureEnvyDetector::class)]
    public function collect(Itinerary $itinerary): array
    {
        $delayed = [];

        foreach ($itinerary->legModes as $mode) {
            if (str_starts_with($mode, 'delayed')) {
                $delayed[] = $mode;
            }
        }

        return $delayed;
    }
}
