<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Comment;
use JesseGall\CodeCommandments\Cs\CommentMatch;
use JesseGall\CodeCommandments\CSharp\Detector;

/**
 * A rule about what a comment says — its prose, whichever kind of comment carries it. The C# twin of the
 * Python prose rules, over the comments the bridge hands over.
 */
abstract class ProseRule implements Detector
{
    /**
     * Does $comment commit the sin this rule is about?
     */
    abstract protected function isSinful(Comment $comment): bool;

    public function find(Codebase $codebase): array
    {
        $found = [];

        foreach ($codebase->modules() as $module) {
            foreach ($module->comments() as $comment) {
                if ($this->isSinful($comment)) {
                    $found[] = new CommentMatch($comment, $module);
                }
            }
        }

        return $found;
    }
}
