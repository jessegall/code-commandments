<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\LaravelIdioms;

final class RawRequestInput extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'raw-request-input',
            skill: LaravelIdioms::class,
            description: "Raw `->input()/->get()/->query()` on a Request"
        );
    }
}
