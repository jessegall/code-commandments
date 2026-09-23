<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Enums;

final class MatchWildcardReturnsNone extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-match-wildcard-returns-none',
            skill: Enums::class,
            description: 'a `match` over an enum\'s members whose `case _:` returns `None` — a member nobody handled answers nothing instead of failing',
            rule: 'End a `match` over an enum\'s members with a `case _:` that raises, or handle every member; never let the wildcard return `None`.',
            suggestion: '`case _: raise UnhandledStatus.of(status)` — or `assert_never(status)` so the type checker names the member left out.',
        );
    }
}
