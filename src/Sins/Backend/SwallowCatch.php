<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Exceptions;

final class SwallowCatch extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'swallow-catch',
            skill: Exceptions::class,
            description: "`catch` whose only effect is `return null/false/[]/none()`; empty catch (silent swallow)"
        );
    }
}
