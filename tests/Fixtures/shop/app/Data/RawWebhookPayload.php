<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Detectors\Backend\AllNullableDataDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * Raw view of an incoming webhook — every field nullable for tolerant decoding.
 */
#[Sinful(AllNullableDataDetector::class)]
final class RawWebhookPayload extends Data
{
    public function __construct(
        public readonly string | null $type = null,
        public readonly string | null $orderId = null,
        public readonly int | null $amountCents = null,
        public readonly string | null $currency = null,
        public readonly array | null $metadata = null,
    ) {}

    public function isPaymentEvent(): bool
    {
        return $this->type === 'payment.captured' || $this->type === 'payment.refunded';
    }

    public function describe(): string
    {
        return sprintf('%s for order %s', $this->type ?? 'unknown', $this->orderId ?? 'n/a');
    }

    public function hasMetadataKey(string $key): bool
    {
        return is_array($this->metadata) && array_key_exists($key, $this->metadata);
    }
}
