<?php

namespace Shop\Http\Controllers;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RawRequestInput;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Http\Requests\CreateProductRequest;
use Shop\Http\Requests\SearchProductRequest;
use Shop\Models\Product;
use Shop\Repositories\ProductRepository;

class ProductController extends Controller
{
    public function __construct(private readonly ProductRepository $products) {}

    public function show(int $id): Product
    {
        return $this->products->findOrFail($id);
    }

    #[Sinful(RawRequestInput::class)]
    public function search(Request $request): array
    {
        $term = $request->input('q');
        $category = $request->input('category');

        return Product::query()
            ->where('name', 'like', "%{$term}%")
            ->where('category', $category)
            ->get()
            ->all();
    }

    /**
     * The same search, read through named getters on a `FormRequest` subclass — the key strings and
     * their types live once, in the request, instead of at every call site.
     *
     * @return list<Product>
     */
    #[Fixed(RawRequestInput::class)]
    public function searchNamed(SearchProductRequest $request): array
    {
        return Product::query()
            ->where('name', 'like', "%{$request->term()}%")
            ->where('category', $request->category())
            ->get()
            ->all();
    }
}
