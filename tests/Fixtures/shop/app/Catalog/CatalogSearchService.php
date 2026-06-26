<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Detectors\Backend\ArchaeologyCommentDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ConfigReadDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Junior catalog search — reads config in the body and pulls fields straight out
 * of a loose `$filters` array instead of a typed query object.
 */
final class CatalogSearchService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, mixed>
     */
    #[Sinful(ConfigReadDetector::class)]
    #[Sinful(ArrayBagDetector::class)]
    #[Sinful(ArchaeologyCommentDetector::class)]
    public function search(array $filters): array
    {
        $perPage = config('shop.catalog.per_page');

        // used to filter in PHP, moved to the query builder in v3
        $term = $filters['q'];
        $sort = $filters['sort'];

        return $this->run($term, $sort, $perPage);
    }

    /**
     * @return array<int, mixed>
     */
    private function run(string $term, string $sort, int $perPage): array
    {
        return [];
    }
}
