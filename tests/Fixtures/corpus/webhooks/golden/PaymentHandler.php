<?php

namespace App\Webhooks;

/**
 * Handles payment events by reconciling the affected order's ledger.
 */
final class PaymentHandler implements WebhookHandler
{
    public function __construct(
        private readonly OrderLedger $ledger,
    ) {}

    public function handle(WebhookPayload $payload): void
    {
        $this->ledger->reconcile($payload->resourceId, $payload->event);
    }
}
