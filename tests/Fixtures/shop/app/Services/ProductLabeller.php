<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Detectors\Backend\RequestAccessorRecastDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Http\Requests\CreateProductRequest;

final class ProductLabeller
{
    #[Sinful(RequestAccessorRecastDetector::class)]
    public function label(CreateProductRequest $request): string
    {
        return sprintf('PRODUCT: %s', $request->string('name')->toString());
    }
}
