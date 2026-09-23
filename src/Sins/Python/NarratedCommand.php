<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\MethodMood;

final class NarratedCommand extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-narrated-command',
            skill: MethodMood::class,
            description: 'a command named in the third person — `hides()`, `locks_for_night()` — where a call is an order, not a description of one',
            rule: 'Name a command in the imperative: `hide()`, `lock_for_night()`, `open_for(user)` — never the third-person `hides()`.',
            suggestion: 'Drop the -s: the call site is giving the order, not narrating it.',
        );
    }
}
