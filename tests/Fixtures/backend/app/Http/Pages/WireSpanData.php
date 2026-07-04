<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\TransformerWithoutTsType;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

/**
 * A custom `DateRangeTransformer` serialises `DateRange` to an ISO interval string, but no
 * `#[TypeScriptType]` declares it — the frontend type stays `DateRange`. Pair the transformer with the
 * transformed shape.
 */
final class WireSpanData extends Data
{
    #[Sinful(TransformerWithoutTsType::class)]
    public function __construct(
        #[WithTransformer(DateRangeTransformer::class)]
        public readonly DateRange $span,
        public readonly string $timezone,
        public readonly bool $allDay,
    ) {}

    public function headline(): string
    {
        if ($this->allDay) {
            return 'All day';
        }

        return $this->span->start . ' to ' . $this->span->end;
    }

    public string $zoneLabel {
        get => strtoupper($this->timezone);
    }

    public function isSameDay(): bool
    {
        return $this->span->start === $this->span->end;
    }

    public function bracketed(): string
    {
        return '[' . $this->headline() . ']';
    }
}
