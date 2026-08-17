<?php

namespace Shop\Ui;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Draws the banner above a shelf, with an optional strapline under the heading. `of` swears the
 * strapline is always a string, then asks whether it is really blank — so `''` is the absence, spelled
 * as a value. `lined` says the same thing in the type.
 */
final class ShelfBanner
{
    #[Sinful(BlankStringDefault::class)]
    public static function of(string $heading, string $strapline = ''): string
    {
        if ($strapline === '') {
            return $heading;
        }

        return $heading . ' — ' . $strapline;
    }

    #[Fixed(BlankStringDefault::class)]
    #[Righteous(BlankStringDefault::class)]
    public static function lined(string $heading, ?string $strapline = null): string
    {
        if ($strapline === null) {
            return $heading;
        }

        return $heading . ' — ' . $strapline;
    }
}
