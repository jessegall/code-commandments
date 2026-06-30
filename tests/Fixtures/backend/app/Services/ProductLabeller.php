<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Sins\Backend\RequestAccessorRecast;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Http\Requests\CreateProductRequest;

final class ProductLabeller
{
    #[Sinful(RequestAccessorRecast::class)]
    public function label(CreateProductRequest $request): string
    {
        return sprintf('PRODUCT: %s', $request->string('name')->toString());
    }
}
