<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Extract all class declarations from an AST.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class ExtractClasses implements Pipe
{
    public function handle(mixed $input): mixed
    {
        if (! $input->hasAst()) {
            return $input;
        }

        $nodeFinder = new NodeFinder;
        $classes = $nodeFinder->findInstanceOf($input->ast, Node\Stmt\Class_::class);

        return $input->with(classes: $classes);
    }
}
