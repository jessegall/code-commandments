<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use SplObjectStorage;

/**
 * Name-free AST detector for the "registry shape": a class you PUT things into
 * and LOOK things up from. Concretely — a public method writes a keyed store
 * (`$this->store[$key] = …`, the `register`/`add`/`put`/`set`/… signal, derived
 * from the AST rather than a verb list) AND some method reads that same store
 * (`$this->store[$key]`, the lookup).
 *
 * This is the single predicate shared by every registry-aware rule (the
 * markerless RegistryReturnContract path, the registry-scoped NoNullCoalesceToNull
 * / PreferOptionOverNull broadenings, and the RegistryNamingHonesty/Pattern
 * advisories) so "where the symptom fires" and "where the cause/guard looks" can
 * never drift apart.
 *
 * Because the write must target the class's OWN keyed array property, framework
 * `register()` hooks (a Laravel `ServiceProvider::register()` binds via
 * `$this->app->singleton(...)`, not `$this->store[$k] = …`) do not match; a
 * `*ServiceProvider` base is excluded outright as a belt-and-suspenders guard.
 */
final class RegistryShape
{
    /** Keyed-store WRITE methods on a wrapper property (`$this->store->put($k, $v)`) — need >= 2 args (key, value). */
    private const STORE_WRITE_METHODS = ['put', 'set', 'offsetSet'];

    /** Keyed-store READ methods on a wrapper property (`$this->store->get($k)`) — need >= 1 arg (key). */
    private const STORE_READ_METHODS = ['get', 'offsetGet'];

    /**
     * @param  list<string>  $storeProps  property names that are both publicly written as a keyed store and read back
     */
    private function __construct(
        private readonly array $storeProps,
    ) {}

    public static function detect(Node\Stmt\Class_ $class): ?self
    {
        if ($class->extends instanceof Node\Name && str_ends_with($class->extends->getLast(), 'ServiceProvider')) {
            return null;
        }

        $finder = new NodeFinder;

        // A TTL/cache evicts on read (a getter that reads a value by key AND unsets
        // that same store) — its lookups are time-dependent, not a stable registry.
        if (self::hasEvictionOnRead($class, $finder)) {
            return null;
        }

        $notLookups = self::nonLookupFetches($class->stmts, $finder);

        // "You put things in": a public, non-static method writes a keyed store —
        // `$this->P[$k] = …`, a wrapper write `$this->P->put($k, $v)`, a merge-rebind
        // `$this->P = array_merge($this->P, …)`, or a copy-write into a fresh instance.
        $publicWritten = [];

        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic() || $method->stmts === null) {
                continue;
            }

            foreach (self::writtenStoreProps($method->stmts, $finder) as $prop) {
                $publicWritten[$prop] = true;
            }
        }

        if ($publicWritten === []) {
            return null;
        }

        // "You look things up": a value read of the same prop by key — `$this->P[$k]`
        // or a wrapper read `$this->P->get($k)`.
        $read = self::readStoreProps($class->stmts, $finder, $notLookups);

        $store = array_values(array_keys(array_intersect_key($publicWritten, $read)));

        return $store === [] ? null : new self($store);
    }

    /**
     * Store properties a method writes by key — covering every shape a registry's
     * "put" takes, derived from the AST: an array-dim assign (`$this->P[$k] = …`),
     * a wrapper method (`$this->P->put/set/offsetSet($k, $v)`, the receiver chain
     * rooted at `$this->P` so a copy-write `$this->P->toBase()->put($k, $v)` counts),
     * or a self-incorporating rebind (`$this->P = array_merge($this->P, …)` / `+=`).
     *
     * @param  array<Node>  $stmts
     * @return list<string>
     */
    private static function writtenStoreProps(array $stmts, NodeFinder $finder): array
    {
        $props = [];

        foreach ($finder->findInstanceOf($stmts, Expr\Assign::class) as $assign) {
            // A keyed array-dim write that stores a GIVEN value — `$this->P[$k] = $value`.
            // An append (`$this->P[] = …`, a list/log) or a CONSTRUCTED/transformed value
            // (`= new Step(…)`, `= trim($body)`) is not a registry's "put what I'm handed".
            if ($assign->var instanceof Expr\ArrayDimFetch && $assign->var->dim !== null) {
                $prop = self::thisProp($assign->var);

                if ($prop !== null && self::isGivenValue($assign->expr)) {
                    $props[$prop] = true;
                }

                continue;
            }

            $prop = self::rebindProp($assign, $finder);

            if ($prop !== null) {
                $props[$prop] = true;
            }
        }

        foreach ($finder->findInstanceOf($stmts, Expr\AssignOp\Plus::class) as $op) {
            $prop = self::thisPropName($op->var);

            if ($prop !== null) {
                $props[$prop] = true;
            }
        }

        foreach ($finder->findInstanceOf($stmts, Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier
                && in_array($call->name->toString(), self::STORE_WRITE_METHODS, true)
                && count($call->args) >= 2
                && $call->args[1] instanceof Node\Arg
                && self::isGivenValue($call->args[1]->value)
            ) {
                $prop = self::receiverRootProp($call->var);

                if ($prop !== null) {
                    $props[$prop] = true;
                }
            }
        }

        return array_keys($props);
    }

    /**
     * Store properties read by a keyed value lookup — `$this->P[$k]` (not an
     * assignment target / isset / unset) or a wrapper read `$this->P->get($k)`.
     *
     * @param  array<Node>  $stmts
     * @return array<string, true>
     */
    private static function readStoreProps(array $stmts, NodeFinder $finder, SplObjectStorage $notLookups): array
    {
        $props = [];

        foreach ($finder->findInstanceOf($stmts, Expr\ArrayDimFetch::class) as $dim) {
            if ($notLookups->offsetExists($dim)) {
                continue;
            }

            $prop = self::thisProp($dim);

            if ($prop !== null) {
                $props[$prop] = true;
            }
        }

        foreach ($finder->findInstanceOf($stmts, Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier
                && in_array($call->name->toString(), self::STORE_READ_METHODS, true)
                && count($call->args) >= 1
            ) {
                $prop = self::receiverRootProp($call->var);

                if ($prop !== null) {
                    $props[$prop] = true;
                }
            }
        }

        return $props;
    }

    /**
     * Whether any method evicts on read — reads a value by key from a store AND
     * unsets that same store (the TTL-cache tell). A registry's lookup is pure.
     */
    private static function hasEvictionOnRead(Node\Stmt\Class_ $class, NodeFinder $finder): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $read = self::readStoreProps($method->stmts, $finder, self::nonLookupFetches($method->stmts, $finder));
            $unset = [];

            foreach ($finder->findInstanceOf($method->stmts, Node\Stmt\Unset_::class) as $unsetStmt) {
                foreach ($unsetStmt->vars as $var) {
                    $prop = self::thisProp($var);

                    if ($prop !== null) {
                        $unset[$prop] = true;
                    }
                }
            }

            if (array_intersect_key($read, $unset) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $value is a value the method STORES AS GIVEN — a plain variable (a
     * parameter or a local holding one). A `new X(…)`, a method/func call, a
     * concatenation, or a literal is a CONSTRUCTED value: the class is building or
     * transforming, the hallmark of a builder/logger/timeline, not a registry that
     * keeps what it is handed.
     */
    private static function isGivenValue(Node $value): bool
    {
        return $value instanceof Expr\Variable;
    }

    /**
     * The prop of a self-incorporating rebind `$this->P = <expr mentioning $this->P>`
     * (e.g. `array_merge($this->P, …)`, `[...$this->P, $k => $v]`) — a keyed grow of
     * P, not a reset. Null when it isn't one.
     */
    private static function rebindProp(Expr\Assign $assign, NodeFinder $finder): ?string
    {
        $prop = self::thisPropName($assign->var);

        if ($prop === null) {
            return null;
        }

        foreach ($finder->findInstanceOf([$assign->expr], Expr\PropertyFetch::class) as $fetch) {
            if (self::thisPropName($fetch) === $prop) {
                return $prop;
            }
        }

        return null;
    }

    /**
     * The `$this->P` a method-call/property chain is ultimately rooted at, so a
     * wrapper write/read (`$this->store->get(...)`, `$this->tags->toBase()->put(...)`)
     * is attributed to its store property. Null when the chain isn't rooted at `$this`.
     */
    private static function receiverRootProp(Expr $expr): ?string
    {
        $cursor = $expr;

        while ($cursor instanceof Expr\MethodCall || $cursor instanceof Expr\PropertyFetch) {
            $prop = self::thisPropName($cursor);

            if ($prop !== null) {
                return $prop;
            }

            $cursor = $cursor->var;
        }

        return null;
    }

    /**
     * The property name of a `$this->P` property fetch, or null.
     */
    private static function thisPropName(Node $node): ?string
    {
        if ($node instanceof Expr\PropertyFetch
            && $node->var instanceof Expr\Variable
            && $node->var->name === 'this'
            && $node->name instanceof Node\Identifier
        ) {
            return $node->name->toString();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function storeProperties(): array
    {
        return $this->storeProps;
    }

    /**
     * Whether this method reads the keyed store in a lookup (non-write) position
     * — i.e. it is a getter over the registry's contents.
     */
    public function readsStore(Node\Stmt\ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        $finder = new NodeFinder;
        $read = self::readStoreProps($method->stmts, $finder, self::nonLookupFetches($method->stmts, $finder));

        foreach (array_keys($read) as $prop) {
            if (in_array($prop, $this->storeProps, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `$this->store[$k]` fetches in $stmts that are NOT value lookups, so they
     * can be excluded when deciding whether a method/class reads the store: the LHS
     * of a plain keyed write (`$this->store[$k] = …`), and the operand of a
     * key-existence test (`isset()`) or removal (`unset()`). Retrieving a value by
     * key is a lookup; writing, testing, or removing membership is not.
     *
     * (A populate-on-miss `$this->store[$k] ??= …` is deliberately NOT excluded —
     * its read-then-maybe-write LHS is the lookup signal that distinguishes a
     * store/memo from a write-only collection.)
     *
     * @param  array<Node>  $stmts
     */
    private static function nonLookupFetches(array $stmts, NodeFinder $finder): SplObjectStorage
    {
        $excluded = new SplObjectStorage;

        foreach ($finder->findInstanceOf($stmts, Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Expr\ArrayDimFetch) {
                $excluded->offsetSet($assign->var, true);
            }
        }

        foreach ($finder->findInstanceOf($stmts, Expr\Isset_::class) as $isset) {
            foreach ($isset->vars as $var) {
                if ($var instanceof Expr\ArrayDimFetch) {
                    $excluded->offsetSet($var, true);
                }
            }
        }

        foreach ($finder->findInstanceOf($stmts, Node\Stmt\Unset_::class) as $unset) {
            foreach ($unset->vars as $var) {
                if ($var instanceof Expr\ArrayDimFetch) {
                    $excluded->offsetSet($var, true);
                }
            }
        }

        return $excluded;
    }

    /**
     * The store property name a `$this->prop[...]` dim-fetch reads/writes, or
     * null when the node isn't a fetch off a `$this->` property.
     */
    private static function thisProp(Node $node): ?string
    {
        if (! $node instanceof Expr\ArrayDimFetch) {
            return null;
        }

        $var = $node->var;

        if ($var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier
        ) {
            return $var->name->toString();
        }

        return null;
    }
}
