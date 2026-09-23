<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Documentation;

final class NegativeSpaceComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-negative-space-comment',
            skill: Documentation::class,
            description: 'a comment or docstring defending the code against a reading nobody made — what it is not, rather than what it is',
            rule: 'State what the code IS; a comment defending it against a strawman means the code should make itself plain.',
            suggestion: 'Delete the defence. If the code needs it, make the code say what it is.',
        );
    }
}
