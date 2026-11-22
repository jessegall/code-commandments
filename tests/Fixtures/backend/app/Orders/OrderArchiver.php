<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\MassUpdateAtCallSite;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;

/**
 * Archives an order by force-filling its columns and saving them at the call site: the "archive" transition has
 * no name on the model, written the long way round instead of through update().
 */
final class OrderArchiver
{
    #[Sinful(MassUpdateAtCallSite::class)]
    public function archive(Order $order, string $shelf): void
    {
        $order->forceFill(['archived' => true, 'shelf' => $shelf])->save();
    }
}
