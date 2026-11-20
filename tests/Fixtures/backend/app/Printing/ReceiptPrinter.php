<?php

namespace Shop\Printing;

use JesseGall\CodeCommandments\Sins\Backend\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Backend\CapabilityProbeHub;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The till's receipt printer, whatever driver the shop plugged in.
 */
#[Sinful(CapabilityProbeHub::class)]
final class ReceiptPrinter
{
    public function __construct(private object $driver) {}

    public function isDoubleSided(): bool
    {
        if ($this->driver instanceof Duplexes) {
            return $this->driver->duplex();
        }

        return false;
    }

    public function cutsAfterReceipt(): bool
    {
        if ($this->driver instanceof CutsPaper) {
            return $this->driver->cut();
        }

        return false;
    }

    #[Sinful(BareStatePredicate::class)]
    public function needsInk(): bool
    {
        if ($this->driver instanceof ReportsInk) {
            return $this->driver->inkLow();
        }

        return false;
    }
}
