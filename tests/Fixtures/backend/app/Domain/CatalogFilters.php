<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\ConditionalArraySpread;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * Builds a query-parameter bag by merging a conditional fragment — `array_merge($base, $cond ? [...] : [])`.
 * The `array_merge` + ternary-into-empty is the same "include when set" smell.
 */
final class CatalogFilters
{
    /**
     * @param  array<string, string>  $base
     */
    public function __construct(
        private readonly array $base = [],
        private readonly ?string $brand = null,
    ) {}

    /*
     * Righteous twin — a plain spread with a static key, no ternary-into-empty. Must NOT flag.
     */
    public function toQuery(): array
    {
        return [...$this->base, 'in_stock' => 'true'];
    }

    /** @return array{sort: string, brand?: string} */
    #[Sinful(ConditionalArraySpread::class)]
    public function toSortedQuery(string $sort): array
    {
        return array_merge(['sort' => $sort], $this->brand === null ? [] : ['brand' => $this->brand]);
    }
}
