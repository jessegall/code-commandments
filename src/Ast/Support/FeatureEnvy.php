<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * Decides whether a method is EXILED BEHAVIOUR (feature envy): it reaches THROUGH
 * one other owned object's internal structure — iterating its collections or
 * navigating its composed parts — to reconstruct knowledge the object should
 * expose itself, using none of its own class's state. The classic smell (Fowler):
 * a method more interested in another object's data than its own; the fix is to
 * Move the Method onto that object (`$node->edges()`, not `EdgeDetector::detect`).
 *
 * Every test is a semantic signal — no method-name or query-function lists:
 *
 *   - reaches into exactly ONE parameter — typed as an owned class other than the
 *     host — and TRAVERSES its structure: a `foreach` over `$p->collection`, or a
 *     `$p->a->b` chain into its parts. Reading flat scalar fields to compute a
 *     value (a grade, a label, a decision) is an external policy — a Strategy, the
 *     documented exception — not envy;
 *   - touches NO `$this` member (a hollow shell — accessors that read own state
 *     are excluded, with no name list);
 *   - CONSTRUCTS nothing (a `new`/`T::from()` body is a mapper/factory building a
 *     new value, which also sweeps up constructors and named factories for free);
 *   - is not a polymorphic contract method (an interface impl / abstract override
 *     is Strategy/Visitor dispatch — it can't move onto the data);
 *   - returns a value, and envies just one subject (two-plus owned subjects is
 *     orchestration, not envy of one).
 */
final class FeatureEnvy
{

    /**
     * @param  array<string, true>  $ownedClasses          FQCN => true for every class declared in the codebase
     * @param  array<string, array<string, true>>  $contractMethods  FQCN => method-name set for interfaces / abstract classes
     * @param  array<string, ?string>  $parents             class FQCN => parent FQCN (for abstract-override lookup)
     */
    private function __construct(
        private readonly array $ownedClasses,
        private readonly array $contractMethods,
        private readonly array $parents,
    ) {}

    public static function forCodebase(Codebase $codebase): self
    {
        $finder = new NodeFinder;
        $owned = [];
        $contract = [];
        $parents = [];

        foreach ($codebase->files() as $file) {
            foreach ($finder->findInstanceOf($file->ast, ClassLike::class) as $type) {
                $fqcn = ($type->namespacedName ?? null)?->toString();

                if ($fqcn === null) {
                    continue;
                }

                // An interface's methods, and a (possibly abstract) class's own
                // methods, are contracts that implementors / subclasses override.
                $contract[$fqcn] = self::methodNames($type);

                if ($type instanceof Interface_) {
                    continue;
                }

                $owned[$fqcn] = true;
                $parents[$fqcn] = $type instanceof Class_ && $type->extends instanceof Name
                    ? $type->extends->toString()
                    : null;
            }
        }

        return new self($owned, $contract, $parents);
    }

    /**
     * The FQCN of the object this method is envious of, or null when it isn't.
     */
    public function enviedOwner(AstNode $match): ?string
    {
        $method = $match->node;
        $class = $match->enclosingClass();

        if (! $method instanceof ClassMethod || $method->stmts === null || $class === null) {
            return null;
        }

        if ($this->fulfilsContract($class, $method->name->toString())) {
            return null;
        }

        if (! $this->returnsValue($method) || $this->constructs($method)) {
            return null;
        }

        $host = ($class->namespacedName ?? null)?->toString() ?? '';
        $paramTypes = $this->ownedParamTypes($method, $host);

        if ($paramTypes === []) {
            return null;
        }

        $reaches = $this->countReaches($method, array_keys($paramTypes));

        // Envious of exactly one owned subject (two+ is orchestration).
        if (count($reaches['foreign']) !== 1) {
            return null;
        }

        $param = array_key_first($reaches['foreign']);

        // Fowler's test: it accesses that object MORE than its own (here, more than
        // `$this`), and reaches THROUGH its structure — iterating its collection.
        if ($reaches['foreign'][$param] <= $reaches['own']) {
            return null;
        }

        return $this->traversesStructureOf($method, $param) ? $paramTypes[$param] : null;
    }

    /**
     * Is $methodName declared by an interface this class implements, or by an
     * abstract ancestor — i.e. a polymorphic override, not a movable method?
     */
    private function fulfilsContract(ClassLike $class, string $methodName): bool
    {
        $name = strtolower($methodName);

        $implements = $class instanceof Class_ || $class instanceof Enum_ ? $class->implements : [];

        foreach ($implements as $interface) {
            if (isset($this->contractMethods[$interface->toString()][$name])) {
                return true;
            }
        }

        $parent = $class instanceof Class_ && $class->extends instanceof Name ? $class->extends->toString() : null;
        $seen = [];

        while ($parent !== null && ! isset($seen[$parent])) {
            $seen[$parent] = true;

            if (isset($this->contractMethods[$parent][$name])) {
                return true;
            }

            $parent = $this->parents[$parent] ?? null;
        }

        return false;
    }

    /**
     * Does the method ITERATE one of $param's collections — `foreach ($param->coll
     * as …)` — operating on the object's internal structure rather than reading its
     * flat scalar fields? Looping another object's collection is the textbook
     * feature-envy shape (the work belongs on that object); a flat-field
     * calculation is an external policy (a Strategy), not envy.
     */
    private function traversesStructureOf(ClassMethod $method, string $param): bool
    {
        foreach ((new NodeFinder)->findInstanceOf($method->stmts, Foreach_::class) as $loop) {
            if ($this->isMemberAccessOf($loop->expr, $param)) {
                return true;
            }
        }

        return false;
    }

    private function isMemberAccess(Node $node): bool
    {
        return $node instanceof PropertyFetch
            || $node instanceof NullsafePropertyFetch
            || $node instanceof MethodCall
            || $node instanceof NullsafeMethodCall;
    }

    /**
     * Is $expr a member access (`$param->x` / `$param->x()`) directly on $param?
     */
    private function isMemberAccessOf(Node $expr, string $param): bool
    {
        return $this->isMemberAccess($expr)
            && $expr->var instanceof Variable
            && $expr->var->name === $param;
    }

    private function returnsValue(ClassMethod $method): bool
    {
        foreach ((new NodeFinder)->findInstanceOf($method->stmts, Return_::class) as $return) {
            if ($return->expr !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the body build an object (`new T`, or a `T::from(...)` named ctor)? Such
     * a method maps its input into a new value — a factory/mapper, not envy.
     */
    private function constructs(ClassMethod $method): bool
    {
        $finder = new NodeFinder;

        if ($finder->findFirstInstanceOf($method->stmts, New_::class) !== null) {
            return true;
        }

        foreach ($finder->findInstanceOf($method->stmts, StaticCall::class) as $call) {
            if ($call->class instanceof Name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Param name => owner FQCN, for params typed as an owned class other than the host.
     *
     * @return array<string, string>
     */
    private function ownedParamTypes(ClassMethod $method, string $host): array
    {
        $types = [];

        foreach ($method->params as $param) {
            if (! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $type = self::typeName($param->type);

            if ($type !== null && $type !== $host && isset($this->ownedClasses[$type])) {
                $types[$param->var->name] = $type;
            }
        }

        return $types;
    }

    /**
     * Count member accesses on `$this` (own) and, per candidate param, on that
     * param (foreign) — the method's focus, used to require a hollow shell (no own
     * state) envious of exactly one owned subject.
     *
     * @param  list<string>  $params
     * @return array{own: int, foreign: array<string, int>}
     */
    private function countReaches(ClassMethod $method, array $params): array
    {
        $own = 0;
        $foreign = [];
        $candidates = array_flip($params);

        foreach ((new NodeFinder)->find($method->stmts, static fn (Node $n): bool => true) as $node) {
            if (! $this->isMemberAccess($node) || ! $node->var instanceof Variable || ! is_string($node->var->name)) {
                continue;
            }

            if ($node->var->name === 'this') {
                $own++;
            } elseif (isset($candidates[$node->var->name])) {
                $foreign[$node->var->name] = ($foreign[$node->var->name] ?? 0) + 1;
            }
        }

        return ['own' => $own, 'foreign' => $foreign];
    }

    /**
     * @return array<string, true>
     */
    private static function methodNames(ClassLike $type): array
    {
        $names = [];

        foreach ($type->getMethods() as $method) {
            $names[strtolower($method->name->toString())] = true;
        }

        return $names;
    }

    private static function typeName(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        return match (true) {
            $type instanceof Name => $type->toString(),
            $type instanceof Identifier => $type->toString(),
            default => null,
        };
    }
}
