<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use PhpParser\Node;

/**
 * TypeResolver's answers: the type of every expression inside a function, as its callers ask it (the enclosing
 * function, the enclosing class), and what each class's members declare, through its ancestry.
 */
final class Types implements Question
{
    public function name(): string
    {
        return 'types';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $resolver = TypeResolver::forCodebase($codebase);
        foreach (nodes($file->ast) as $node) {
            if ($node instanceof Node\Expr && ($function = AstNode::enclosingFunctionOf($node)) !== null) {
                yield [$node, 'typeOf', $resolver->typeOf($node, $function, $codebase->wrap($node, $file)->enclosingClassName())];
            }
            if ($node instanceof Node\Stmt\ClassLike && AstNode::declaredClassNameOf($node) !== null) {
                yield [$node, 'members', $this->members($codebase, $resolver, AstNode::declaredClassNameOf($node))];
            }
            if (($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall) && $node->name instanceof Node\Identifier
                && ($function = AstNode::enclosingFunctionOf($node)) !== null) {
                $receiver = $resolver->typeOf($node->var, $function, $codebase->wrap($node, $file)->enclosingClassName());
                yield [$node, 'call', $this->method($resolver, $receiver, $node->name->toString(), count($node->args))];
            }
        }
    }

    private function members(Codebase $codebase, TypeResolver $resolver, string $class): array
    {
        $fields = ['nope'];
        $methods = ['nope'];
        foreach ([$class, ...$codebase->ancestorsOf($class)] as $owner) {
            $declaration = $codebase->declarationMatch($owner)?->node;
            if (! $declaration instanceof Node\Stmt\ClassLike) {
                continue;
            }
            foreach ($codebase->declarationMatch($owner)->fields() as $field) {
                $fields[] = $field->name;
            }
            foreach ($declaration->getMethods() as $method) {
                $methods[] = $method->name->toString();
            }
            foreach ($declaration->getTraitUses() as $use) {
                foreach ($use->traits as $trait) {
                    foreach ($codebase->declarationMatch($trait->toString())?->node?->getMethods() ?? [] as $method) {
                        $methods[] = $method->name->toString();
                    }
                }
            }
        }
        $answers = ['fields' => [], 'methods' => []];
        foreach (array_unique($fields) as $field) {
            $answers['fields'][$field] = [
                'declaringClassOf' => $resolver->declaringClassOf($class, $field),
                'propertyTypeOf' => $resolver->propertyTypeOf($class, $field),
                'collectionElementOf' => $resolver->collectionElementOf($class, $field),
            ];
        }
        foreach (array_unique($methods) as $method) {
            $answers['methods'][$method] = $this->method($resolver, $class, $method, 4);
        }

        return $answers;
    }

    private function method(TypeResolver $resolver, ?string $class, string $method, int $arity): array
    {
        $positions = range(0, max(0, $arity - 1));

        return [
            'declaringClassOfMethod' => $resolver->declaringClassOfMethod($class, $method),
            'methodIsVariadic' => $resolver->methodIsVariadic($class, $method),
            'paramTypeOf' => array_map(static fn (int $pos): ?string => $resolver->paramTypeOf($class, $method, $pos), $positions),
            'paramIsNullable' => array_map(static fn (int $pos): ?bool => $resolver->paramIsNullable($class, $method, $pos), $positions),
        ];
    }
}
