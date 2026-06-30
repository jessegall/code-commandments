<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Backend\InlineThrow;
use JesseGall\CodeCommandments\Sins\Backend\MessageAtThrow;

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

    #[Sinful(GenericException::class)]
    #[Sinful(InlineThrow::class)]
    #[Sinful(MessageAtThrow::class)]
    public function reasonFor(Order $order): string
    {
        return strtoupper($order->refund_reason ?? throw new \LogicException('A refunded order must carry a reason.'));
    }
}
