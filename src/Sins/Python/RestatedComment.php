<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Documentation;

final class RestatedComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-restated-comment',
            skill: Documentation::class,
            description: 'a `#` comment that only narrates the statement below it — every word of it already spelled by the code',
            rule: 'A comment must say something the code does not; one whose every word is in the line below is noise.',
            suggestion: 'Delete the comment, or replace it with the reason the code cannot state.',
        );
    }
}
