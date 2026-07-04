<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

/**
 * Best-effort static type of an expression, resolved through the RECEIVER CHAIN — the
 * "receiver of the receiver of the receiver". Where {@see ReceiverResolver} answers one hop
 * ($this, a typed param), this follows the whole chain: a local typed from its assignment
 * ORIGIN (`$x = Foo::from(...)`, `new Foo`, `$this->prop`, a typed method return), then a
 * property/method chain read off it (`$a->b->c()`), hop by hop, against a codebase-wide index
 * of field types, method return types, and parameter nullability.
 *
 * Best-effort by design: an unresolvable link yields null (the caller stays conservative rather
 * than guess), and the type is the DECLARED one — no flow-narrowing. It underpins usage-driven
 * rules that must know "what does this value actually flow into" — e.g. whether a nullable field
 * is ever read as if present. Names are fully-qualified (the parser resolved them); memoised per
 * codebase like {@see DataClassShape}.
 */
final class TypeResolver
{
    /** @var array<string, array<string, ?string>>  fqcn => field => declared type fqcn */
    private array $fieldType = [];

    /** @var array<string, array<string, ?string>>  fqcn => method => return type fqcn */
    private array $returnType = [];

    /** @var array<string, array<string, array<int, bool>>>  fqcn => method => pos => param is nullable */
    private array $paramNullable = [];

    /** @var array<int, array<string, ?string>>  object-id of a function => local var => type */
    private array $localCache = [];

    private static ?\WeakMap $memo = null;

    private function __construct(Codebase $codebase)
    {
        $this->index($codebase);
    }

    public static function forCodebase(Codebase $codebase): self
    {
        self::$memo ??= new \WeakMap();

        return self::$memo[$codebase] ??= new self($codebase);
    }

    /**
     * The type $expr evaluates to inside $function, or null when it can't be resolved cheaply.
     */
    public function typeOf(Node $expr, FunctionLike $function, ?string $selfFqcn): ?string
    {
        return $this->resolve($expr, $this->localTypes($function, $selfFqcn), $selfFqcn);
    }

    /**
     * Is the $pos-th parameter of $fqcn::$method nullable (or absent from the index)? Null when the
     * signature isn't known — the caller must not treat "unknown" as "non-nullable".
     */
    public function paramIsNullable(?string $fqcn, string $method, int $pos): ?bool
    {
        return $this->paramNullable[ltrim((string) $fqcn, '\\')][$method][$pos] ?? null;
    }

    private function resolve(Node $expr, array $locals, ?string $selfFqcn): ?string
    {
        if ($expr instanceof Variable && is_string($expr->name)) {
            return $expr->name === 'this' ? $selfFqcn : ($locals[$expr->name] ?? null);
        }

        if ($expr instanceof New_ && $expr->class instanceof Name) {
            return ltrim($expr->class->toString(), '\\');
        }

        if ($expr instanceof StaticCall && $expr->class instanceof Name && $expr->name instanceof Identifier) {
            $class = ltrim($expr->class->toString(), '\\');

            // A `::from()`/`::from<Type>()` magic factory returns the class itself; any other static
            // call resolves through its declared return type.
            return str_starts_with($expr->name->toString(), 'from') ? $class : ($this->returnType[$class][$expr->name->toString()] ?? null);
        }

        if ($expr instanceof PropertyFetch && $expr->name instanceof Identifier) {
            $receiver = $this->resolve($expr->var, $locals, $selfFqcn);

            return $receiver === null ? null : ($this->fieldType[$receiver][$expr->name->toString()] ?? null);
        }

        if (($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall) && $expr->name instanceof Identifier) {
            $receiver = $this->resolve($expr->var, $locals, $selfFqcn);

            return $receiver === null ? null : ($this->returnType[$receiver][$expr->name->toString()] ?? null);
        }

        return null;
    }

    /**
     * The local variables of $function typed from their assignment ORIGIN — resolved in source
     * order so a later assignment can lean on an earlier one. Memoised per function.
     *
     * @return array<string, ?string>
     */
    private function localTypes(FunctionLike $function, ?string $selfFqcn): array
    {
        $id = spl_object_id($function);

        if (isset($this->localCache[$id])) {
            return $this->localCache[$id];
        }

        $locals = [];

        foreach ($function->getParams() as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $locals[$param->var->name] = self::typeName($param->type);
            }
        }

        foreach (new NodeFinder()->findInstanceOf($function, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && is_string($assign->var->name)) {
                $locals[$assign->var->name] = $this->resolve($assign->expr, $locals, $selfFqcn);
            }
        }

        return $this->localCache[$id] = $locals;
    }

    private function index(Codebase $codebase): void
    {
        $finder = new NodeFinder();

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, Class_::class) as $class) {
                $fqcn = ltrim(($class->namespacedName ?? null)?->toString() ?? '', '\\');

                if ($fqcn === '') {
                    continue;
                }

                foreach ($class->getMethod('__construct')?->params ?? [] as $param) {
                    if ($param->flags !== 0 && $param->var instanceof Variable && is_string($param->var->name)) {
                        $this->fieldType[$fqcn][$param->var->name] = self::typeName($param->type);
                    }
                }

                foreach ($class->getProperties() as $property) {
                    foreach ($property->props as $declared) {
                        $this->fieldType[$fqcn][$declared->name->toString()] = self::typeName($property->type);
                    }
                }

                foreach ($class->getMethods() as $method) {
                    $this->returnType[$fqcn][$method->name->toString()] = self::typeName($method->returnType);

                    foreach (array_values($method->params) as $pos => $param) {
                        $this->paramNullable[$fqcn][$method->name->toString()][$pos] = self::paramAcceptsNull($param);
                    }
                }
            }
        }
    }

    /**
     * Can this parameter receive `null`? True when its type admits null — nullable (`?T`/`T|null`),
     * untyped, or `mixed` — or when it defaults to the null literal. A non-null default (`int $x = 0`)
     * does NOT make it null-accepting; that's the difference between "optional" and "nullable".
     */
    public static function paramAcceptsNull(Node\Param $param): bool
    {
        return $param->type === null
            || $param->type instanceof NullableType
            || ($param->type instanceof Identifier && strtolower($param->type->toString()) === 'mixed')
            || ($param->type instanceof Node\UnionType && self::unionHasNull($param->type))
            || ($param->default instanceof ConstFetch && $param->default->name->toLowerString() === 'null');
    }

    private static function unionHasNull(Node\UnionType $type): bool
    {
        foreach ($type->types as $member) {
            if ($member instanceof Identifier && strtolower($member->toString()) === 'null') {
                return true;
            }
        }

        return false;
    }

    private static function typeName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return self::typeName($type->type);
        }

        return $type instanceof Name ? ltrim($type->toString(), '\\') : null;
    }
}
