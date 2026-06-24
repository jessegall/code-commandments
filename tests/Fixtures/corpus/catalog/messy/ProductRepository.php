<?php

namespace App\Catalog;

use Illuminate\Support\Facades\DB;

class ProductRepository
{
    /**
     * @return array<int, mixed>|null
     */
    public function all()
    {
        $rows = DB::table('products')->get();

        if ($rows == null) {
            return null;
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = (array) $r;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find($sku)
    {
        $row = DB::table('products')->where('sku', $sku)->first();

        return $row ? (array) $row : null;
    }
}
