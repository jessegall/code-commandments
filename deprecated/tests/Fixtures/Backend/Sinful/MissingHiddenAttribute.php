<?php

namespace App\Http\View\Products;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\FromSession;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProductsIndexPage extends Data
{
    public function __construct(
        public readonly array $products,
        // Sin: Missing #[Hidden] on property with #[FromContainer]
        #[FromContainer(\App\Repositories\ProductRepository::class)]
        public readonly \App\Repositories\ProductRepository $repository,
        // Sin: Missing #[Hidden] on property with #[FromSession]
        #[FromSession('user_preferences')]
        public readonly array $preferences,
    ) {}
}
