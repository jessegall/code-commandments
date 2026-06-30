<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Exceptions;

final class WrappingWithoutCause extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'wrapping-without-cause',
            skill: Exceptions::class,
            description: "Wrapping a caught exception without passing it as `previous`/cause"
        );
    }
}
