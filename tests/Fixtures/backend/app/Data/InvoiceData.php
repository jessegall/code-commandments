<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\DataMethodHintCollision;

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
