<?php

namespace Shop\Scales;

use JesseGall\CodeCommandments\Sins\Backend\CapabilityProbeHub;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The scale on the deli counter, read out on the customer display.
 */
#[Sinful(CapabilityProbeHub::class)]
final class CounterScale
{
    public function __construct(private readonly object $device, private readonly string $label) {}

    public function heading(): string
    {
        return strtoupper($this->label);
    }

    public function tareLine(): string
    {
        return $this->device instanceof Tares ? $this->device->tare() : 'tare unsupported';
    }

    public function piecesLine(): string
    {
        if ($this->device instanceof CountsPieces) {
            return $this->device->pieceCount();
        }

        return 'counting unsupported';
    }

    public function calibrationLine(): string
    {
        return $this->device instanceof Calibrates ? $this->device->calibrate() : 'calibration unsupported';
    }
}
