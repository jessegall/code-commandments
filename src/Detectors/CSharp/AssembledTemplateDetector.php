<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\AssembledTemplate;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Three or more lines written right there, two of them fixed text, joined with a newline — or written one
 * `AppendLine` at a time on one builder, C#'s own way of taking a template apart: the C# twin of the PHP and
 * Python assembled-template rules.
 */
final class AssembledTemplateDetector implements Detector
{
    /**
     * How many lines a join must hold before it is a template rather than a pair.
     */
    private const int MIN_LINES = 3;

    /**
     * How many of them must be written into the source for there to be a fixed shape to show.
     */
    private const int MIN_FIXED_LINES = 2;

    public function sin(): Sin
    {
        return new AssembledTemplate();
    }

    public function find(Codebase $codebase): array
    {
        $joins = $codebase
            ->whereCall()
            ->where(static fn (NodeMatch $match): bool => count($match->node->joinedLines()) >= self::MIN_LINES)
            ->where(static fn (NodeMatch $match): bool => count(array_filter($match->node->joinedLines(), static fn (Node $line): bool => $line->isFixedText())) >= self::MIN_FIXED_LINES)
            ->get();

        $runs = $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->startsAppendLineRun(self::MIN_LINES, self::MIN_FIXED_LINES))
            ->get();

        return [...$joins, ...$runs];
    }
}
