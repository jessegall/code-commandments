<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\EnumCaseOrChain;

use Shop\Enums\OrderStatus;
use JesseGall\CodeCommandments\Testing\Sinful;

final class OpenOrderPredicate
{
    #[Sinful(EnumCaseOrChain::class)]
    public function isOpen(OrderStatus $status): bool
    {
        return $status === OrderStatus::Pending || $status === OrderStatus::Paid;
    }
}
