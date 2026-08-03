<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\TernaryStatementScribe;
use JesseGall\CodeCommandments\Sins\Backend\TernaryStatement;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A ternary standing as a whole statement — `$this->holds($id) ? array_push(…) : $gone[] = $id;`.
 * A ternary's ONE job is to produce a value; when nothing reads it, the `?:` is not choosing a
 * value at all but an ACTION, and it is an `if`/`else` in disguise. The grammar then deforms the
 * code around it: both arms must be expressions, so an assignment gets jammed into an expression
 * slot and a call is picked for being callable rather than for being clear — and the value the
 * whole thing evaluates to (a push's new count, an assignment's value) is meaningless and dropped.
 * Sibling of {@see ShortCircuitStatementDetector}: same disguise, two arms instead of one — and it
 * spares the same assertion, `$name ?: throw Missing::of()`, whose one action is to leave.
 */
final class TernaryStatementDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new TernaryStatement();
    }

    public function scribe(): string
    {
        return TernaryStatementScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isTernary())
            ->where(static fn (AstNode $node): bool => $node->resultIsDiscarded())
            ->reject(static fn (AstNode $node): bool => $node->soleTernaryActionIsThrow())
            ->get();
    }
}
