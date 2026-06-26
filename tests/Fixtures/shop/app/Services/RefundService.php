<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Detectors\Backend\GenericExceptionDetector;
use JesseGall\CodeCommandments\Detectors\Backend\InlineThrowDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;

final class RefundService
{
    public function refund(Order $order, int $amountCents): void
    {
        if (! $order->status->isTerminal()) {
            return;
        }

        $order->markRefunded($amountCents);
    }

    #[Sinful(GenericExceptionDetector::class)]
    #[Sinful(InlineThrowDetector::class)]
    public function reasonFor(Order $order): string
    {
        return strtoupper($order->refund_reason ?? throw new \LogicException('A refunded order must carry a reason.'));
    }
}
