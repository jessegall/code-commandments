<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\MemberOutOfOrderScribe;
use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects a head of class assembled in an ad-hoc order — a constant under a property, a public field
 * under a private one, a derived hook above the fields it reads. One fixed sequence costs nothing to
 * follow and makes every class scan the same way. Reported on the member that arrives too late, which
 * is the one to move. A member below a method is the other layout sin's business, never both.
 * Points at the class-layout skill.
 */
final class MemberOutOfOrderDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new MemberOutOfOrder();
    }

    public function scribe(): string
    {
        return MemberOutOfOrderScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassMember()
            ->reject(fn (AstNode $n): bool => $n->followsAMethodInItsClass())
            ->where(fn (AstNode $n): bool => $n->breaksClassLayoutOrder())
            ->get();
    }
}
