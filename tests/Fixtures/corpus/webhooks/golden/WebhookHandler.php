<?php

namespace App\Webhooks;

/**
 * A unit that reacts to one verified inbound webhook payload.
 */
interface WebhookHandler
{
    public function handle(WebhookPayload $payload): void;
}
