<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class DeepNesting extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'deep-nesting',
            skill: GuardClausesAndFlow::class,
            description: "`if` nested 3-deep (a pyramid — hoist guards / extract)"
        );
    }
}
