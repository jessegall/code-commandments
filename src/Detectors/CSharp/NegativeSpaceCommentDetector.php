<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\CommentMatch;
use JesseGall\CodeCommandments\Sins\CSharp\NegativeSpaceComment;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * A comment defending the code against a strawman — the C# twin of the PHP and Python negative-space-comment
 * rules.
 */
final class NegativeSpaceCommentDetector extends ProseRule
{
    public function sin(): Sin
    {
        return new NegativeSpaceComment();
    }

    protected function isSinful(CommentMatch $found): bool
    {
        return Prose::defendsAgainstStrawman($found->comment->prose());
    }
}
