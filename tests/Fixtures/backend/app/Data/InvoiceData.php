<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataMethodHintCollision;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;
use Spatie\LaravelData\Data;

/**
 * Typed invoice view, built from an order.
 *
 * @method static static fromOrder(Order $order)
 */
#[Sinful(DataMethodHintCollision::class)]
final class InvoiceData extends Data
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $totalCents,
        public readonly string $reference,
    ) {}

    // The `@method` tag re-declares THIS visible method — IDE "already defined".
    public static function fromOrder(Order $order): self
    {
        return self::from([
            'orderId' => $order->id,
            'totalCents' => $order->total_cents,
            'reference' => 'INV-' . $order->id,
        ]);
    }
}

/**
 * The FIX for {@see InvoiceData}: the `@method` tag names the INVISIBLE magic `from()` — the one
 * method the IDE cannot see — and says nothing about the concrete `fromOrder()` factory declared
 * below it, so no hint re-declares a real method.
 *
 * @method static static from(mixed ...$payloads)
 */
#[Fixed(DataMethodHintCollision::class)]
final class CreditNoteData extends Data
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $refundedCents,
        public readonly string $reference,
    ) {}

    public static function fromOrder(Order $order): self
    {
        return self::from([
            'orderId' => $order->id,
            'refundedCents' => $order->total_cents,
            'reference' => 'CN-' . $order->id,
        ]);
    }
}
