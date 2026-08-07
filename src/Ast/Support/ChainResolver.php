<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeFinder;

/**
 * Resolves a property/method chain's final type by walking hops through declared types —
 * best-effort and conservative, returning null for untyped/parameterised hops. Detectors
 * use it to follow values through nested objects one hop at a time.
 */
final class ChainResolver
{
    /**
     * @param  array<string, array<string, string>>  $propertyTypes  class FQCN => [property => type FQCN]
     * @param  array<string, array<string, string>>  $returnTypes    class FQCN => [lowercase method => return type FQCN]
     */
    private function __construct(
        private readonly array $propertyTypes,
        private readonly array $returnTypes,
    ) {}

    public static function forCodebase(Codebase $codebase): self
    {
        $finder = new NodeFinder;
        $properties = [];
        $returns = [];

        foreach ($codebase->files() as $file) {
            foreach ([...$finder->findInstanceOf($file->ast, Class_::class), ...$finder->findInstanceOf($file->ast, Enum_::class)] as $class) {
                $fqcn = ($class->namespacedName ?? null)?->toString();

                if ($fqcn === null) {
                    continue;
                }

                $properties[$fqcn] = self::propertyTypesOf($class);
                $returns[$fqcn] = self::returnTypesOf($class);
            }
        }

        return new self($properties, $returns);
    }

    /**
     * The FQCN the expression resolves to, or null when it can't be resolved
     * cheaply. `$paramTypes` maps the enclosing method's parameter names to types.
     *
     * @param  array<string, string>  $paramTypes
     */
    public function resolve(Node $expr, array $paramTypes): ?string
    {
        if ($expr instanceof Variable) {
            return is_string($expr->name) ? ($paramTypes[$expr->name] ?? null) : null;
        }

        if (AstNode::isPropertyRead($expr)) {
            $base = $this->resolveOwner($expr->var, $paramTypes);

            return $base !== null && $expr->name instanceof Identifier
                ? ($this->propertyTypes[$base][$expr->name->toString()] ?? null)
                : null;
        }

        if ((AstNode::isMethodSend($expr)) && $expr->args === []) {
            $base = $this->resolveOwner($expr->var, $paramTypes);

            return $base !== null && $expr->name instanceof Identifier
                ? ($this->returnTypes[$base][strtolower($expr->name->toString())] ?? null)
                : null;
        }

        return null;
    }

    /**
     * @param  array<string, string>  $paramTypes
     */
    private function resolveOwner(Node $expr, array $paramTypes): ?string
    {
        $type = $this->resolve($expr, $paramTypes);

        return $type === null ? null : ltrim($type, '\\');
    }

    /**
     * @return array<string, string>
     */
    private static function propertyTypesOf(Class_|Enum_ $class): array
    {
        $types = [];

        foreach ($class->getProperties() as $property) {
            $type = self::typeName($property->type);

            if ($type !== null) {
                foreach ($property->props as $prop) {
                    $types[$prop->name->toString()] = $type;
                }
            }
        }

        $constructor = AstNode::constructorOf($class);

        if ($constructor !== null) {
            foreach ($constructor->params as $param) {
                $type = self::typeName($param->type);

                if ($param->flags !== 0 && $param->var instanceof Variable && is_string($param->var->name) && $type !== null) {
                    $types[$param->var->name] = $type;
                }
            }
        }

        return $types;
    }

    /**
     * @return array<string, string>
     */
    private static function returnTypesOf(Class_|Enum_ $class): array
    {
        $types = [];

        foreach ($class->getMethods() as $method) {
            $type = self::typeName($method->returnType);

            if ($type !== null) {
                $types[strtolower($method->name->toString())] = $type;
            }
        }

        return $types;
    }

    private static function typeName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        return $type instanceof Name ? ltrim($type->toString(), '\\') : null;
    }
}
