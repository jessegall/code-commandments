<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NonFinalData;

use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * A customer profile DTO that forgot to seal itself.
 */
#[Sinful(NonFinalData::class)]
class OpenProfileData extends Data
{
    public function __construct(
        public readonly string $displayName,
        public readonly string $locale,
        public readonly bool $marketingOptIn,
    ) {}

    public function greeting(): string
    {
        return "Hi {$this->displayName}";
    }

    public function speaksDutch(): bool
    {
        return str_starts_with($this->locale, 'nl');
    }

    public function initials(): string
    {
        return strtoupper(substr($this->displayName, 0, 1));
    }

    public function canEmail(): bool
    {
        return $this->marketingOptIn;
    }
}
