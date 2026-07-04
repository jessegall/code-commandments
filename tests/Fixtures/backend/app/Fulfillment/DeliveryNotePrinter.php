<?php

namespace Shop\Fulfillment;

/**
 * Prints the delivery note on the packing slip, falling back to a default when the shopper left none.
 */
final class DeliveryNotePrinter
{
    public function line(DeliveryInstruction $instruction): string
    {
        return $instruction->note !== null ? $instruction->note : 'Leave at door';
    }
}
