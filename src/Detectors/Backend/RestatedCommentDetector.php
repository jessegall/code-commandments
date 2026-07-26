<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\RestatedComment;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\Prose;

/**
 * Detects an inline comment that only narrates the statement it sits on: reduce the comment to its
 * content words and the annotated statement's head to the words it spells ({@see AstNode::codeWords()}),
 * and flag when EVERY comment word is already one of them — a comment carrying a why brings a word the
 * code lacks, so it survives. Only prose over a STATEMENT counts; a comment over a declaration or a run
 * of array items is a section heading, which the skill blesses, and commented-out code belongs to
 * another rule. Points at the documentation skill.
 */
final class RestatedCommentDetector implements Detector
{
    /**
     * Below this, a comment is a label rather than a narration ("// flush") — too thin to call a
     * restatement even when the code spells the same word.
     */
    private const int MIN_WORDS = 2;

    public function sin(): Sin
    {
        return new RestatedComment();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereLineComment()
            ->where(fn (AstNode $n): bool => $n->enclosingFunction() !== null)
            ->reject(fn (AstNode $n): bool => $n->isFunctionDeclaration())
            ->reject(fn (AstNode $n): bool => $n->isArrayItem())
            ->reject(fn (AstNode $n): bool => $n->hasCommentedOutCode())
            ->where(fn (AstNode $n): bool => $this->hasCommentRestatingTheCode($n))
            ->get();
    }

    /**
     * Does the prose above this statement say nothing the statement doesn't already say? The whole
     * run of comment lines is weighed as ONE block: a paragraph that enumerates the cases of a knotty
     * condition earns its place even though each line, alone, echoes a piece of the code.
     */
    private function hasCommentRestatingTheCode(AstNode $node): bool
    {
        $comment = array_unique(Prose::words(implode(' ', $node->lineComments())));

        if (count($comment) < self::MIN_WORDS) {
            return false;
        }

        $code = $node->codeWords();

        return array_all($comment, static fn (string $word): bool => in_array($word, $code, true));
    }
}
