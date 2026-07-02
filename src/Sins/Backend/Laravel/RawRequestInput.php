<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class RawRequestInput extends Sin implements RequiresComposerPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'raw-request-input',
            skill: LaravelIdioms::class,
            description: "Raw `->input()/->get()/->query()/->post()` on a Request",
            rule: "Read request input through a typed accessor (`\$request->string('x')`); never raw `->input()`/`->get()`/`->query()`.",
            suggestion: "A named getter on a `FormRequest` subclass (`\$request->productId()`)."
        );
    }
}
