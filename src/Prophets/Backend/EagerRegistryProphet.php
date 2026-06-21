<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * A registry is **eagerly hydrated, read-only**: its store is filled once up front —
 * at boot, or once the app has booted if needed (the constructor / mutators, called
 * from a service provider) — and its lookups are dumb
 * reads. Flag a registry whose LOOKUP method WRITES or BUILDS the backing store —
 * lazy hydration (`$this->items ??= $this->build()`) or populate-on-miss
 * (`$this->items[$k] ??= $this->make($k)`). A class that builds/memoises on read is a
 * cache or factory wearing a registry's name, not a registry.
 *
 * Decisive AST signal: the store is written INSIDE a read method. Role via the
 * `Registry` CLAIM (a base/name ending `Registry`, a `Registry` interface, or a
 * `#[Registry]` attribute) — NOT the bare store shape, so an honestly-named
 * `*Cache`/`*Resolver` that lazy-loads is left alone. ADVISORY (a WARNING). GENERIC.
 * Pairs with the `registry` skill's hydration doctrine.
 */
#[IntroducedIn('2.26.0')]
class EagerRegistryProphet extends PhpCommandment
{
    private const LOOKUP_NAMES = ['all', 'get', 'find', 'has', 'first', 'keys', 'values', 'only', 'map'];

    private const LOOKUP_PREFIXES = ['get', 'find', 'has', 'for', 'resolve', 'lookup'];

    private const MUTATORS = ['register', 'registermany', 'add', 'put', 'push', 'set', 'unregister', 'remove', 'forget', 'flush', 'boot', '__construct'];

    /** Self-method calls inside a lookup that mutate the store. */
    private const MUTATOR_CALLS = ['register', 'registermany', 'add', 'put', 'push', 'set'];

    public function description(): string
    {
        return 'A registry is eagerly hydrated + read-only — lookups must not lazily build or populate-on-miss';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A registry (the register/look-up shape, or a `Registry` base/name/attribute) '
                . 'has a LOOKUP method (`all`/`get`/`find`/`has`/a by-key getter) that WRITES '
                . 'or BUILDS the backing store — `$this->items ??= $this->build()` (lazy '
                . 'hydration) or `$this->items[$k] ??= $this->make($k)` (populate-on-miss).'
            )
            ->leaveWhen(
                'the store is written ONLY in the constructor or mutators (register/add); the '
                . 'class is a genuine CACHE/factory that does not claim the registry role or '
                . 'name (name it `*Cache`/`*Factory`); or the build/discovery is a separate '
                . 'collaborator the boot path calls, not the lookup.'
            )
            ->whenUnsure(
                'hydrate the registry once up front — preferably at boot, or once the app '
                . 'has booted if needed (a service provider calls '
                . '`registerMany($discovered)`) — and make lookups dumb reads (`return '
                . '$this->items`, resolve-or-throw). Move discovery/reflection into a '
                . '`*Discovery`/`*Reflector` collaborator. See the registry skill (hydration).'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A registry maps a key to a registered value. It is hydrated ONCE up front — at boot,
or just after the app has booted if needed (a service provider calls register()/
registerMany()) — and read-only thereafter — lookups never
mutate or build the store. A "registry" that builds on first read, or creates+caches
entries on a miss, is a cache or factory in disguise.

Bad — the lookup builds the store on first access:
    public function all(): array {
        return $this->items ??= $this->discoverAndBuild();   // reflection/discovery on READ
    }

Bad — populate-on-miss:
    public function for(string $key): Thing {
        return $this->items[$key] ??= $this->make($key);     // creates+caches on read
    }

Good — hydrate eagerly up front, lookups are dumb reads:
    // ServiceProvider::boot(): $registry->registerMany($discovery->scan());
    public function all(): array { return $this->items; }

WHAT FIRES — a registry-role class whose lookup method (`all`/`get`/`find`/`has`/a
by-key getter) writes the store (`$this->p = …`, `[$k] =`, `??=`), calls a self
mutator (`$this->register(...)`), or builds/discovers (a `build*`/`discover*` call,
`Discover::…`, reflection).

WHAT DOES NOT — the store is written only in the ctor/mutators; a class that does not
claim the registry role/name; discovery in a separate collaborator. Advisory (a
WARNING); not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $this->isRegistry($class)) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                if (! $this->isLookup($method)) {
                    continue;
                }

                $reason = $this->mutatesStore($method, $finder);

                if ($reason !== null) {
                    $warnings[] = $this->warningAt(
                        $method->getStartLine(),
                        sprintf(
                            'Registry lookup `%s()` %s — a registry is eagerly hydrated and read-only, so this is lazy hydration / populate-on-miss (a cache/factory wearing a registry\'s name). Hydrate the store once up front — at boot, or after the app has booted if needed (a service provider calling registerMany()) — make lookups dumb reads, and move any discovery/reflection into a separate collaborator.',
                            $method->name->toString(),
                            $reason,
                        ),
                        null,
                        'eager-registry:' . strtolower($method->name->toString()),
                    );
                }
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function isRegistry(Node\Stmt\Class_ $class): bool
    {
        if ($class->extends instanceof Node\Name && str_ends_with($class->extends->getLast(), 'ServiceProvider')) {
            return false;
        }

        if ($class->extends instanceof Node\Name && str_ends_with($class->extends->getLast(), 'Registry')) {
            return true;
        }

        if ($class->name instanceof Node\Identifier && str_ends_with($class->name->toString(), 'Registry')) {
            return true;
        }

        foreach ($class->implements as $interface) {
            if (str_ends_with($interface->getLast(), 'Registry')) {
                return true;
            }
        }

        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name->getLast() === 'Registry') {
                    return true;
                }
            }
        }

        // Gate on the registry CLAIM (name/marker), NOT the bare store shape: a class
        // honestly named *Cache / *Resolver / *Tracker that lazy-loads is fine — it does
        // not claim to be a boot-hydrated registry. (RegistryNamingHonesty handles the
        // naming question; this prophet enforces the doctrine on things that claim it.)
        return false;
    }

    private function isLookup(Node\Stmt\ClassMethod $method): bool
    {
        if (! $method->isPublic() || $method->isStatic() || $method->stmts === null) {
            return false;
        }

        $name = strtolower($method->name->toString());

        if (in_array($name, self::MUTATORS, true)) {
            return false;
        }

        if (in_array($name, self::LOOKUP_NAMES, true)) {
            return true;
        }

        foreach (self::LOOKUP_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** A short reason if the lookup writes/builds the store, else null. */
    private function mutatesStore(Node\Stmt\ClassMethod $method, NodeFinder $finder): ?string
    {
        foreach ($finder->find((array) $method->stmts, fn (Node $n) => $n instanceof Expr\Assign || $n instanceof Expr\AssignOp || $n instanceof Expr\AssignRef) as $assign) {
            /** @var Expr\Assign|Expr\AssignOp|Expr\AssignRef $assign */
            if ($this->isThisPropertyTarget($assign->var)) {
                return 'writes the backing store ($this->… =)';
            }
        }

        foreach ($finder->findInstanceOf((array) $method->stmts, Expr\MethodCall::class) as $call) {
            if (! $call->var instanceof Expr\Variable || $call->var->name !== 'this' || ! $call->name instanceof Node\Identifier) {
                continue;
            }

            $callee = strtolower($call->name->toString());

            if (in_array($callee, self::MUTATOR_CALLS, true)) {
                return 'calls a mutator ($this->' . $call->name->toString() . '())';
            }

            if (preg_match('/^(build|discover|load|hydrate|scan|populate|boot|init)/', $callee)) {
                return 'builds/discovers on read ($this->' . $call->name->toString() . '())';
            }
        }

        foreach ($finder->findInstanceOf((array) $method->stmts, Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name && $call->class->getLast() === 'Discover') {
                return 'discovers on read (Discover::…)';
            }
        }

        foreach ($finder->findInstanceOf((array) $method->stmts, Expr\New_::class) as $new) {
            if ($new->class instanceof Node\Name && str_starts_with($new->class->getLast(), 'Reflection')) {
                return 'reflects on read (new Reflection…)';
            }
        }

        return null;
    }

    private function isThisPropertyTarget(Node $var): bool
    {
        // $this->prop[...] = …
        if ($var instanceof Expr\ArrayDimFetch) {
            $var = $var->var;
        }

        return $var instanceof Expr\PropertyFetch
            && $var->var instanceof Expr\Variable
            && $var->var->name === 'this'
            && $var->name instanceof Node\Identifier;
    }
}
