<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;
use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects comments describing history instead of present code. Excludes ambiguous markers
 * with legitimate present-tense readings ("previously bound", "used to scope"). Points at
 * the documentation skill.
 */
final class ArchaeologyCommentDetector implements Detector
{
    public function sin(): Sin
    {
        return new ArchaeologyComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->whereComment(Prose::HISTORY)->get();
    }
}
