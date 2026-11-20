<?php

namespace Shop\Webhooks;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\RawWebhookPayload;
use Shop\Services\OrderService;

final class WebhookDecoder
{
    #[Sinful(ConcreteCrossModuleDependency::class)]
    public function __construct(private readonly OrderService $orders) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $raw = RawWebhookPayload::from($payload);

        if ($raw->type === null || $raw->orderId === null || $raw->amountCents === null) {
            return;
        }

        if ($raw->type === 'payment.captured') {
            $this->orders->settle($this->orders->find((int) $raw->orderId));
        }
    }
}
