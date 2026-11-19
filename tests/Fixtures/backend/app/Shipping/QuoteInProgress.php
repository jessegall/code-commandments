<?php

namespace Shop\Shipping;

use LogicException;

/**
 * A parcel was quoted again while its first quote was still being worked out.
 */
final class QuoteInProgress extends LogicException
{
    public static function forParcel(string $parcel): self
    {
        return new self("Already quoting {$parcel}.");
    }
}
