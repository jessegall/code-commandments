<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\PositionalTupleReturn;

use JesseGall\CodeCommandments\Testing\Sinful;

final class CheckoutUnpacker
{
    /**
     * @return array{0: string, 1: list<string>, 2: int, 3: string}
     */
    #[Sinful(PositionalTupleReturn::class)]
    public function unpack(string $reference): array
    {
        $parts = explode(':', $reference);
        $order = $parts[0];
        $lines = array_slice($parts, 1);
        $count = count($lines);
        $currency = strtoupper(substr($order, 0, 3));

        return [$order, $lines, $count, $currency];
    }
}
