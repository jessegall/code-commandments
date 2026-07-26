<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Detectors\RunsLast;
use JesseGall\CodeCommandments\Scribes\Backend\StackedDocblockScribe;
use JesseGall\CodeCommandments\Sins\Backend\StackedDocblock;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects a declaration wearing several docblocks. PHP hands only the LAST one to `getDocComment()`,
 * so every block above it is documentation an IDE, a generator and a reader's tooling never show —
 * two descriptions of the same thing, one of them invisible. A normaliser, so it is {@see RunsLast}:
 * the content rules decide what the docs SAY before the stack is folded into one block. Points at the
 * documentation skill.
 */
final class StackedDocblockDetector implements Detector, Repentable, RunsLast
{
    public function sin(): Sin
    {
        return new StackedDocblock();
    }

    public function scribe(): string
    {
        return StackedDocblockScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereDocblock()
            ->where(fn (AstNode $n): bool => $n->hasStackedDocblocks())
            ->get();
    }
}
