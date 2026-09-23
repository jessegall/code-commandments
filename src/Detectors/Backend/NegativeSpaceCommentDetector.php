<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;
use JesseGall\CodeCommandments\Sins\Backend\NegativeSpaceComment;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects comments that defend code against strawmen — a negation paired with a word like
 * "random", "magic", or "coincidence", where the code should simply state what it IS.
 * Points at documentation.
 */
final class NegativeSpaceCommentDetector implements Detector
{
    public function sin(): Sin
    {
        return new NegativeSpaceComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->whereComment(Prose::strawman())->get();
    }
}
