<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\SetShape;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Naming honesty for set-shaped classes — the unkeyed-collection sibling of
 * {@see RegistryNamingHonestyProphet}. A class you ADD items into and only ever
 * ITERATE (no keyed `get(string)` value lookup) is a Set, not a Registry: it
 * answers membership (`has`) and iteration (`all`/`values`), never "the value for
 * this key". This advisory surfaces the gap — a class with the set SHAPE
 * ({@see SetShape}) whose name does not advertise it (`*Set`/`*Collection`) and
 * which carries no `Set` marker — so it gets an honest name and the marker-driven
 * {@see SetReturnContractProphet} can enforce its contract.
 *
 * Advisory, never a sin — renaming ripples across call sites. Emit guidance only.
 */
#[IntroducedIn('2.28.0')]
class SetNamingHonestyProphet extends PhpCommandment
{
    private const DEFAULT_SUFFIXES = ['Set', 'Collection'];

    public function description(): string
    {
        return 'A class shaped like a set (add + iterate, no keyed lookup) should be named *Set and extend a base';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A class has the set shape — you `add`/append into a collection AND only read it in bulk (`all`/`values`/iterate), with a membership test (`has`/`isset`) but NO keyed `get(string $key)` value lookup — yet its name does not end in `*Set`/`*Collection` and it carries no `Set` marker. It is an unkeyed, iterate-only collection wearing a vaguer name.')
            ->leaveWhen('the class actually answers a keyed VALUE lookup (`get(string): T`) — that is a `*Registry`, not a set (RegistryNamingHonesty covers it); it derives values on demand without owning a collection (a `*Resolver`/`*Factory`); or the domain language already has a settled name for it.')
            ->whenUnsure('ask what it DOES: you `add` items + iterate them + test membership, with NO keyed value lookup → it is a `*Set` (extend the shared base; `commandments:scaffold` can generate one, after which SetReturnContract enforces the total membership surface). If you look entries up BY KEY, it is a `*Registry` instead.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A Set is an unkeyed, iterate-only collection: you `add()` items and only ever
ITERATE them (`all()`/`values()`/foreach) and TEST membership (`has()`). It is
the sibling of a Registry — but a Registry answers "the value FOR this key"
(keyed lookup), while a Set answers "is this IN, and what is in it" (membership +
iteration). Name it `*Set` when ALL hold:

  1. You `add()` / append into it — you put things in.
  2. It owns a collection of those things, read in BULK.
  3. There is NO keyed `get(string $key): T` value lookup. ← the tell vs a Registry

A set-shaped class with a vaguer name hides its contract: the reader can't tell
whether to look up by key (they can't — there's no keyed get) and the
marker-driven SetReturnContract rule can't see it to enforce the total surface.

Honest names for the near misses:
  - you look entries up BY KEY (`get(string): T`)   → `*Registry` (not a set)
  - computes/derives on demand, owns no collection   → `*Resolver` / `*Factory`

The fix:
  - rename to `*Set` (or `*Collection`) so the contract is legible;
  - extend a shared base — `commandments:scaffold` generates a `Set` base
    (`add`/`has`/`all`/`values`/`remove`), the idiomatic "one abstract base, N
    concrete sets" shape.

Once marked (extends a `Set` base / `#[Set]` / a `Set` interface),
SetReturnContract enforces the full contract — `has(): bool`, total iteration, no
`Option`/`?T` leak, and no foreign keyed `get(string)`.

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

            if ($this->nameAdvertisesSet($class) || $this->isMarked($class)) {
                continue;
            }

            if (SetShape::detect($class) === null) {
                continue;
            }

            $name = $class->name->toString();
            $warnings[] = $this->warningAt(
                $class->getStartLine(),
                sprintf('%s is shaped like a set — you `add`/append into a collection, then only iterate it (`all`/`values`) and test membership, with no keyed `get(string)` lookup — but it is not named `*Set`/`*Collection` and carries no `Set` marker. Name it `*Set` and extend a shared base (`commandments:scaffold` can generate one), after which SetReturnContract enforces its total membership surface. (If you DO look entries up by key, it is a `*Registry` instead.)', $name),
                $this->lineAt($content, $class->getStartLine()),
                'set-naming:' . $name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function nameAdvertisesSet(Node\Stmt\Class_ $class): bool
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
                if ($attr->name->getLast() === 'Set') {
                    return true;
                }
            }
        }

        foreach ($class->implements as $interface) {
            if (str_ends_with($interface->getLast(), 'Set')) {
                return true;
            }
        }

        return $class->extends instanceof Node\Name && str_ends_with($class->extends->getLast(), 'Set');
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

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
