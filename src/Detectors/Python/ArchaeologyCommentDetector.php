<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Docstring;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * A comment or docstring about the code's past — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ArchaeologyCommentDetector}, reading the same phrases.
 */
final class ArchaeologyCommentDetector implements Detector
{
    public function sin(): Sin
    {
        return new ArchaeologyComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => array_any($match->prose(), static fn (string $text) => Prose::narratesHistory(Docstring::withoutVersionNotes($text))))
            ->get();
    }
}
