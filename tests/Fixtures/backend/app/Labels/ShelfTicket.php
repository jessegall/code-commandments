<?php

namespace Shop\Labels;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringOnTheWire;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The ticket clipped to a shelf edge. Its note is a total `string` and the shop's printer settings
 * carry a note of their own — but nothing on the wire ever asks THIS one whether it is blank.
 */
#[Righteous(BlankStringOnTheWire::class)]
final readonly class ShelfTicket
{
    public function __construct(
        public string $sku,
        public string $note,
    ) {}

    public function headline(): string
    {
        return $this->sku . ' ' . $this->note;
    }
}
