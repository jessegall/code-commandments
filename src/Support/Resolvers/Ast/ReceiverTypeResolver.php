<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Resolvers\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Resolve a declaration-typed receiver (a typed param `$x`, a typed `$this->prop`) to its FQCN, plus the enclosing-scope lookups that feed it.
 */
final class ReceiverTypeResolver
{
    /** Function-like node kinds, for the enclosing-scope lookups. */
    private const FUNCTION_LIKE = [Node\Stmt\ClassMethod::class, Node\Stmt\Function_::class, Expr\Closure::class, Expr\ArrowFunction::class];

    /**
     * The resolved FQCN of $recv — a typed param `$x`, or a typed `$this->prop`
     * — else null (an unresolved or chained receiver).
     */
    public static function resolve(Expr $recv, FileAst $file, Node $context): ?string
    {
        if ($recv instanceof Expr\Variable && is_string($recv->name)) {
            $type = self::paramTypeInScope($recv->name, $context, $file->nodes);

            return $type !== null ? $file->resolveType($type) : null;
        }

        if ($recv instanceof Expr\PropertyFetch
            && $recv->var instanceof Expr\Variable && $recv->var->name === 'this'
            && $recv->name instanceof Node\Identifier
        ) {
            $class = self::enclosingClass($context, $file->nodes);
            $type = $class !== null ? self::propertyType($class, $recv->name->toString()) : null;

            return $type !== null ? $file->resolveType($type) : null;
        }

        return null;
    }

    /**
     * The declared type name of parameter $name in the innermost function that
     * encloses $context AND declares it (handles a method param used inside a
     * nested closure), or null.
     *
     * @param  array<Node>  $ast
     */
    public static function paramTypeInScope(string $name, Node $context, array $ast): ?string
    {
        $fn = self::innermost($ast, $context, self::FUNCTION_LIKE, static function (Node $fn) use ($name): bool {
            foreach ($fn->params as $param) {
                if ($param->var instanceof Expr\Variable && $param->var->name === $name && $param->type instanceof Node\Name) {
                    return true;
                }
            }

            return false;
        });

        if ($fn === null) {
            return null;
        }

        foreach ($fn->params as $param) {
            if ($param->var instanceof Expr\Variable && $param->var->name === $name && $param->type instanceof Node\Name) {
                return $param->type->toString();
            }
        }

        return null;
    }

    /**
     * The declared type NODE of parameter $name in the innermost enclosing
     * function that declares it — the raw type (`Name`, `Identifier`,
     * `NullableType`, `UnionType`, or null when untyped). Use this when you need
     * to inspect the type (e.g. nullability); use {@see paramTypeInScope()} when a
     * resolvable class-name string is enough.
     *
     * @param  array<Node>  $ast
     */
    public static function paramTypeNode(string $name, Node $context, array $ast): ?Node
    {
        $fn = self::innermost($ast, $context, self::FUNCTION_LIKE, static function (Node $fn) use ($name): bool {
            foreach ($fn->params as $param) {
                if ($param->var instanceof Expr\Variable && $param->var->name === $name) {
                    return true;
                }
            }

            return false;
        });

        if ($fn === null) {
            return null;
        }

        foreach ($fn->params as $param) {
            if ($param->var instanceof Expr\Variable && $param->var->name === $name) {
                return $param->type;
            }
        }

        return null;
    }

    /**
     * The declared type NODE of $property on $class (a promoted-constructor param
     * or a declared property), or null when untyped/absent. The node counterpart
     * of {@see propertyType()}.
     */
    public static function propertyTypeNode(Node\Stmt\Class_ $class, string $property): ?Node
    {
        foreach ($class->getProperties() as $prop) {
            foreach ($prop->props as $declared) {
                if ($declared->name->toString() === $property) {
                    return $prop->type;
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Expr\Variable && $param->var->name === $property) {
                    return $param->type;
                }
            }
        }

        return null;
    }

    /**
     * The innermost class declaration containing $node, or null.
     *
     * @param  array<Node>  $ast
     */
    public static function enclosingClass(Node $node, array $ast): ?Node\Stmt\Class_
    {
        $class = self::innermost($ast, $node, [Node\Stmt\Class_::class]);

        return $class instanceof Node\Stmt\Class_ ? $class : null;
    }

    /**
     * The innermost function-like (method / function / closure) containing
     * $node, or null.
     *
     * @param  array<Node>  $ast
     */
    public static function enclosingFunction(Node $node, array $ast): ?Node\FunctionLike
    {
        $fn = self::innermost($ast, $node, self::FUNCTION_LIKE);

        return $fn instanceof Node\FunctionLike ? $fn : null;
    }

    /**
     * The innermost node of one of $types whose source range contains $node,
     * optionally constrained by $accept — the one shared "walk enclosing scopes"
     * loop behind the lookups above.
     *
     * @param  array<Node>  $ast
     * @param  list<class-string<Node>>  $types
     * @param  (callable(Node): bool)|null  $accept
     */
    private static function innermost(array $ast, Node $node, array $types, ?callable $accept = null): ?Node
    {
        $pos = (int) $node->getStartFilePos();
        $finder = new NodeFinder;
        $best = null;
        $bestStart = -1;

        foreach ($types as $type) {
            foreach ($finder->findInstanceOf($ast, $type) as $candidate) {
                $start = (int) $candidate->getStartFilePos();

                if ($start <= $pos && (int) $candidate->getEndFilePos() >= $pos && $start > $bestStart
                    && ($accept === null || $accept($candidate))
                ) {
                    $best = $candidate;
                    $bestStart = $start;
                }
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
