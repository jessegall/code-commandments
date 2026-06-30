<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Exceptions;

final class GenericException extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'generic-exception',
            skill: Exceptions::class,
            description: "`throw new <bare SPL>` (RuntimeException/LogicException/…) instead of a named type"
        );
    }
}
