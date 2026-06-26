<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A comment that narrates the code's past — `// previously...`, `// changed
 * from...`, `// now it returns...`. Git holds the history; the comment should
 * describe the present code. Points at the documentation skill.
 */
final class ArchaeologyCommentDetector implements Detector
{
    private const string PATTERN = '/\b(previously|used to|formerly|originally|refactored|renamed|moved (from|to)|changed (from|to)|no longer|now (it|we|returns)|was extracted)\b/i';

    public function skill(): string
    {
        return 'documentation';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->whereComment(self::PATTERN)->get();
    }
}
