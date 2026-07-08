<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualOutputTransform;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Computed;

/**
 * RIGHTEOUS look-alikes the output-transform detector must NOT flag: a getter composing the object's OWN
 * scalar fields, a getter drawing from TWO different receivers (a genuine view composite), and a getter
 * projecting a nested `Data` (just a shape of another payload). None is a single value object hand-flattened.
 */
#[Righteous(ManualOutputTransform::class)]
final class WireShapesPage extends Data
{
    public function __construct(
        public readonly string $first,
        public readonly string $last,
        public readonly Money $price,
        public readonly Money $tax,
        public readonly CartLine $lead,
    ) {}

    // Own fields — receiver is $this (this Data), not a value object.
    #[Computed]
    public array $fullName { get => ['first' => $this->first, 'last' => $this->last]; }

    // Two different receivers — a real composite, not one object flattened.
    #[Computed]
    public array $totals { get => ['price' => $this->price->cents, 'tax' => $this->tax->cents]; }

    // Receiver resolves to a nested Data — just a projection of another payload.
    #[Computed]
    public array $line { get => ['sku' => $this->lead->sku, 'qty' => $this->lead->qty]; }
}
