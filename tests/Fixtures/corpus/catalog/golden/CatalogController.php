<?php

namespace App\Catalog;

use Illuminate\Http\RedirectResponse;

/**
 * Accepts a catalog search and redirects to its results listing.
 */
final class CatalogController
{
    public function __construct(
        private readonly CatalogService $catalog,
    ) {}

    public function search(ProductSearchRequest $request): RedirectResponse
    {
        $matches = $this->catalog->search($request->toSearch());

        return redirect()->route('catalog.index', ['count' => $matches->count()]);
    }
}
