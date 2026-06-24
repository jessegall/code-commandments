<?php

namespace App\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogController
{
    public function search(Request $request)
    {
        $term = $request->input('term');
        if (is_array($term)) {
            $term = '';
        }
        $term = (string) ($term ?? '');

        $type = $request->input('type');
        if ($type != null && ! in_array($type, ['physical', 'digital', 'subscription'])) {
            return response()->json(['error' => 'bad type'], 422);
        }

        $max = $request->input('max_price_cents');
        $max = (int) ($max ?? 0);
        if ($max < 0) {
            return response()->json(['error' => 'bad price'], 422);
        }

        $criteria = compact('term', 'type', 'max');

        $service = new CatalogService();
        $results = $service->search([
            'term' => $term,
            'type' => $type,
            'max_price_cents' => $max,
        ]);

        if ($results == null) {
            Log::info('no catalog results', $criteria);

            return response()->json(['count' => 0, 'data' => []]);
        }

        $out = [];
        foreach ($results as $r) {
            $label = ProductType::label($r['type'] ?? 'physical');
            $r['type_label'] = $label;
            $out[] = $r;
        }

        return response()->json(['count' => count($out), 'data' => $out]);
    }
}
