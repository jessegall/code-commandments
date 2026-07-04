<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * `$span` is hand-built (`new DateRange(...)`) in every `::from()` source — a value object assembled at
 * the call site instead of hydrated by a cast from the raw start/end.
 */
final class InboundBookingData extends Data
{
    #[Sinful(ManualInputCast::class)]
    public function __construct(
        public readonly DateRange $span,
        public readonly string $timezone,
        public readonly bool $allDay,
    ) {}

    public function headline(): string
    {
        if ($this->allDay) {
            return 'All day on ' . $this->span->start;
        }

        return $this->span->start . ' to ' . $this->span->end . ' (' . $this->timezone . ')';
    }
}
