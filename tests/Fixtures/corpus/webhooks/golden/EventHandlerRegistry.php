<?php

namespace App\Webhooks;

/**
 * Keyed store of the handler bound to each inbound webhook event.
 */
final class EventHandlerRegistry
{
    /**
     * @var array<string, WebhookHandler>
     */
    private array $handlers = [];

    public function register(WebhookEvent $event, WebhookHandler $handler): void
    {
        $this->handlers[$event->value] = $handler;
    }

    public function has(WebhookEvent $event): bool
    {
        return isset($this->handlers[$event->value]);
    }

    public function get(WebhookEvent $event): WebhookHandler
    {
        return $this->handlers[$event->value] ?? throw HandlerNotFoundException::forEvent($event);
    }
}
