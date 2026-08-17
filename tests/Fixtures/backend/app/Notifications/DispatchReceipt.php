<?php

namespace Shop\Notifications;

use JesseGall\CodeCommandments\Sins\Backend\ErasedNullObject;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\Text\BlankText;

/**
 * The record a courier hands back for a dispatch. `of` defaults the complaint to a Null Object the
 * `string` parameter coerces straight back to `''`, so the wrapper never reaches the receipt; `noted`
 * says the same absence in the type.
 */
final readonly class DispatchReceipt
{
    private function __construct(
        public string $consignment,
        public ?string $complaint,
    ) {}

    #[Sinful(ErasedNullObject::class)]
    public static function of(string $consignment, string $complaint = new BlankText): self
    {
        return new self($consignment, $complaint);
    }

    #[Fixed(ErasedNullObject::class)]
    #[Righteous(ErasedNullObject::class)]
    public static function noted(string $consignment, ?string $complaint = null): self
    {
        return new self($consignment, $complaint);
    }
}
