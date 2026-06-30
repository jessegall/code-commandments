<?php

namespace Shop\Reporting;

/**
 * Righteous twin for PositionalTupleReturnDetector: this returns a positional
 * array too, but every element is a projection of ONE source ($order) — a display
 * row, a collection, not a bundle of independent values. It must NOT be flagged.
 */
final class OrderRowView
{
    /**
     * @return list<string>
     */
    public function row(Order $order): array
    {
        return [$order->reference, $order->status, $order->placedAt];
    }
}
