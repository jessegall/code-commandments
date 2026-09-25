<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Comment;

/** The comments php-parser attaches to each node, as spans: a scribe reads a node's docblock and comments through them. */
final class Comments implements Question
{
    public function name(): string
    {
        return 'comments';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            yield [$node, null, array_map(
                static fn (Comment $comment): array => [$comment->getStartFilePos(), $comment->getEndFilePos() + 1],
                $node->getComments(),
            )];
        }
    }
}
