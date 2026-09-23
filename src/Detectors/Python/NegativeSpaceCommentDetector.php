<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Sins\Python\NegativeSpaceComment;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * A comment or docstring defending the code against a strawman — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\NegativeSpaceCommentDetector}, reading the same phrases.
 */
final class NegativeSpaceCommentDetector extends ProseRule
{
    public function sin(): Sin
    {
        return new NegativeSpaceComment();
    }

    protected function isSinful(string $text): bool
    {
        return Prose::defendsAgainstStrawman($text);
    }
}
