<?php

namespace Shop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JesseGall\CodeCommandments\Detectors\Backend\RequestAccessorRecastDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

class TermController extends Controller
{
    /**
     * @return list<Product>
     */
    #[Sinful(RequestAccessorRecastDetector::class)]
    public function search(Request $request): array
    {
        $term = $request->string('q')->toString();

        return Product::query()
            ->where('name', 'like', "%{$term}%")
            ->get()
            ->all();
    }
}
