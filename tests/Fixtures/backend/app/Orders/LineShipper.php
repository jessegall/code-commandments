<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\ParamResolvedFromParam;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Unpacks the line out of the order by id, then builds its shipping label — the
 * order is pure packaging here (only carried so the line can be dug out). The
 * caller should resolve the line and pass it.
 */
final class LineShipper
{
    #[Sinful(ParamResolvedFromParam::class)]
    public function labelFor(Order $order, string $lineId): string
    {
        $line = $order->lineById($lineId);

        return sprintf('%s x%d', $line->sku(), $line->quantity());
    }
}

final class Order
{
    /** @var array<string, OrderLine> */
    public array $lines = [];

    public function lineById(string $id): OrderLine
    {
        return $this->lines[$id];
    }
}

final class OrderLine
{
    public function sku(): string
    {
        return 'SKU';
    }

    public function quantity(): int
    {
        return 1;
    }
}
