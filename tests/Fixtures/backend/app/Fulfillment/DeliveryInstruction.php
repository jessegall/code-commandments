<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\PhantomNullable;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * RIGHTEOUS: `$note` is a genuine optional — a shopper may leave delivery instructions or not, and its
 * reader ({@see DeliveryNotePrinter}) null-checks it before use. A guard exists in its flow, so the
 * `?string` is honest and the detector leaves it alone.
 */
#[Righteous(PhantomNullable::class)]
final class DeliveryInstruction
{
    public function __construct(
        public readonly ?string $note,
        public readonly bool $signatureRequired,
    ) {}
}
