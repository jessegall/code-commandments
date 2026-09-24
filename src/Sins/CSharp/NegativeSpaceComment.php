<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Documentation;

final class NegativeSpaceComment extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-negative-space-comment',
            skill: Documentation::class,
            description: 'a comment defending the code against a reading nobody made — `// not magic, just a day`, `// deliberately not sorted` — saying what it is not instead of what it is',
            rule: 'Say what the code is; a comment answering an objection nobody raised means the code should make itself plain.',
            suggestion: 'Name the thing so it explains itself (a named constant, a well-named method) and delete the comment, or say what it is instead.',
        );
    }
}
