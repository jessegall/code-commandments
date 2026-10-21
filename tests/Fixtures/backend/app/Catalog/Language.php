<?php

namespace Shop\Catalog;

/**
 * One language a product's text can be written in, by its two-letter code.
 */
final readonly class Language
{
    public function __construct(public string $code) {}

    public function isDutch(): bool
    {
        return $this->code === 'nl';
    }
}
