<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Node;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Resolves a subject expression (`$var`, `$this->prop`, `$obj->prop`) to the
 * `[enumFqcn, nullable]` of its declared type — shared by the prophets that must
 * decide whether a CompareSelf comparison can ANCHOR on a non-null instance
 * (`$subject->equalsAny(...)`) or must keep the null-safe static form
 * (`Enum::equalsAny($subject, ...)`).
 *
 * Conservative by design: returns null whenever the type cannot be PROVEN a
 * single class type (scalar, mixed, union-of-classes, intersection, or
 * unresolved), so a caller never anchors on an uncertain subject.
 */
final class EnumInstanceResolver
{
    /**
     * @param  array<Node>  $ast
     * @param  array<string,string>  $uses  alias => FQCN
     * @return array{0:string,1:bool}|null  [fqcn, nullable]
     */
    public static function resolve(Node $subject, int $pos, array $ast, NodeFinder $finder, array $uses, ?string $namespace): ?array
    {
        if ($subject instanceof Node\Expr\Variable && is_string($subject->name)) {
            return self::resolveTypeNode(self::paramTypeNode($subject->name, $pos, $ast, $finder), $uses, $namespace);
        }

        if ($subject instanceof Node\Expr\PropertyFetch
            && $subject->name instanceof Node\Identifier
            && $subject->var instanceof Node\Expr\Variable
            && is_string($subject->var->name)
        ) {
            $prop = $subject->name->toString();

            if ($subject->var->name === 'this') {
                $class = self::enclosingClassLike($pos, $ast, $finder);

                return $class === null ? null : self::resolveTypeNode(self::propertyTypeNode($class, $prop), $uses, $namespace);
            }

            $owner = self::resolveTypeNode(self::paramTypeNode($subject->var->name, $pos, $ast, $finder), $uses, $namespace);

            if ($owner === null) {
                return null;
            }

            $ownerNode = self::classLikeNodeFor($owner[0], $ast, $finder, $namespace);

            if ($ownerNode !== null) {
                return self::resolveTypeNode(self::propertyTypeNode($ownerNode, $prop), $uses, $namespace);
            }

            return self::reflectPropertyType($owner[0], $prop);
        }

        return null;
    }

    /**
     * @param  array<string,string>  $uses
     * @return array{0:string,1:bool}|null
     */
    private static function resolveTypeNode(?Node $type, array $uses, ?string $namespace): ?array
    {
        if ($type instanceof Node\NullableType) {
            $inner = self::resolveTypeNode($type->type, $uses, $namespace);

            return $inner === null ? null : [$inner[0], true];
        }

        if ($type instanceof Node\UnionType) {
            $nullable = false;
            $fqcn = null;
            $classParts = 0;

            foreach ($type->types as $part) {
                if ($part instanceof Node\Identifier && strtolower($part->toString()) === 'null') {
                    $nullable = true;

                    continue;
                }

                if ($part instanceof Node\Name) {
                    $fqcn = ltrim(NameResolver::resolve($part->toString(), $uses, $namespace), '\\');
                    $classParts++;

                    continue;
                }

                return null;
            }

            return ($fqcn !== null && $classParts === 1) ? [$fqcn, $nullable] : null;
        }

        if ($type instanceof Node\Name) {
            return [ltrim(NameResolver::resolve($type->toString(), $uses, $namespace), '\\'), false];
        }

        return null;
    }

    private static function propertyTypeNode(Node\Stmt\ClassLike $class, string $property): ?Node
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
                if ($param->flags !== 0 && $param->var instanceof Node\Expr\Variable && $param->var->name === $property) {
                    return $param->type;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:bool}|null
     */
    private static function reflectPropertyType(string $ownerFqcn, string $property): ?array
    {
        if (! class_exists($ownerFqcn) && ! enum_exists($ownerFqcn) && ! interface_exists($ownerFqcn)) {
            return null;
        }

        try {
            $rc = new ReflectionClass($ownerFqcn);

            if (! $rc->hasProperty($property)) {
                return null;
            }

            $type = $rc->getProperty($property)->getType();
        } catch (\Throwable) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() ? null : [ltrim($type->getName(), '\\'), $type->allowsNull()];
        }

        if ($type instanceof ReflectionUnionType) {
            $fqcn = null;
            $classParts = 0;

            foreach ($type->getTypes() as $part) {
                if ($part instanceof ReflectionNamedType && ! $part->isBuiltin() && strtolower($part->getName()) !== 'null') {
                    $fqcn = ltrim($part->getName(), '\\');
                    $classParts++;
                }
            }

            return ($fqcn !== null && $classParts === 1) ? [$fqcn, $type->allowsNull()] : null;
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    public static function classLikeNodeFor(string $fqcn, array $ast, NodeFinder $finder, ?string $namespace): ?Node\Stmt\ClassLike
    {
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $node) {
            if ($node->name === null) {
                continue;
            }

            $declared = $namespace !== null && $namespace !== '' ? $namespace . '\\' . $node->name->toString() : $node->name->toString();

            if (ltrim($declared, '\\') === ltrim($fqcn, '\\')) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private static function enclosingClassLike(int $pos, array $ast, NodeFinder $finder): ?Node\Stmt\ClassLike
    {
        $best = null;
        $bestStart = -1;

        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $node) {
            $start = (int) $node->getStartFilePos();

            if ($start <= $pos && (int) $node->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $node;
                $bestStart = $start;
            }
        }

        return $best;
    }

    /**
     * @param  array<Node>  $ast
     */
    private static function paramTypeNode(string $name, int $pos, array $ast, NodeFinder $finder): ?Node
    {
        $best = null;
        $bestStart = -1;

        $functions = array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Node\Expr\Closure::class),
            $finder->findInstanceOf($ast, Node\Expr\ArrowFunction::class),
        );

        foreach ($functions as $fn) {
            $start = (int) $fn->getStartFilePos();

            if ($start > $pos || (int) $fn->getEndFilePos() < $pos || $start <= $bestStart) {
                continue;
            }

            foreach ($fn->params as $param) {
                if ($param->var instanceof Node\Expr\Variable && $param->var->name === $name && $param->type !== null) {
                    $best = $param->type;
                    $bestStart = $start;
                }
            }
        }

        return $best;
    }
}
