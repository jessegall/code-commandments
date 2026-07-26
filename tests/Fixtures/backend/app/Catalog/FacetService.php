<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Builds the search facets as a loose string-keyed bag instead of a Facets value
 * object — three named fields travelling as an array.
 */
final class FacetService
{
    /**
     * @return array<string, mixed>
     */
    #[Sinful(ArrayReturnBag::class)]
    public function facets(): array
    {
        return [
            'categories' => $this->byCategory(),
            'price_buckets' => $this->byPrice(),
            'ratings' => $this->byRating(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function byCategory(): array
    {
        return [];
    }

    /**
     * @return array<int, int>
     */
    private function byPrice(): array
    {
        return [];
    }

    /**
     * @return array<int, int>
     */
    private function byRating(): array
    {
        return [];
    }

    /**
     * A SHAPED-array return (`@return array{…}`) — a sealed, statically-checkable struct whose
     * fields are named and typed. That is a typed record contract, not a loose bag, so it is NOT
     * this sin (no marker): the shape annotation already gives what a value object would.
     *
     * @return array{categories: array<int, string>, price_buckets: array<int, int>, ratings: array<int, int>}
     */
    #[Righteous(ArrayReturnBag::class)]
    public function shapedFacets(): array
    {
        return [
            'categories' => $this->byCategory(),
            'price_buckets' => $this->byPrice(),
            'ratings' => $this->byRating(),
        ];
    }

    /**
     * A JSON-Schema contract shape serialized to a search provider — `type` + the schema
     * vocabulary, properties keyed by arbitrary user facet names. Not a fixed-field bag,
     * so NOT this sin (no marker): it can't sensibly become a typed value object.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function facetSchema(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => ['categories'],
            'additionalProperties' => false,
        ];
    }
}
