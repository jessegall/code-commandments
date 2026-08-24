<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;
use JesseGall\CodeCommandments\Unpublished;

/**
 * Two paths that do one job, where one applies a step the other lacks — a change that landed in only one.
 */
final class DivergentTwin extends Sin implements Unpublished
{
    public function __construct()
    {
        parent::__construct(
            name: 'divergent-twin',
            skill: FixAtTheSource::class,
            description: "Two code paths do the same job and one does strictly less — skipping a check its twin makes, which is what a change looks like when it landed in only one of two places, or lacking work its twin does, which is one mechanism written twice",
            rule: "Funnel a shared behaviour through ONE path. Where two places do the same job, the one that must happen everywhere cannot be left to each of them to remember.",
            suggestion: "Route the poorer path through the richer one, so the step cannot be forgotten again.",
        );
    }
}
