<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;

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

    /** @return array<int, string> */
    private function byCategory(): array
    {
        return [];
    }

    /** @return array<int, int> */
    private function byPrice(): array
    {
        return [];
    }

    /** @return array<int, int> */
    private function byRating(): array
    {
        return [];
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
