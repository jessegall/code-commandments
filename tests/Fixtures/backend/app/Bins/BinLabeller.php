<?php

namespace Shop\Bins;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Prints the label on a warehouse bin.
 */
#[Sinful(LoneImplementerInterface::class)]
interface BinLabeller
{
    public function label(string $bin): string;

    public function reprint(string $bin, int $copies): string;

    public function calibrate(float $darkness, int $speedInchesPerSecond): void;
}
