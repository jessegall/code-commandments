<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\MutableStaticState;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A write to static state — a global with a namespace in front of it. Nobody owns it, so whoever
 * writes last wins; nothing declares it, so a reader cannot tell which call put it there; and it
 * outlives every instance, so the order things ran in becomes part of the behaviour. The write is
 * reported rather than the declaration, because the write is where the coupling is actually made.
 * A `??=` memo is exempt ({@see AstNode::isStaticStateWrite}) — it adds nothing observable.
 */
final class MutableStaticStateDetector implements Detector
{
    public function sin(): Sin
    {
        return new MutableStaticState();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isStaticStateWrite())
            ->get();
    }
}
