<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Reads the brand column of an import sheet: a name the person typed, which may match no brand.
 */
final class BrandColumn
{
    /**
     * @param  array<string, string>  $brandIds
     */
    public function __construct(private readonly array $brandIds) {}

    public function read(string $typed): string
    {
        return $this->labelled($this->idOf($typed), $typed);
    }

    #[Righteous(NullableRegistryLookup::class)]
    private function idOf(string $typed): ?string
    {
        return $this->brandIds[$typed] ?? null;
    }

    public function isKnown(string $typed): bool
    {
        $id = $this->knownId($typed);

        return ! empty($id);
    }

    #[Righteous(NullableRegistryLookup::class)]
    private function knownId(string $typed): ?string
    {
        return $this->brandIds[strtolower($typed)] ?? null;
    }

    private function labelled(?string $id, string $typed): string
    {
        return $id === null ? "unknown brand {$typed}" : "brand {$id}";
    }
}
