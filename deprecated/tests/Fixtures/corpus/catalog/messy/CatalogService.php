<?php

namespace App\Catalog;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatalogService
{
    public $store;

    public function __construct()
    {
        $this->store = new CategoryStore();
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<int, mixed>|null
     */
    public function search(array $criteria)
    {
        Log::info('searching catalog', $criteria);

        $rows = Cache::get('catalog.products');

        if ($rows == null) {
            $rows = DB::table('products')->get()->toArray();
            Cache::put('catalog.products', $rows, 60);
        }

        $results = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            $term = $criteria['term'] ?? '';
            if ($term != '' && strpos(strtolower($row['name'] ?? ''), strtolower($term)) === false) {
                continue;
            }

            $type = $criteria['type'] ?? null;
            if ($type != null && ($row['type'] ?? '') != $type) {
                continue;
            }

            $max = (int) ($criteria['max_price_cents'] ?? 0);
            if ($max > 0 && (int) ($row['amount_cents'] ?? 0) > $max) {
                continue;
            }

            $results[] = $row;
        }

        if (count($results) == 0) {
            return null;
        }

        return $results;
    }

    /**
     * @return array<int, mixed>|null
     */
    public function inCategory($categoryKey)
    {
        $category = $this->store->get($categoryKey);

        if ($category == null) {
            return null;
        }

        $service = app(ProductRepository::class);
        $all = $service->all();

        $out = [];
        foreach ($all as $p) {
            if (($p['category_key'] ?? null) == $categoryKey) {
                $out[] = $p;
            }
        }

        return $out;
    }
}
