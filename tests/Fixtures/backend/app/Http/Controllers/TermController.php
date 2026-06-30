<?php

namespace Shop\Http\Controllers;

use JesseGall\CodeCommandments\Sins\Backend\RequestAccessorRecast;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JesseGall\CodeCommandments\Testing\Sinful;
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
}
