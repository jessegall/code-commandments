<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Packages\Catalog as Packages;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
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
     * PHP collection built-ins that QUERY a collection, mapped to the argument
     * index that holds it. Recognising the LANGUAGE's array API by argument
     * position (not domain method names) lets us spot `array_reduce($order->lines(),
     * …)` / `in_array($x, $descriptor->handleNames())` — a query that belongs ON the
     * object. `count()` is left out on purpose: a size peek rarely warrants a move.
     */
    private const array COLLECTION_QUERIES = [
        'in_array' => 1, 'array_search' => 1, 'array_filter' => 0,
        'array_reduce' => 0, 'array_column' => 0, 'array_sum' => 0,
    ];

    /**
     * Framework boundary bases you don't move behaviour ONTO — an HTTP / MCP
     * request is an input carrier, not a home for domain logic; querying its data
     * is the contract, not envy.
     */

    /**
     * @param  array<string, true>  $ownedClasses          FQCN => true for every class declared in the codebase
     * @param  array<string, array<string, true>>  $contractMethods  FQCN => method-name set for interfaces / abstract classes
     * @param  array<string, ?string>  $parents             class FQCN => parent FQCN (for abstract-override lookup)
     */
    private function __construct(
        private readonly array $ownedClasses,
        private readonly array $contractMethods,
        private readonly array $parents,
        private readonly ChainResolver $chains,
        private readonly Codebase $codebase,
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

        return new self($owned, $contract, $parents, ChainResolver::forCodebase($codebase), $codebase);
    }

    /**
     * Is this method feature envy — should it move onto the object it operates on?
     */
    public function isEnviedOwner(AstNode $match): bool
    {
        return $this->enviedOwner($match) !== null;
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

        // Exemptions, all name-free:
        //  - a class implementing ANY interface is a deliberate polymorphic
        //    component (Strategy / Command / Visitor / Pipe / Handler) — the
        //    literature's exception; its behaviour is meant to act on other types;
        //  - a polymorphic contract method (interface / abstract override) likewise;
        //  - a method that CONSTRUCTS a value is a mapper/factory, not envy.
        if ($this->implementsInterface($class)
            || $this->fulfilsContract($class, $method->name->toString())
            || $this->constructs($method)) {
            return null;
        }

        $host = ($class->namespacedName ?? null)?->toString() ?? '';

        // Query shape: a PHP collection built-in run over a collection fetched from
        // an owned object reached through ANY chain — `array_reduce($order->lines())`,
        // `in_array($x, $ctx->descriptor->handleNames())`. The chain resolver follows
        // the value into nested objects.
        $queried = $this->queriedOwner($method, $host);

        if ($queried !== null) {
            return $queried;
        }

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

        // Fowler's test: it accesses that object MORE than its own `$this` state…
        if ($reaches['foreign'][$param] <= $reaches['own']) {
            return null;
        }

        // …AND operates on the object's internals — iterating its collection (a
        // query that belongs on it) or mutating its state (read-then-mutate, the
        // canonical DateTime->modify() form). Both are Move-Method candidates.
        $iterates = $this->traversesStructureOf($method, $param);
        $mutates = $this->mutates($method, $param);

        // Orchestration, not envy: a loop over the owner's collection whose
        // per-element work is handed to one of THIS object's own collaborators
        // (console IO, an injected catalog) is the orchestrator doing its OWN job
        // over a collection — moving the loop onto the owner would invert the
        // dependency. Self-recursion doesn't count; that genuinely belongs there.
        if ($iterates && ! $mutates && $this->delegatesElementToCollaborator($method, $param)) {
            return null;
        }

        return $iterates || $mutates ? $paramTypes[$param] : null;
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

    /**
     * In a `foreach ($param->coll as $el)`, is each element $el handed to one of
     * THIS object's own collaborators — `$this->collaborator($el, …)` — rather than
     * worked on through its own data? That is orchestration (the owner of the loop
     * does its job over the collection), not envy. A call to the enclosing method
     * itself (recursion) is excluded: that genuinely belongs on the owner.
     */
    private function delegatesElementToCollaborator(ClassMethod $method, string $param): bool
    {
        $self = $method->name->toString();
        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($method->stmts, Foreach_::class) as $loop) {
            if (! $this->isMemberAccessOf($loop->expr, $param)) {
                continue;
            }

            $element = $loop->valueVar;

            if (! $element instanceof Variable || ! is_string($element->name)) {
                continue;
            }

            foreach ($finder->findInstanceOf($loop->stmts, MethodCall::class) as $call) {
                if (! $call->var instanceof Variable || $call->var->name !== 'this'
                    || ! $call->name instanceof Identifier || $call->name->toString() === $self) {
                    continue;
                }

                foreach ($finder->findInstanceOf($call->args, Variable::class) as $arg) {
                    if ($arg->name === $element->name) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Does the class implement any interface (even a marker)? Such a class is a
     * deliberate polymorphic component — a pipe / handler / command / strategy /
     * visitor — the literature's exception, exempt from the mutation form of envy.
     */
    private function implementsInterface(ClassLike $class): bool
    {
        $implements = $class instanceof Class_ || $class instanceof Enum_ ? $class->implements : [];

        return $implements !== [];
    }

    /**
     * Does the method MUTATE $param's state — write to one of its properties
     * (`$param->x = …`, `$param->x += …`, `$param->x++`, `$param->items[] = …`)?
     * Reaching in to set another object's fields is the read-then-mutate form of
     * envy (and broken encapsulation); the change belongs on the object.
     */
    private function mutates(ClassMethod $method, string $param): bool
    {
        foreach ((new NodeFinder)->find($method->stmts, static fn (Node $n): bool => true) as $node) {
            $target = match (true) {
                $node instanceof Assign, $node instanceof AssignOp => $node->var,
                $node instanceof PreInc, $node instanceof PostInc,
                $node instanceof PreDec, $node instanceof PostDec => $node->var,
                default => null,
            };

            if ($target === null) {
                continue;
            }

            // Only writes that land on the object's OWN members — `$param->x = …`
            // or `$param->items[] = …` — not a write to a local seeded from it.
            $member = $target instanceof ArrayDimFetch ? $target->var : $target;

            if ($this->isMemberAccessOf($member, $param)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The owner an external collection query lands on, or null. Looks for a PHP
     * collection built-in {@see COLLECTION_QUERIES} whose collection argument is a
     * no-arg member of some receiver, then resolves that receiver — through any
     * chain — to an owned class other than the host. `array_reduce($order->lines())`
     * → Order; `in_array($x, $ctx->descriptor->handleNames())` → NodeDescriptor.
     */
    private function queriedOwner(ClassMethod $method, string $host): ?string
    {
        $paramTypes = $this->allParamTypes($method);

        if ($paramTypes === []) {
            return null;
        }

        foreach ((new NodeFinder)->findInstanceOf($method->stmts, FuncCall::class) as $call) {
            if (! $call->name instanceof Name) {
                continue;
            }

            $index = self::COLLECTION_QUERIES[strtolower($call->name->getLast())] ?? null;

            if ($index === null) {
                continue;
            }

            $argument = $call->args[$index] ?? null;

            if (! $argument instanceof Arg || ! $this->isCollectionAccess($argument->value)) {
                continue;
            }

            /** @var PropertyFetch|NullsafePropertyFetch|MethodCall|NullsafeMethodCall $collection */
            $collection = $argument->value;
            $owner = $this->chains->resolve($collection->var, $paramTypes);

            if ($owner !== null && $owner !== $host && isset($this->ownedClasses[$owner]) && ! $this->isBoundaryType($owner)) {
                return $owner;
            }
        }

        return null;
    }

    /**
     * Is $owner a framework request/boundary type — a class you query but never
     * move domain behaviour onto? {@see BOUNDARY_BASES}.
     */
    private function isBoundaryType(string $owner): bool
    {
        foreach (Packages::boundaryTypes() as $base) {
            if ($owner === $base || $this->codebase->extends($owner, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A no-arg member access (`$x->coll` or `$x->coll()`) — fetching a collection.
     * A parameterised call (`$x->coll($arg)`) is a query API, not a raw fetch.
     */
    private function isCollectionAccess(Node $node): bool
    {
        return $node instanceof PropertyFetch
            || $node instanceof NullsafePropertyFetch
            || (($node instanceof MethodCall || $node instanceof NullsafeMethodCall) && $node->args === []);
    }

    /**
     * Every typed parameter, name => owned-or-not type FQCN — the roots the chain
     * resolver starts from.
     *
     * @return array<string, string>
     */
    private function allParamTypes(ClassMethod $method): array
    {
        $types = [];

        foreach ($method->params as $param) {
            $type = self::typeName($param->type);

            if ($type !== null && $param->var instanceof Variable && is_string($param->var->name)) {
                $types[$param->var->name] = $type;
            }
        }

        return $types;
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
