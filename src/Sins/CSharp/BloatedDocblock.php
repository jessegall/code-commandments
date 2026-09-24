<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Documentation;

final class BloatedDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-bloated-docblock',
            skill: Documentation::class,
            description: 'a type whose doc comment runs to two or more paragraphs — usually a sign the type does too much',
            rule: 'Keep a type\'s doc comment to one short paragraph; if it needs more, the type is doing too much.',
            suggestion: 'Cut the comment to one sentence about what the type is, and split the type if the rest describes a second job.',
        );
    }
}
