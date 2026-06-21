<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use SplObjectStorage;

/**
 * Name-free AST detector for the "set shape": an unkeyed, iterate-only collection
 * — a class you ADD items into and only ever read in BULK (iterate / `values()` /
 * `all()`), with a membership TEST (`isset`/`has`) but NO keyed VALUE lookup
 * (`return $this->store[$key]`). The Set counterpart of {@see RegistryShape}: a
 * Registry answers "the value FOR this key"; a Set answers "is this item IN, and
 * what is in it".
 *
 * Concretely a property qualifies as a set store when a public method WRITES it
 * either by append (`$this->store[] = …`) or by a keyed/dedup write
 * (`$this->store[$item::class] = $item` / `??=`), the property is read in BULK
 * somewhere (`return $this->store` / `foreach` / `array_values($this->store)` /
 * `count(...)`), and it is NEVER read by key as a value lookup. The moment a keyed
 * value read exists the class is a Registry (or a memo), not a Set — so a
 * registry-shaped class ({@see RegistryShape}) is excluded outright, keeping the
 * two shapes mutually exclusive.
 *
 * Shared by the Set-aware rules (SetNamingHonesty and, structurally, the
 * Set archetype) so "what is a set" is defined in exactly one place.
 */
final class SetShape
{
    /**
     * @param  list<string>  $storeProps  collection properties added-to and iterated, with no keyed value lookup
     */
    private function __construct(
        private readonly array $storeProps,
    ) {}

    public static function detect(Node\Stmt\Class_ $class): ?self
    {
        if ($class->extends instanceof Node\Name && str_ends_with($class->extends->getLast(), 'ServiceProvider')) {
            return null;
        }

        // A keyed VALUE lookup makes it a Registry, not a Set — keep the shapes
        // mutually exclusive so nothing is both.
        if (RegistryShape::detect($class) !== null) {
            return null;
        }

        $finder = new NodeFinder;

        // "You add things in": a public, non-static method appends (`$this->p[] = …`)
        // or keys/dedups (`$this->p[$k] = …` / `??=`) into a collection property.
        $written = [];

        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic() || $method->stmts === null) {
                continue;
            }

            $params = self::paramNames($method);

            foreach ($finder->find($method->stmts, static fn (Node $n): bool => $n instanceof Expr\Assign || $n instanceof Expr\AssignOp\Coalesce) as $assign) {
                /** @var Expr\Assign|Expr\AssignOp\Coalesce $assign */
                if (! $assign->var instanceof Expr\ArrayDimFetch) {
                    continue;
                }

                $prop = self::thisProp($assign->var);

                if ($prop === null) {
                    continue;
                }

                // REGISTRATION STORE, not a set: the key is an external method
                // PARAMETER (`register($key, $value)` → `$this->store[$key] = …`).
                // That is a keyed store looked up by a lookup key, not a Set keyed
                // by an item's own identity (`$this->items[$item::class] = $item`,
                // where the key is derived from the value). Bail out.
                $dim = $assign->var->dim;

                if ($dim instanceof Expr\Variable && is_string($dim->name) && isset($params[$dim->name])) {
                    return null;
                }

                $written[$prop] = true;
            }
        }

        if ($written === []) {
            return null;
        }

        $keyedRead = self::keyedValueReadProps($class->stmts, $finder);
        $bulkRead = self::bulkReadProps($class->stmts, $finder);

        $store = [];

        foreach (array_keys($written) as $prop) {
            // A keyed value lookup disqualifies it (Registry/memo); a property never
            // read in bulk is a write-only sink, not an iterate-only collection.
            if (! isset($keyedRead[$prop]) && isset($bulkRead[$prop])) {
                $store[] = $prop;
            }
        }

        return $store === [] ? null : new self($store);
    }

    /**
     * @return list<string>
     */
    public function storeProperties(): array
    {
        return $this->storeProps;
    }

    /**
     * The set store properties read BY KEY as a VALUE lookup (`$this->p[$k]`) — the
     * registry signal a Set must not have. Excludes write LHS (`= / ??= / =&`) and
     * key-existence tests (`isset`/`unset`), which are not value lookups.
     *
     * @param  array<Node>  $stmts
     * @return array<string, true>
     */
    private static function keyedValueReadProps(array $stmts, NodeFinder $finder): array
    {
        $excluded = self::nonLookupFetches($stmts, $finder);
        $reads = [];

        foreach ($finder->findInstanceOf($stmts, Expr\ArrayDimFetch::class) as $dim) {
            if ($excluded->offsetExists($dim)) {
                continue;
            }

            $prop = self::thisProp($dim);

            if ($prop !== null) {
                $reads[$prop] = true;
            }
        }

        return $reads;
    }

    /**
     * Properties read in BULK — a whole-property `$this->p` access that is neither
     * the base of a keyed `$this->p[$k]` fetch nor an assignment target: the
     * iterate surface (`return $this->p`, `foreach ($this->p …)`,
     * `array_values($this->p)`, `count($this->p)`).
     *
     * @param  array<Node>  $stmts
     * @return array<string, true>
     */
    private static function bulkReadProps(array $stmts, NodeFinder $finder): array
    {
        $keyedBases = new SplObjectStorage;

        foreach ($finder->findInstanceOf($stmts, Expr\ArrayDimFetch::class) as $dim) {
            if ($dim->var instanceof Expr\PropertyFetch) {
                $keyedBases->offsetSet($dim->var, true);
            }
        }

        $assignTargets = new SplObjectStorage;

        foreach ($finder->find($stmts, static fn (Node $n): bool => $n instanceof Expr\Assign || $n instanceof Expr\AssignOp || $n instanceof Expr\AssignRef) as $assign) {
            /** @var Expr\Assign|Expr\AssignOp|Expr\AssignRef $assign */
            if ($assign->var instanceof Expr\PropertyFetch) {
                $assignTargets->offsetSet($assign->var, true);
            }
        }

        $bulk = [];

        foreach ($finder->findInstanceOf($stmts, Expr\PropertyFetch::class) as $fetch) {
            if ($keyedBases->offsetExists($fetch) || $assignTargets->offsetExists($fetch)) {
                continue;
            }

            $prop = self::propName($fetch);

            if ($prop !== null) {
                $bulk[$prop] = true;
            }
        }

        return $bulk;
    }

    /**
     * The `$this->store[$k]` fetches that are NOT value lookups: write LHS and
     * key-existence (`isset`) / removal (`unset`) operands.
     *
     * @param  array<Node>  $stmts
     */
    private static function nonLookupFetches(array $stmts, NodeFinder $finder): SplObjectStorage
    {
        $excluded = new SplObjectStorage;

        foreach ($finder->find($stmts, static fn (Node $n): bool => $n instanceof Expr\Assign || $n instanceof Expr\AssignOp || $n instanceof Expr\AssignRef) as $assign) {
            /** @var Expr\Assign|Expr\AssignOp|Expr\AssignRef $assign */
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
     * The property name a `$this->prop[...]` dim-fetch reads/writes, or null when
     * the node isn't a fetch off a `$this->` property.
     */
    private static function thisProp(Expr\ArrayDimFetch $node): ?string
    {
        return self::propName($node->var);
    }

    /**
     * The names of a method's parameters (for spotting a register-by-key write).
     *
     * @return array<string, true>
     */
    private static function paramNames(Node\Stmt\ClassMethod $method): array
    {
        $names = [];

        foreach ($method->params as $param) {
            if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                $names[$param->var->name] = true;
            }
        }

        return $names;
    }

    /** The `prop` of a `$this->prop` property-fetch, or null. */
    private static function propName(Node $node): ?string
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
}
