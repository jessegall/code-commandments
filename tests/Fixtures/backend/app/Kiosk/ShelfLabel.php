<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ErasedNullObject;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\Text\BlankText;

/**
 * The line a shelf label prints under the price. With no promotion it hands back a Null Object through a ternary
 * or a match arm, typed `string`, so the caller gets `''` and never the object.
 */
final class ShelfLabel
{
    #[Sinful(ErasedNullObject::class)]
    public function promotion(?string $offer): string
    {
        return $offer !== null ? 'Now ' . $offer : new BlankText;
    }

    #[Sinful(ErasedNullObject::class)]
    public function badge(int $stock): string
    {
        return match (true) {
            $stock > 100 => 'Plenty',
            $stock > 0 => 'Few left',
            default => new BlankText,
        };
    }

    #[Righteous(ErasedNullObject::class)]
    public function note(?string $offer): string
    {
        return $offer ?? 'No offer';
    }
}
