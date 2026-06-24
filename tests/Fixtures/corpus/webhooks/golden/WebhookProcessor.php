<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Verifies an inbound webhook and dispatches it to its registered handler.
 */
final class WebhookProcessor
{
    public function __construct(
        private readonly SignatureVerifier $verifier,
        private readonly EventHandlerRegistry $handlers,
    ) {}

    public function process(string $body, string $signature, WebhookPayload $payload): void
    {
        $this->verifier->verify($body, $signature);

        $this->handlers->get($payload->event)->handle($payload);
    }
}
