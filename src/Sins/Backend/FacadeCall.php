<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\LaravelIdioms;

final class FacadeCall extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'facade-call',
            skill: LaravelIdioms::class,
            description: "Laravel facade call (`Cache::`, `Log::`, `Mail::` …)"
        );
    }
}
