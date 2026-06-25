<?php

namespace Shop\Data;

use Spatie\LaravelData\Data;

/**
 * Raw view of an incoming webhook — every field nullable for tolerant decoding.
 */
final class RawWebhookPayload extends Data
{
    public function __construct(
        public readonly string | null $type = null,
        public readonly string | null $orderId = null,
        public readonly int | null $amountCents = null,
        public readonly string | null $currency = null,
        public readonly array | null $metadata = null,
    ) {}
}
