<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\AssembledTemplate;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A template assembled from line fragments — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\AssembledTemplateDetector}.
 */
final class AssembledTemplateDetector implements Detector
{
    /**
     * Fewer lines than this is a pair, not a template — its shape is already visible.
     */
    private const int MIN_LINES = 3;

    /**
     * At least this many of the lines must be FIXED text. A join whose parts are all computed is a list
     * being presented, and no template could state it.
     */
    private const int MIN_FIXED_LINES = 2;

    public function sin(): Sin
    {
        return new AssembledTemplate();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereCall()
            ->where(static fn (ExprMatch $match): bool => $match->expr->isNewlineJoin())
            ->where(static fn (ExprMatch $match): bool => count($match->expr->joinedLines()) >= self::MIN_LINES)
            ->where(static fn (ExprMatch $match): bool => count(array_filter($match->expr->joinedLines(), static fn (Expr $line): bool => $line->isFixedText())) >= self::MIN_FIXED_LINES)
            ->get();
    }
}
