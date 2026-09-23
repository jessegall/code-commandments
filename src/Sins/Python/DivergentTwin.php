<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\FixAtTheSource;

final class DivergentTwin extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-divergent-twin',
            skill: FixAtTheSource::class,
            description: 'two functions do one job — the same rare outside calls, in different words — and one does strictly less of it, which is what a change looks like when it landed in only one of the two places that should have been one',
            rule: 'Funnel a shared behaviour through one path. Where two places do the same job, the step that must happen everywhere cannot be left to each of them to remember.',
            suggestion: 'Route the poorer path through the richer one, so the step cannot be forgotten again.',
        );
    }
}
