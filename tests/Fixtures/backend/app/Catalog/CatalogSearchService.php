<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Junior catalog search — reads config in the body and pulls fields straight out
 * of a loose `$filters` array instead of a typed query object.
 */
final class CatalogSearchService
{
    public function __construct(private readonly CatalogSettings $settings) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, mixed>
     */
    #[Sinful(ConfigRead::class)]
    #[Sinful(ArrayBag::class)]
    #[Sinful(ArchaeologyComment::class)]
    public function search(array $filters): array
    {
        $perPage = config('shop.catalog.per_page');

        // formerly filtered in PHP; refactored into the query builder in v3
        $term = $filters['q'];
        $sort = $filters['sort'];

        return $this->run($term, $sort, $perPage);
    }

    /**
     * Injects the typed settings object instead of reading config in the body.
     *
     * @return array<int, mixed>
     */
    #[Righteous(ConfigRead::class)]
    public function searchTop(string $term, string $sort): array
    {
        return $this->run($term, $sort, $this->settings->perPage);
    }

    /**
     * A comment that describes the present code and its runtime, in present tense.
     *
     * @return array<int, mixed>
     */
    #[Righteous(ArchaeologyComment::class)]
    public function searchSorted(string $term): array
    {
        // the cached rank may no longer exist; the flag previously bound is used to scope the column
        $sort = $term === '' ? 'rank' : 'relevance';

        return $this->run($term, $sort, $this->settings->perPage);
    }

    /**
     * @return array<int, mixed>
     */
    private function run(string $term, string $sort, int $perPage): array
    {
        return [];
    }
}
