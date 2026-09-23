<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Documentation;

final class ArchaeologyComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-archaeology-comment',
            skill: Documentation::class,
            description: 'a comment or docstring narrating the code\'s history — where it lived, what it replaced, what it no longer is',
            rule: 'Say what the code is now; the history lives in git, not in a comment or a docstring.',
            suggestion: 'Delete the history. If a reason still matters, state it in the present tense.',
        );
    }
}
