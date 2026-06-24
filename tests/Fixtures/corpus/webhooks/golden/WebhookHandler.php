<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * A unit that reacts to one verified inbound webhook payload.
 */
interface WebhookHandler
{
    public function handle(WebhookPayload $payload): void;
}
