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
}
