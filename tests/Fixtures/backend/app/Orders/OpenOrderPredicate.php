<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Detectors\Backend\EnumCaseOrChainDetector;
use Shop\Enums\OrderStatus;
use JesseGall\CodeCommandments\Testing\Sinful;

final class OpenOrderPredicate
{
    #[Sinful(EnumCaseOrChainDetector::class)]
    public function isOpen(OrderStatus $status): bool
    {
        return $status === OrderStatus::Pending || $status === OrderStatus::Paid;
    }
}
