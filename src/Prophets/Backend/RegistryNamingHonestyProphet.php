<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\RegistryShape;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Naming honesty for registry-shaped classes (the inverse-direction sibling of
 * {@see ResolverNamingHonestyProphet}). A class you PUT things into and LOOK
 * things up from is a Registry — and the marker-driven
 * {@see RegistryReturnContractProphet} can only enforce its return contract once
 * the class is named/marked as one. This advisory surfaces the gap: a class with
 * the registry SHAPE (a public keyed-store write + a lookup) whose name does not
 * advertise it (`*Registry`/`*Map`/`*Catalog`) and which carries no `Registry`
 * marker — name it, and extend a shared base, so the contract gets enforced.
 *
 * Advisory, never a sin — renaming ripples across call sites and the domain's
 * ubiquitous language sometimes wins. Emit guidance only.
 *
 *
 *
 * @method-generated-start
 * @method static suffixes(array $value)
 * @method-generated-end
 */
#[IntroducedIn('2.0.0')]
class RegistryNamingHonestyProphet extends PhpCommandment
{
    private const DEFAULT_SUFFIXES = ['Registry', 'Map', 'Catalog'];

    public function description(): string
    {
        return 'A class shaped like a registry (register + keyed store + lookup) should be named *Registry and extend a base';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A class has the registry shape — you `register`/`add`/`put` into a keyed store AND look entries up (`find`/`has`/`get`) — but its name does not end in `Registry`/`Map`/`Catalog` and it carries no `Registry` marker. The "you put things in" write is the dead giveaway it owns a keyspace.')
            ->leaveWhen('the class only DERIVES values on demand without owning a store (a `*Resolver`/`*Factory`), the absence it answers is genuinely optional and per-entry (not a must-exist lookup), or the domain language already has a settled name for it.')
            ->whenUnsure('ask what it DOES: you put things in + own a keyed store + answer membership/lookup → it is a `*Registry` (extend the shared base; `commandments:scaffold` can generate one, after which RegistryReturnContract enforces return-or-throw). Owns a store but nothing is registered into it → `*Map`/`*Catalog`. Computes on demand with no store → `*Resolver`/`*Factory`.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A Registry is a class you PUT things into and LOOK things up from. Name it
`*Registry` when ALL THREE hold — and the first is the dead giveaway:

  1. You `register()` / `add()` / `put()` into it — you put things in. ← the tell
  2. It owns a keyed store of those things.
  3. It answers membership / lookup over them — the trio `find()` (→ maybe),
     `has()` (→ bool), `get()` (→ the item, or throws).

A class with the shape but a misleading name hides its contract: the reader
expects a service/resolver and finds a keyspace owner, and the marker-driven
RegistryReturnContract rule can't see it to enforce return-or-throw.

Honest names for the near misses:
  - owns a store but nothing is *registered* into it  → `*Map` / `*Catalog`
  - computes/derives on demand, owns no store          → `*Resolver` / `*Factory`
  - reads one object's fields                          → `*Reader` / `*Accessor`

The fix:
  - rename to `*Registry` so the contract is legible;
  - extend a shared base — `commandments:scaffold` generates a `Registry` base
    (`register`/`registerMany`/`has(): bool`/`get(): T` throws/`all`/`values`) into your support
    namespace, the idiomatic "one abstract base, N concrete registries" shape.

MARKER ASYMMETRY (worth knowing before you mark it): once a class IS marked
(extends a `Registry` base / `#[Registry]` / a `Registry` interface),
RegistryReturnContract enforces the FULL contract — including Option-returning
getters. An UNMARKED, shape-detected class is only nudged on raw `?T` getters
(an Option getter is read as a deliberate genuine-absence opt-in and left alone).
So marking it is the opt-in to strict enforcement.

Advisory — renaming is a cross-repo refactor; weigh it. Not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || $class->isAbstract()) {
                continue;
            }

            // Already advertises / is marked as a registry → honest, leave it.
            if ($this->nameAdvertisesRegistry($class) || $this->isMarked($class)) {
                continue;
            }

            if (RegistryShape::detect($class) === null) {
                continue;
            }

            $name = $class->name->toString();
            $warnings[] = $this->warningAt(
                $class->getStartLine(),
                sprintf('%s is shaped like a registry — you `register`/store into a keyed property, then look entries up — but it is not named `*Registry`/`*Map`/`*Catalog` and carries no `Registry` marker. Name it `*Registry` and extend a shared base (`commandments:scaffold` can generate one), after which RegistryReturnContract enforces its return contract (return T or throw, with a `has()` companion).', $name),
                $this->lineSnippet($content, $class->getStartLine()),
                'registry-naming:' . $name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function nameAdvertisesRegistry(Node\Stmt\Class_ $class): bool
    {
        $name = $class->name?->toString() ?? '';

        foreach ($this->suffixes() as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isMarked(Node\Stmt\Class_ $class): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name->getLast() === 'Registry') {
                    return true;
                }
            }
        }

        foreach ($class->implements as $interface) {
            if (str_ends_with($interface->getLast(), 'Registry')) {
                return true;
            }
        }

        return $class->extends instanceof Node\Name && str_ends_with($class->extends->getLast(), 'Registry');
    }

    /**
     * @return list<string>
     */
    private function suffixes(): array
    {
        $configured = $this->config('suffixes', self::DEFAULT_SUFFIXES);

        return is_array($configured) && $configured !== []
            ? array_values(array_map('strval', $configured))
            : self::DEFAULT_SUFFIXES;
    }

}
