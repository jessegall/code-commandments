<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\CommentKind;
use JesseGall\CodeCommandments\Cs\CommentMatch;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Sins\CSharp\BloatedDocblock;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A type's doc comment running to two or more paragraphs — the C# twin of the PHP and Python
 * bloated-docblock rules.
 */
final class BloatedDocblockDetector extends ProseRule
{
    public function sin(): Sin
    {
        return new BloatedDocblock();
    }

    protected function isSinful(CommentMatch $found): bool
    {
        return $found->comment->kind === CommentKind::Documentation
            && $found->documented()->isSomeAnd(static fn (Node $node): bool => $node->isTypeDeclaration())
            && $found->comment->paragraphs() >= 2;
    }
}
