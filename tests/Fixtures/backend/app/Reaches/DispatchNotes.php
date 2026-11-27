<?php

namespace Shop\Reaches;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;

final class DispatchNotes
{
    #[Sinful(RepeatedReach::class)]
    public function heading(Order $order): string
    {
        if ($order->status->isTerminal()) {
            return 'Closed';
        }

        return 'Open order';
    }

    #[Sinful(RepeatedReach::class)]
    public function footer(Order $order, string $signature): string
    {
        return sprintf('%s, signed %s', $order->status->label(), $signature);
    }
}
