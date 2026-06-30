<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\LaravelIdioms;

final class RequestAccessorRecast extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'request-accessor-recast',
            skill: LaravelIdioms::class,
            description: "Re-coercing a typed request accessor at a call site — `\$request->string('id')->toString()` instead of a named getter on a request class"
        );
    }
}
