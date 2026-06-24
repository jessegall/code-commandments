<?php

namespace App\Webhooks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and types an inbound webhook delivery.
 */
final class WebhookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string'],
            'resource_id' => ['required', 'string'],
            'occurred_at' => ['required', 'integer'],
            'signature' => ['required', 'string'],
        ];
    }

    public function event(): WebhookEvent
    {
        return $this->enum('event', WebhookEvent::class);
    }

    public function resourceId(): string
    {
        return (string) $this->string('resource_id');
    }

    public function occurredAt(): int
    {
        return $this->integer('occurred_at');
    }

    public function signature(): string
    {
        return (string) $this->string('signature');
    }

    public function payload(): WebhookPayload
    {
        return new WebhookPayload(
            event: $this->event(),
            resourceId: $this->resourceId(),
            occurredAt: $this->occurredAt(),
        );
    }
}
