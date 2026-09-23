<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\RestatedComment;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A comment that says nothing its statement does not — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\RestatedCommentDetector}. The run of comment lines is
 * weighed as one block, so a paragraph enumerating the cases of a knotty condition earns its place.
 */
final class RestatedCommentDetector implements Detector
{
    /**
     * Below this, a comment is a label rather than a narration (`# flush`) — too thin to call a restatement
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
            ->where(static fn (NodeMatch $match): bool => $match->enclosingFunction()->isSome())
            ->reject(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef)
            ->where(static fn (NodeMatch $match): bool => count($match->commentWords()) >= self::MIN_WORDS)
            ->where(static fn (NodeMatch $match): bool => array_all($match->commentWords(), static fn (string $word): bool => in_array($word, $match->codeWords(), true)))
            ->get();
    }
}
