<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualOutputTransform;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

/**
 * A `#[Computed]` method reshapes a single `GeoPoint` — mixing property reads and a method call off the
 * one receiver — into a wire array. Same flatten as a getter hook, older shape (and also an array-bag
 * return): a transformer on a real `GeoPoint` slot should own the projection.
 */
final class LocationPage extends Data
{
    /** @param list<GeoPoint> $waypoints */
    public function __construct(
        public readonly GeoPoint $origin,
        public readonly array $waypoints,
        public readonly string $placeName,
    ) {}

    #[Sinful(ManualOutputTransform::class)]
    #[Sinful(ArrayReturnBag::class)]
    #[Computed]
    public function marker(): array
    {
        return ['lat' => $this->origin->lat, 'lng' => $this->origin->lng, 'origin' => $this->origin->label()];
    }

    public function northernWaypoints(): int
    {
        $count = 0;

        foreach ($this->waypoints as $waypoint) {
            if ($waypoint->lat > 0.0) {
                $count++;
            }
        }

        return $count;
    }
}
