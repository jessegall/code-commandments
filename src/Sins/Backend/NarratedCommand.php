<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\MethodMood;

final class NarratedCommand extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'narrated-command',
            skill: MethodMood::class,
            description: 'A command named in the third person — `hides()`, `entersTestMode()` — where a call is an order, not a description of one',
            rule: 'Name a command in the imperative: `hide()`, `enterTestMode()`, `openFor(\$user)` — never the third-person `hides()`, and never a participle.',
            suggestion: "drop the -s: the call site is giving the order, not narrating it",
        );
    }
}
