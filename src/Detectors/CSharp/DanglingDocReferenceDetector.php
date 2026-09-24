<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\CommentMatch;
use JesseGall\CodeCommandments\Cs\Cref;
use JesseGall\CodeCommandments\Sins\CSharp\DanglingDocReference;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A doc comment naming, in a `cref`, a type or member the project does not declare — the C# twin of the PHP
 * and Python dangling-doc-reference rules.
 */
final class DanglingDocReferenceDetector extends ProseRule
{
    public function sin(): Sin
    {
        return new DanglingDocReference();
    }

    protected function isSinful(CommentMatch $found): bool
    {
        return array_any($found->comment->crefs, static fn (Cref $cref): bool => $cref->isDangling());
    }
}
