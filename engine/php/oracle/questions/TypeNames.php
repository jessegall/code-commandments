<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;

/** What TypeName reads from every written type: a parameter's, property's or constant's, and a function's return. */
final class TypeNames implements Question
{
    public function name(): string
    {
        return 'typenames';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            if (($node instanceof Node\Param || $node instanceof Node\Stmt\Property || $node instanceof Node\Stmt\ClassConst) && $node->type !== null) {
                yield [$node, 'declared', $this->read($node->type)];
            }
            if ($node instanceof Node\FunctionLike && $node->getReturnType() !== null) {
                yield [$node, 'returns', $this->read($node->getReturnType())];
            }
            if ($node instanceof Node\Param) {
                yield [$node, 'promises', array_map(static fn (string $scalar): bool => TypeName::promisesScalar($node, $scalar), ['string' => 'string', 'int' => 'int', 'bool' => 'bool', 'float' => 'float', 'array' => 'array'])];
            }
            if ($node instanceof Node\FunctionLike && $node->getReturnType() !== null) {
                foreach ($node->getParams() as $param) {
                    if ($param->type !== null) {
                        $one = TypeName::render($param->type);
                        $other = TypeName::render($node->getReturnType());
                        yield [$param, ['overlaps' => [$one, $other]], TypeName::overlaps($one, $other)];
                    }
                }
            }
        }
    }

    private function read(Node $type): array
    {
        $names = [];
        foreach (nodes([$type]) as $part) {
            if ($part instanceof Node\Name) {
                $names[] = $part->toString();
            }
        }

        return [
            'class' => TypeName::class($type),
            'simpleName' => TypeName::simpleName($type),
            'nullableClass' => TypeName::nullableClass($type),
            'isNullable' => TypeName::isNullable($type),
            'isNullableArray' => TypeName::isNullableArray($type),
            'render' => TypeName::render($type),
            'unionIncludes' => (object) array_map(static fn (string $name): bool => TypeName::unionIncludes($type, $name), array_combine($names, $names) ?: []),
            'isClassName' => (object) array_map(TypeName::isClassName(...), array_combine($names, $names) ?: []),
        ];
    }
}
