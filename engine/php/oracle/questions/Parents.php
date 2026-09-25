<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;

/** Each node's parent kind, as the PHP engine wires it: the tree the Go side reads must be the same tree. */
final class Parents implements Question
{
    public function name(): string
    {
        return 'parents';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            $parent = $node->getAttribute('parent');

            yield [$node, null, $parent instanceof Node ? $parent->getType() : 'File'];
        }
    }
}
