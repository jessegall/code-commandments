<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Laravel\LaravelIdioms;

final class RequestAccessorRecast extends Sin implements RequiresPackage
{
    use RequiresLaravel;

    public function __construct()
    {
        parent::__construct(
            name: 'request-accessor-recast',
            skill: LaravelIdioms::class,
            description: "Re-coercing a typed request accessor at a call site — `\$request->string('id')->toString()` instead of a named getter on a request class",
            rule: "Expose a named getter on a typed request class; don't re-coerce a typed accessor (`\$request->string('id')->toString()`) at a call site.",
            suggestion: "A named getter on a typed request class returning the coerced value."
        );
    }
}
