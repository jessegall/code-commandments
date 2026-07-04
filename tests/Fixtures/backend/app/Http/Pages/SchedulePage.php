<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualOutputTransform;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * The constructor hand-flattens a `DateRange` into a public slot's wire array — a non-computed public
 * property is no less the sin than a getter. A transformer on a real `DateRange` slot should own the shape.
 */
final class SchedulePage extends Data
{
    public array $window;

    #[Sinful(ManualOutputTransform::class)]
    public function __construct(
        DateRange $range,
        public readonly string $timezone,
    ) {
        $this->window = ['start' => $range->start, 'end' => $range->end];
    }

    public function summary(): string
    {
        return $this->window['start'] . ' until ' . $this->window['end'] . ' (' . $this->timezone . ')';
    }
}
