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

        // Assignment targets, so a write `$this->store[$k] = …` isn't also
        // mistaken for a lookup read of the same node.
        $writeTargets = new SplObjectStorage;

        foreach ($finder->findInstanceOf($class->stmts, Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Expr\ArrayDimFetch) {
                $writeTargets->offsetSet($assign->var, true);
            }
        }

        // "You put things in": a public, non-static method writes `$this->P[$k] = …`.
        $publicWritten = [];

        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic() || $method->isStatic() || $method->stmts === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Expr\Assign::class) as $assign) {
                $prop = self::thisProp($assign->var);

                if ($prop !== null) {
                    $publicWritten[$prop] = true;
                }
            }
        }

        if ($publicWritten === []) {
            return null;
        }

        // "You look things up": a non-write read `$this->P[$k]` of the same prop.
        $read = [];

        foreach ($finder->findInstanceOf($class->stmts, Expr\ArrayDimFetch::class) as $dim) {
            if ($writeTargets->offsetExists($dim)) {
                continue;
            }

            $prop = self::thisProp($dim);

            if ($prop !== null) {
                $read[$prop] = true;
            }
        }

        $store = array_values(array_keys(array_intersect_key($publicWritten, $read)));

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
     * Whether this method reads the keyed store in a lookup (non-write) position
     * — i.e. it is a getter over the registry's contents.
     */
    public function readsStore(Node\Stmt\ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        $finder = new NodeFinder;
        $writeTargets = new SplObjectStorage;

        foreach ($finder->findInstanceOf($method->stmts, Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Expr\ArrayDimFetch) {
                $writeTargets->offsetSet($assign->var, true);
            }
        }

        foreach ($finder->findInstanceOf($method->stmts, Expr\ArrayDimFetch::class) as $dim) {
            if ($writeTargets->offsetExists($dim)) {
                continue;
            }

            $prop = self::thisProp($dim);

            if ($prop !== null && in_array($prop, $this->storeProps, true)) {
                return true;
            }
        }

        return false;
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
