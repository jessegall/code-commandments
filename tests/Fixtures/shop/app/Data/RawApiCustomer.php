<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Detectors\Backend\AllNullableDataDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A customer pulled from a third-party API, modelled with every field optional so
 * the DTO promises nothing — callers must re-check `id`/`email` that should be
 * guaranteed.
 */
#[Sinful(AllNullableDataDetector::class)]
final class RawApiCustomer extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $fullName = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
    ) {}

    public function greeting(): string
    {
        return 'Dear ' . ($this->fullName ?? 'customer');
    }

    public function reachable(): bool
    {
        return $this->email !== null || $this->phone !== null;
    }
}
