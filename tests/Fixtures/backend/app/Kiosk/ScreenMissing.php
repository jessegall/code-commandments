<?php

namespace Shop\Kiosk;

use OutOfBoundsException;

/**
 * A kiosk was asked to open a screen nothing mounted.
 */
final class ScreenMissing extends OutOfBoundsException
{
    public static function forSlug(string $slug): self
    {
        return new self("No kiosk screen is mounted as '{$slug}'.");
    }
}
