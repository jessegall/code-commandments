<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Scenario 2 — a frontend-bound gauge. Its optional threshold band is a nullable ENUM, mapped to a colour
 * ramp; a bounded-arithmetic shape distinct from the trail, panel, and metric scenarios.
 */
#[Sinful(NullableWireObject::class)]
#[TypeScript]
final class WireNode extends Data
{
    public function __construct(
        public readonly int $percent,
        public readonly int $min = 0,
        public readonly int $max = 100,
        public readonly ?Band $band = null,
    ) {}

    public function clamped(): int
    {
        return max($this->min, min($this->max, $this->percent));
    }

    public function fraction(): float
    {
        $span = $this->max - $this->min;

        return $span === 0 ? 0.0 : ($this->clamped() - $this->min) / $span;
    }

    public function colour(): string
    {
        return match ($this->band) {
            Band::Danger => '#ef4444',
            Band::Warn => '#f59e0b',
            default => '#22c55e',
        };
    }
}

enum Band: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Danger = 'danger';
}

/**
 * The FIX for {@see WireNode}: the genuinely-absent band is typed `Band|Optional = new Optional()`,
 * not `?Band = null`. The wire now OMITS the key entirely when there is no band — which is what the
 * frontend's `gauge.band?.` reads as "absent" — instead of shipping `"band": null`.
 */
#[Fixed(NullableWireObject::class)]
#[TypeScript]
final class WireDial extends Data
{
    public function __construct(
        public readonly int $percent,
        public readonly int $floor = 0,
        public readonly int $ceiling = 100,
        public readonly Band|Optional $band = new Optional(),
    ) {}

    public function needle(): int
    {
        return max($this->floor, min($this->ceiling, $this->percent));
    }

    public function ramp(): string
    {
        if ($this->band instanceof Optional) {
            return '#22c55e';
        }

        return $this->band === Band::Danger ? '#ef4444' : '#f59e0b';
    }
}
