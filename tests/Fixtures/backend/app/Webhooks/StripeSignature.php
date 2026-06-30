<?php

namespace Shop\Webhooks;

final class StripeSignature
{
    public function __construct(
        public readonly string $signature,
        public readonly int $timestamp,
    ) {}

    public static function fromHeader(string $header): self
    {
        [$timestamp, $signature] = explode(',', $header, 2);

        return new self($signature, (int) $timestamp);
    }
}
