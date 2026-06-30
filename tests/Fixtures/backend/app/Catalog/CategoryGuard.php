<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\InArrayMirrorsEnumDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Validates incoming product payloads. The category check spells out the
 * ProductCategory enum's values as a literal whitelist.
 */
final class CategoryGuard
{
    public function __construct(
        private readonly bool $strict = true,
        private readonly string $fallback = 'apparel',
    ) {}

    public function isValid(string $name, string $category, int $price): bool
    {
        if ($name === '') {
            return false;
        }

        if (! $this->permits($category !== '' ? $category : $this->fallback)) {
            return false;
        }

        if ($this->strict && $price <= 0) {
            return false;
        }

        return true;
    }

    #[Sinful(InArrayMirrorsEnumDetector::class)]
    private function permits(string $category): bool
    {
        return in_array($category, ['apparel', 'electronics', 'food'], true);
    }
}
