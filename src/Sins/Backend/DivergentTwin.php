<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;

/**
 * Two paths that do one job, where one applies a step the other lacks — a change that landed in only one.
 */
final class DivergentTwin extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'divergent-twin',
            skill: FixAtTheSource::class,
            description: "Two methods do one job — the same rare verbs, in different words — and one of them does strictly less of it, which is what a change looks like when it landed in only one of the two places that should have been one",
            rule: "Funnel a shared behaviour through ONE path. Where two places do the same job, the one that must happen everywhere cannot be left to each of them to remember.",
            suggestion: "Route the poorer path through the richer one, so the step cannot be forgotten again.",
        );
    }
}
