<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Handles a freshly placed order by queuing it for fulfilment.
 */
final class OrderCreatedHandler implements WebhookHandler
{
    public function __construct(
        private readonly FulfilmentQueue $fulfilment,
    ) {}

    public function handle(WebhookPayload $payload): void
    {
        $this->fulfilment->enqueue($payload->resourceId);
    }
}
