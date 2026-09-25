<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;

/** What the codebase knows about each declared class-like, and whether each written type is a value type. */
final class Hierarchy implements Question
{
    /** @var list<string>|null */
    private ?array $contracts = null;

    public function name(): string
    {
        return 'hierarchy';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        foreach (nodes($file->ast) as $node) {
            if ($node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null) {
                yield [$node, 'class', $this->about($codebase, $node->namespacedName->toString())];
            }
            if (($node instanceof Node\Param || $node instanceof Node\Stmt\Property) && $node->type !== null) {
                yield [$node, 'isValueType', $codebase->isValueType($node->type)];
            }
            if ($node instanceof Node\Name) {
                yield [$node, 'named', [
                    'isEnum' => $codebase->isEnum($node->toString()),
                    'isInterface' => $codebase->isInterface($node->toString()),
                    'hasSubclass' => $codebase->hasSubclass($node->toString()),
                    'declared' => $codebase->declarationMatch($node->toString()) !== null,
                ]];
            }
        }
    }

    private function about(Codebase $codebase, string $class): array
    {
        $declaration = $codebase->declarationMatch($class);

        return [
            'ancestors' => $codebase->ancestorsOf($class),
            'implements' => array_values(array_filter($this->contracts($codebase), static fn (string $contract): bool => $codebase->implements($class, $contract))),
            'isEnum' => $codebase->isEnum($class),
            'isInterface' => $codebase->isInterface($class),
            'hasSubclass' => $codebase->hasSubclass($class),
            'classIsValueType' => $codebase->classIsValueType($class),
            'classNamed' => $codebase->classNamed($class)->node !== null,
            'declaration' => $declaration === null ? null : [relative($declaration->file->path), $declaration->node->getStartFilePos()],
            'usersOfTrait' => $codebase->usersOfTrait($class),
            'traitMethods' => array_map(static fn (Node\Stmt\ClassMethod $method): string => $method->name->toString(), $codebase->traitMethodsOf($class)),
            'fields' => $declaration === null ? [] : array_map(static fn ($field): array => [
                'name' => $field->name,
                'type' => TypeName::render($field->type),
                'isPublic' => $field->isPublic,
                'isPromoted' => $field->isPromoted,
            ], $declaration->fields()),
        ];
    }

    /** @return list<string> every name a declaration implements or an interface extends */
    private function contracts(Codebase $codebase): array
    {
        if ($this->contracts !== null) {
            return $this->contracts;
        }
        $names = [];
        foreach ($codebase->declarations() as $declaration) {
            $node = $declaration->node;
            $written = $node instanceof Node\Stmt\Interface_ ? $node->extends : ($node->implements ?? []);
            foreach ($written as $name) {
                $names[$name->toString()] = true;
            }
        }
        $names = array_keys($names);
        sort($names);

        return $this->contracts = $names;
    }
}
