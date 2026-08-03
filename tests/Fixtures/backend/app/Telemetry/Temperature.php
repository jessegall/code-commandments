<?php

namespace Shop\Warranty;

use JesseGall\CodeCommandments\Sins\Backend\MutableValueObject;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A reading with its own scale, taken once and then re-scaled in place. The field is not promoted
 * but the constructor still takes it, so it is what the caller ASKED for — and converting rewrites
 * it, leaving anything that already recorded this reading describing a temperature nobody measured.
 */
#[Sinful(MutableValueObject::class)]
final class Temperature
{
    private float $degrees;

    private string $scale;

    public function __construct(float $degrees, string $scale)
    {
        $this->degrees = $degrees;
        $this->scale = $scale;
    }

    public function convertToCelsius(): void
    {
        $this->degrees = ($this->degrees - 32) * 5 / 9;
        $this->scale = 'C';
    }

    public function reading(): string
    {
        return round($this->degrees, 1) . '°' . $this->scale;
    }

    public function belowFreezing(): bool
    {
        return $this->scale === 'C' ? $this->degrees < 0 : $this->degrees < 32;
    }
}

/**
 * The same reading, derived instead of re-scaled: `readonly` on the CLASS makes it final once built, and
 * `withCelsius()` answers with a NEW reading — so whatever recorded this one still describes the
 * temperature that was actually measured.
 */
#[Fixed(MutableValueObject::class)]
final readonly class CalibratedReading
{
    public function __construct(
        public float $degrees,
        public string $scale,
    ) {}

    public function withCelsius(): self
    {
        return new self(($this->degrees - 32) * 5 / 9, 'C');
    }
}
