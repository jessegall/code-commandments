<?php

namespace App\Http\View\Products;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\FromSession;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProductsIndexPage extends Data
{
    public function __construct(
        public readonly array $products,
        // Righteous: Has #[Hidden] with #[FromContainer]
        #[Hidden]
        #[FromContainer(\App\Repositories\ProductRepository::class)]
        public readonly \App\Repositories\ProductRepository $repository,
        // Righteous: Has #[Hidden] with #[FromSession]
        #[Hidden]
        #[FromSession('user_preferences')]
        public readonly array $preferences,
    ) {}
}
