<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ErasedNullObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\Text\BlankText;

/**
 * The paper strip a kiosk prints after a sale. The footer is promoted as a total `string` holding a Null
 * Object, so the field the printer reads is `''` and the object it was given no longer exists.
 */
final readonly class ReceiptStrip
{
    #[Sinful(ErasedNullObject::class)]
    public function __construct(
        public string $till,
        public int $total,
        public string $footer = new BlankText,
    ) {}

    public function printed(): string
    {
        return $this->till . ' ' . $this->total . ' ' . $this->footer;
    }
}
