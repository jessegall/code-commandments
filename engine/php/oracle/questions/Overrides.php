<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;

/** Whether each method of each class-like answers to one an ancestor declares. */
final class Overrides implements Question
{
    public function name(): string
    {
        return 'overrides';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            if (! $node instanceof Node\Stmt\ClassLike || $node->namespacedName === null) {
                continue;
            }
            $class = $node->namespacedName->toString();
            foreach ($node->getMethods() as $method) {
                yield [$method, [$class, $method->name->toString()], $codebase->overridesMethod($class, $method->name->toString())];
            }
        }
    }
}
