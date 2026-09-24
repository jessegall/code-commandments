<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The comments a module holds, read as runs above the code — Python's and C#'s the same way. A comment that
 * trails code on its line is not above anything.
 */
trait CommentRuns
{
    /**
     * @var array<int, object{start: int}>|null  each comment standing on a line of its own, by its line
     */
    private ?array $ownLineComments = null;

    /**
     * Every comment in the module, in the order it is written.
     *
     * @return list<object{start: int}>
     */
    abstract public function comments(): array;

    abstract public function lineAt(int $offset): int;

    /**
     * The run of comments standing on lines of their own directly above $node — the last on the line before it,
     * each earlier one on the line before that.
     *
     * @return list<object{start: int}>
     */
    public function commentsAbove(SyntaxNode $node): array
    {
        $this->ownLineComments ??= $this->ownLineComments();
        $run = [];

        for ($line = $this->lineAt($node->start) - 1; isset($this->ownLineComments[$line]); $line--) {
            array_unshift($run, $this->ownLineComments[$line]);
        }

        return $run;
    }

    /**
     * @return array<int, object{start: int}>
     */
    private function ownLineComments(): array
    {
        $byLine = [];

        foreach ($this->comments() as $comment) {
            $lineStart = (int) strrpos(substr($this->source, 0, $comment->start), "\n");

            if (trim(substr($this->source, $lineStart, $comment->start - $lineStart)) === '') {
                $byLine[$this->lineAt($comment->start)] = $comment;
            }
        }

        return $byLine;
    }
}
