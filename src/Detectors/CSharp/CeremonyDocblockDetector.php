<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\CommentKind;
use JesseGall\CodeCommandments\Cs\CommentMatch;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Sins\CSharp\CeremonyDocblock;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A doc comment that only repeats the signature it sits on — the C# twin of the PHP and Python
 * ceremony-docblock rules.
 */
final class CeremonyDocblockDetector extends ProseRule
{
    public function sin(): Sin
    {
        return new CeremonyDocblock();
    }

    protected function isSinful(CommentMatch $found): bool
    {
        return $found->comment->kind === CommentKind::Documentation
            && $found->documented()->isSomeAnd(static fn (Node $node): bool => $found->comment->restatesOnly($node->signatureWords()));
    }
}
