<?php

namespace Shop\Loyalty;

use JesseGall\CodeCommandments\Sins\Backend\MutableValueObject;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A postal address that relocates itself. Whatever recorded "the address this parcel was quoted
 * for" now holds a different address, and nothing near the quote did that.
 */
#[Sinful(MutableValueObject::class)]
final class PostalAddress
{
    public function __construct(
        private string $line,
        private string $postcode,
        private string $city,
        private string $country,
    ) {}

    public function relocate(string $line, string $postcode, string $city): void
    {
        $this->line = $line;
        $this->postcode = $postcode;
        $this->city = $city;
    }

    public function oneLine(): string
    {
        return "{$this->line}, {$this->postcode} {$this->city}, {$this->country}";
    }

    public function isDomestic(): bool
    {
        return $this->country === 'NL';
    }

    public function label(): array
    {
        return [$this->line, "{$this->postcode} {$this->city}", strtoupper($this->country)];
    }
}
