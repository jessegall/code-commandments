<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Resolvers\Ast;

use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Resolve a call/read receiver to its declared FQCN, plus the scope lookups that
 * feed it (enclosing class/function, a parameter's declared type, a property's
 * declared type).
 *
 * Resolution is purely declaration-based (a typed parameter `$x`, a typed
 * `$this->prop`): an untyped or chained receiver returns null (a deliberate
 * "leave it" for the caller), never a guess.
 */
final class ReceiverTypeResolver
{
    /**
     * The resolved FQCN of $recv — a typed param `$x`, or a typed `$this->prop`
     * — else null (an unresolved or chained receiver).
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses  alias => FQCN (see {@see FileImports::of()})
     */
    public static function resolve(Expr $recv, array $ast, array $uses, ?string $namespace, Node $context): ?string
    {
        if ($recv instanceof Expr\Variable && is_string($recv->name)) {
            $type = self::paramTypeInScope($recv->name, $context, $ast);

            return $type !== null ? ltrim(NameResolver::resolve($type, $uses, $namespace), '\\') : null;
        }

        if ($recv instanceof Expr\PropertyFetch
            && $recv->var instanceof Expr\Variable && $recv->var->name === 'this'
            && $recv->name instanceof Node\Identifier
        ) {
            $class = self::enclosingClass($context, $ast);
            $type = $class !== null ? self::propertyType($class, $recv->name->toString()) : null;

            return $type !== null ? ltrim(NameResolver::resolve($type, $uses, $namespace), '\\') : null;
        }

        return null;
    }

    /**
     * The declared type name of parameter $name in the function enclosing
     * $context (the innermost match), or null.
     *
     * @param  array<Node>  $ast
     */
    public static function paramTypeInScope(string $name, Node $context, array $ast): ?string
    {
        $pos = (int) $context->getStartFilePos();
        $finder = new NodeFinder;
        $best = null;
        $bestStart = -1;

        foreach (array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Expr\Closure::class),
        ) as $fn) {
            $start = (int) $fn->getStartFilePos();

            if ($start > $pos || (int) $fn->getEndFilePos() < $pos || $start <= $bestStart) {
                continue;
            }

            foreach ($fn->params as $param) {
                if ($param->var instanceof Expr\Variable && $param->var->name === $name
                    && $param->type instanceof Node\Name
                ) {
                    $best = $param->type->toString();
                    $bestStart = $start;
                }
            }
        }

        return $best;
    }

    /**
     * The innermost class declaration containing $node, or null.
     *
     * @param  array<Node>  $ast
     */
    public static function enclosingClass(Node $node, array $ast): ?Node\Stmt\Class_
    {
        $pos = (int) $node->getStartFilePos();
        $best = null;
        $bestStart = -1;

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $start = (int) $class->getStartFilePos();

            if ($start <= $pos && (int) $class->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $class;
                $bestStart = $start;
            }
        }

        return $best;
    }

    /**
     * The innermost function-like (method / function / closure) containing
     * $node, or null.
     *
     * @param  array<Node>  $ast
     */
    public static function enclosingFunction(Node $node, array $ast): ?Node\FunctionLike
    {
        $pos = (int) $node->getStartFilePos();
        $best = null;
        $bestStart = -1;
        $finder = new NodeFinder;

        foreach (array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Expr\Closure::class),
        ) as $fn) {
            $start = (int) $fn->getStartFilePos();

            if ($start <= $pos && (int) $fn->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $fn;
                $bestStart = $start;
            }
        }

        return $best;
    }

    /**
     * The declared type name of $property on $class — a promoted-constructor
     * param or a declared property — or null when untyped/absent.
     */
    public static function propertyType(Node\Stmt\Class_ $class, string $property): ?string
    {
        foreach ($class->getProperties() as $prop) {
            foreach ($prop->props as $declared) {
                if ($declared->name->toString() === $property && $prop->type instanceof Node\Name) {
                    return $prop->type->toString();
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Expr\Variable
                    && $param->var->name === $property && $param->type instanceof Node\Name
                ) {
                    return $param->type->toString();
                }
            }
        }

        return null;
    }
}
