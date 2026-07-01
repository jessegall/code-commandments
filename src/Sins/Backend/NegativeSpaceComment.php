<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class NegativeSpaceComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'negative-space-comment',
            skill: Documentation::class,
            description: 'A comment defending the code against a strawman ("not random", "no magic", "not a coincidence", "not dead code")',
            rule: 'State what the code IS, affirmatively — a comment that defends it against a strawman (that it is "not random", "no magic", "not a typo") is negative space; make the code self-evident and delete the comment.',
        );
    }
}
