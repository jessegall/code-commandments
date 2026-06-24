<?php

declare(strict_types=1);

namespace App\Webhooks;

use RuntimeException;

/**
 * Thrown when no handler is registered for an inbound webhook event.
 */
final class HandlerNotFoundException extends RuntimeException
{
    public static function forEvent(WebhookEvent $event): self
    {
        return new self("No webhook handler registered for `{$event->value}`.");
    }
}
