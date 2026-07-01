<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NegativeSpaceComment;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A comment that defends the code against a strawman — `// not random`, `// no
 * magic here`, `// not a coincidence`, `// this isn't dead code`. It argues what
 * the code is NOT instead of stating what it IS: negative space that pre-empts a
 * misreading the code should make impossible on its own. Points at documentation.
 */
final class NegativeSpaceCommentDetector implements Detector
{
    private const string PATTERN = '/'
        // negation + a strawman noun ("not random", "no magic", "not a coincidence", "not vibes")
        . '\b(?:not|never|no|isn\'?t|aren\'?t|nothing)\b[^.]{0,24}\b(?:random|arbitrary|magic|magical|blanket|coincidence|coincidental|accident|accidental|by chance|typo|mistake|dead code|courtesy|vibes|afterthought|oversight)\b'
        // an intent adverb defending a negation/absence ("intentionally NOT…", "deliberately empty")
        . '|\b(?:intentionally|deliberately)\b[^.]{0,24}\b(?:not|never|no|empty|incomplete|omitted|unused)\b'
        // a negation excused as deliberate ("a TRAIT, not a base method, on purpose")
        . '|\b(?:not|never)\b[^.]{0,40}\bon purpose\b'
        . '/i';

    public function sin(): Sin
    {
        return new NegativeSpaceComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->whereComment(self::PATTERN)->get();
    }
}
