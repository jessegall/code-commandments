<?php

namespace Shop\Warranty;

use JesseGall\CodeCommandments\Sins\Backend\MutableValueObject;
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
