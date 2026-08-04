<?php

namespace Shop\Http\Controllers;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RequestAccessorRecast;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Http\Requests\SearchProductRequest;
use Shop\Models\Product;

class TermController extends Controller
{
    /**
     * @return list<Product>
     */
    #[Sinful(RequestAccessorRecast::class)]
    public function search(Request $request): array
    {
        $term = $request->string('q')->toString();

        return Product::query()
            ->where('name', 'like', "%{$term}%")
            ->get()
            ->all();
    }

    /**
     * The coercion moved onto the typed request as `term()`, so the call site asks a named getter for
     * an already-`string` value instead of recasting an accessor.
     *
     * @return list<Product>
     */
    #[Fixed(RequestAccessorRecast::class)]
    public function searchNamed(SearchProductRequest $request): array
    {
        return Product::query()
            ->where('name', 'like', "%{$request->term()}%")
            ->orderBy('name')
            ->get()
            ->all();
    }
}
