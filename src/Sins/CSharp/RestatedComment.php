<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Documentation;

final class RestatedComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-restated-comment',
            skill: Documentation::class,
            description: 'a comment above a statement whose every word the statement already spells — `// set the total to the order total` over `var total = order.Total;`',
            rule: 'A comment must say something the code does not; if every word of it is already in the code below, delete it.',
            suggestion: 'Delete the comment. If the statement is unclear, name it better (extract a well-named method or variable); keep a comment only for a reason the code cannot state.',
        );
    }
}
