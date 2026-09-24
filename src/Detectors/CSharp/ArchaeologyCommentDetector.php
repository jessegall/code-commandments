<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Comment;
use JesseGall\CodeCommandments\Sins\CSharp\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * A comment narrating the code's history — the C# twin of the PHP and Python archaeology-comment rules, read by
 * the one shared reading of prose, {@see Prose::narratesHistory}.
 */
final class ArchaeologyCommentDetector extends ProseRule
{
    public function sin(): Sin
    {
        return new ArchaeologyComment();
    }

    protected function isSinful(Comment $comment): bool
    {
        return Prose::narratesHistory($comment->prose());
    }
}
