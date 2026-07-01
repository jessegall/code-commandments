<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ArchaeologyComment;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A comment that narrates the code's past — `// previously...`, `// changed
 * from...`, `// now it returns...`. Git holds the history; the comment should
 * describe the present code. Points at the documentation skill.
 */
final class ArchaeologyCommentDetector implements Detector
{
    private const string PATTERN = '/\b(previously|used to|formerly|originally|refactored|renamed|moved (from|to)|changed (from|to)|no longer|now (it|we|returns)|was extracted)\b/i';

    public function sin(): Sin
    {
        return new ArchaeologyComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->whereComment(self::PATTERN)->get();
    }
}
