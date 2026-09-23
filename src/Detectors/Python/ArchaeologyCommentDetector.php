<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Sins\Python\ArchaeologyComment;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * A comment or docstring about the code's past — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ArchaeologyCommentDetector}, reading the same phrases.
 */
final class ArchaeologyCommentDetector extends ProseRule
{
    public function sin(): Sin
    {
        return new ArchaeologyComment();
    }

    protected function isSinful(string $text): bool
    {
        return Prose::narratesHistory($text);
    }
}
