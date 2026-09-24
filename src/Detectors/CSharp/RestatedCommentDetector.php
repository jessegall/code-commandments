<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\RestatedComment;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A comment narrating the statement below it — every word of it already spelled by the code. The twin of the
 * backend's and Python's restated-comment rules.
 */
final class RestatedCommentDetector implements Detector
{
    /**
     * Below this, a comment is a label rather than a narration (`// total`) — too thin to call a restatement
     * even when the code spells the same word.
     */
    private const int MIN_WORDS = 2;

    public function sin(): Sin
    {
        return new RestatedComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->reject(static fn (NodeMatch $match): bool => $match->node->is('Block'))
            ->where(static fn (NodeMatch $match): bool => count($match->commentWords()) >= self::MIN_WORDS)
            ->where(static fn (NodeMatch $match): bool => array_all($match->commentWords(), static fn (string $word): bool => in_array($word, $match->codeWords(), true)))
            ->get();
    }
}
