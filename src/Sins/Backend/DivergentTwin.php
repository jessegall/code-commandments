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
            description: 'Two functions do the same job, but one of them skips a step the other takes — usually a fix made in one copy and forgotten in the other.',
            rule: 'Put shared behaviour in one place, so a step that must always happen can\'t be forgotten in a copy.',
            suggestion: 'Make the shorter function call the longer one, or have both call one shared function.',
        );
    }
}
