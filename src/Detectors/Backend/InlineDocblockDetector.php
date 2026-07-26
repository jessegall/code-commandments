<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Detectors\RunsLast;
use JesseGall\CodeCommandments\Scribes\Backend\InlineDocblockScribe;
use JesseGall\CodeCommandments\Sins\Backend\InlineDocblock;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects a docblock crammed onto its delimiters — the whole thing on one line, or a block that opens
 * or closes beside its text. A docblock is a BLOCK: it reads as one at a glance, and every declaration
 * looks the same from three lines away. Pure shape, so it is {@see RunsLast} — the content rules
 * (bloated, ceremony, archaeology, restated) settle what a docblock SAYS first, and this reshapes only
 * what survived them. Points at the documentation skill.
 */
final class InlineDocblockDetector implements Detector, Repentable, RunsLast
{
    public function sin(): Sin
    {
        return new InlineDocblock();
    }

    public function scribe(): string
    {
        return InlineDocblockScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereDocblock()
            ->where(fn (AstNode $n): bool => $n->hasInlineDocblock())
            ->get();
    }
}
