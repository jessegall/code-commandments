<?php

namespace App\Webhooks;

/**
 * The typed, verified body of an inbound webhook.
 */
final readonly class WebhookPayload
{
    public function __construct(
        public WebhookEvent $event,
        public string $resourceId,
        public int $occurredAt,
    ) {}
}
