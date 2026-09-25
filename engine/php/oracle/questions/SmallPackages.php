<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Concurrent\ConcurrentNode;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\PhpTypes\OptionNode;
use PhpParser\Node;

/** The jessegall/concurrent and jessegall/php-types decorators' answers about every node they speak of. */
final class SmallPackages implements Question
{
    public function name(): string
    {
        return 'smallpackages';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            $concurrent = $codebase->wrap($node, $file, ConcurrentNode::class);
            $option = $codebase->wrap($node, $file, OptionNode::class);
            if ($node instanceof Node\Stmt\ClassMethod) {
                yield [$node, 'extendsConcurrent', $concurrent->extendsConcurrent()];
            }
            if ($node instanceof Node\Param || $node instanceof Node\Stmt\Property || $node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Stmt\Function_) {
                yield [$node, 'declaresNullableOption', $option->declaresNullableOption()];
            }
            if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall) {
                yield [$node, 'isUnwrapOrNull', $option->isUnwrapOrNull()];
            }
            if ($node instanceof Node\Name) {
                yield [$node, 'isOption', OptionNode::isOption($node->toString())];
            }
        }
    }
}
