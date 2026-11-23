<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\PlaceholderFilledData;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * How a till's nightly sync with head office went: the batch it sent, or why it could not.
 */
final class TillSyncResult
{
    private function __construct(
        public readonly bool $sent,
        public readonly string $batch,
        public readonly string $reason,
    ) {}

    #[Sinful(PlaceholderFilledData::class)]
    public static function failed(string $reason): self
    {
        return new self(false, '', $reason);
    }

    #[Righteous(PlaceholderFilledData::class)]
    public static function skipped(): self
    {
        return new self(false, '', '');
    }
}
