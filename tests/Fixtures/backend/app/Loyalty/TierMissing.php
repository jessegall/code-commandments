<?php

namespace Shop\Loyalty;

use LogicException;

/**
 * No loyalty tier starts at the points asked about.
 */
final class TierMissing extends LogicException
{
    public static function at(int $points): self
    {
        return new self("No tier starts at {$points} points.");
    }
}
