<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
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
    private const string PATTERN = '/'
        // negation + a strawman noun ("not random", "no magic", "not a coincidence", "not vibes")
        . '\b(?:not|never|no|isn\'?t|aren\'?t|nothing)\b[^.]{0,24}\b(?:random|arbitrary|magic|magical|blanket|coincidence|coincidental|accident|accidental|by chance|typo|mistake|dead code|courtesy|vibes|afterthought|oversight)\b'
        // an intent adverb defending a negation/absence ("intentionally NOT…", "deliberately empty")
        . '|\b(?:intentionally|deliberately)\b[^.]{0,24}\b(?:not|never|no|empty|incomplete|omitted|unused)\b'
        // a negation excused as deliberate ("a TRAIT, not a base method, on purpose")
        . '|\b(?:not|never)\b[^.]{0,40}\bon purpose\b'
        // pointing at an ABSENCE — a named thing that is NOT present here / in this list (a defence of what
        // is missing, not a description of behaviour: "is not built here" is fine, "is NOT here" is not)
        . '|\b(?:is|are|\'?s|\'?re)\s+not\s+(?:in\s+this\b|here\b)'
        . '|\bnot\s+(?:stored|listed|included|present|defined|declared|kept|shown)\s+(?:here|in\s+this)\b'
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
