<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;

/** The call graph's callers of every method each class-like declares, each caller named by its file and start. */
final class Callers implements Question
{
    public function name(): string
    {
        return 'callers';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $index = $codebase->index();
        foreach (nodes($file->ast) as $node) {
            if (! $node instanceof Node\Stmt\ClassLike || $node->namespacedName === null) {
                continue;
            }
            $class = $node->namespacedName->toString();
            foreach ($node->getMethods() as $method) {
                yield [$method, $class, array_map(
                    static fn ($call): array => [relative($call->file->path), $call->node->getStartFilePos(), $call->node->getType()],
                    $index->callersOf($class, $method->name->toString()),
                )];
            }
        }
    }
}
