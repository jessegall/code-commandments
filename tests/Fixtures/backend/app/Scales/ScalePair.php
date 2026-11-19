<?php

namespace Shop\Scales;

use JesseGall\CodeCommandments\Sins\Backend\CapabilityProbeHub;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Two scales side by side, each asked about the one capability it may have.
 */
#[Righteous(CapabilityProbeHub::class)]
final class ScalePair
{
    public function __construct(private readonly object $front, private readonly object $back) {}

    public function frontTare(): string
    {
        return $this->front instanceof Tares ? $this->front->tare() : 'tare unsupported';
    }

    public function backTare(): string
    {
        return $this->back instanceof Tares ? $this->back->tare() : 'tare unsupported';
    }

    public function backPieces(): string
    {
        return $this->back instanceof CountsPieces ? $this->back->pieceCount() : 'counting unsupported';
    }
}
