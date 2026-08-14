<?php

namespace Shop\Dispatch;

/**
 * A parcel's shipping paperwork — the subject three different callers flatten instead of handing over.
 */
final class Waybill
{
    public function __construct(private readonly string $code, private readonly int $grams) {}

    public function trackingCode(): string
    {
        return $this->code;
    }

    public function weightGrams(): int
    {
        return $this->grams;
    }

    public function isHeavy(): bool
    {
        return $this->grams > 20_000;
    }
}
