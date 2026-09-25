<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;

/** Every variable's trace: each occurrence of it in its function, by where it starts and how it is used there. */
final class Traces implements Question
{
    public function name(): string
    {
        return 'traces';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            if ($node instanceof Node\Expr\Variable) {
                yield [$node, null, array_map(
                    static fn ($interaction): array => [$interaction->node->node->getStartFilePos(), $interaction->kind->value, $interaction->isWrite(), $interaction->deNulls()],
                    $codebase->wrap($node, $file)->trace(),
                )];
            }
        }
    }
}
