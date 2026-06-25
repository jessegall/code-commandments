<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Enforce the set contract on a class that opts in via a marker (a base class
 * named `Set` or extending one, an interface named `Set`, or a `#[Set]`
 * attribute) — the unkeyed-collection sibling of
 * {@see RegistryReturnContractProphet}.
 *
 * A Set is a TOTAL, iterate-only collection: `add()` items, `has(item): bool` for
 * membership, `all()`/`values()` to iterate. Two things are foreign to that
 * surface:
 *
 *   1. A keyed `get(string $key): T` VALUE lookup — if you need to look an entry
 *      up by key you wanted a Registry, not a Set.
 *   2. Handing absence across the boundary — an `Option<T>` or `?T` return from
 *      the iterate/membership surface. Membership is a `bool`; iteration is total.
 *
 * Marker-driven only (the author opted in), so false positives can't breed on the
 * heuristic shape; the shape path lives in {@see SetNamingHonestyProphet}.
 *
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static markers(array $value)
 * @method static optionClasses(array $value)
 * @method-generated-end
 */
#[IntroducedIn('2.28.0')]
class SetReturnContractProphet extends PhpCommandment
{
    private const DEFAULT_MARKERS = ['Set'];

    private const DEFAULT_OPTION_CLASSES = ['Option'];

    /** Getter names that ANNOUNCE nullability is normal — left even on a set. */
    private const FINDER_PREFIXES = ['find', 'search', 'try', 'lookup'];

    public function description(): string
    {
        return 'A set is a total, iterate-only collection — has(): bool, no Option/nullable leak, and no keyed get(string) lookup (that is a registry)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A class marked as a `Set` (a base class named `Set` or extending one, a `Set` interface, or a `#[Set]` attribute) either exposes a keyed `get(string $key): T` VALUE lookup (registry behaviour — a set has no keys) OR a PUBLIC method that hands absence across its boundary (`Option<T>` always, or a non-finder `?T`). A set is add + membership + iterate; it is total over what it holds.')
            ->leaveWhen('the method is a NULLABLE finder whose NAME announces value-or-nothing (`find*`/`search*`/`try*`/`lookup*`/`*OrNull`/`*OrDefault`), or the keyed accessor is genuinely a Registry — in which case mark/name the class `*Registry`, not `*Set`.')
            ->whenUnsure('keep sets simple: `add`, `has(item): bool`, `all()`/`values()` (total). If you are looking entries up BY KEY, it is a Registry — rename it and let RegistryReturnContract govern it. If you are handing an Option/nullable out so callers branch on a miss, that resolution belongs outside the set.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A Set is an unkeyed, iterate-only collection — you `add()` items, ask `has(item)`,
and iterate `all()`/`values()`. It is TOTAL over what it holds: there is no "the
value is missing" branch to push onto callers, and no key to look a value up by.

Two shapes are foreign to a marked `Set`:

Bad — a keyed value lookup (that is a Registry, not a Set):
    public function get(string $key): Node {     // keyed lookup → you wanted a Registry
        return $this->items[$key];
    }

Bad — leaking absence from the iterate/membership surface:
    public function first(): Option { … }        // a set is total; no Option out
    public function find(string $type): ?Node { … }  // a keyed maybe → Registry/finder

Good — add + membership + total iteration:
    public function add(Node $node): static { … }
    public function has(Node $node): bool { … }
    /** @return list<Node> */
    public function all(): array { … }

WHAT FIRES — on a class carrying the `Set` marker (a base class named `Set` or
extending one, a `Set` interface, or `#[Set]`): a PUBLIC keyed accessor
(`get(string $key)` / a `get*` getter taking a key) returning a non-bool value, OR
a PUBLIC method returning `Option<T>` (always) / `?T` (unless it is a named
finder).

WHAT DOES NOT — a `bool` `has()`/`contains()`/`is()`; an array/iterable
`all()`/`values()`; a NULLABLE finder by name (`find*`/`try*`/`*OrNull`); a
non-public method; an `Option` used only INTERNALLY. The marker is the opt-in, so
there is no guessing.

NOT auto-fixable — retyping a maybe-getter to throw or dropping a keyed accessor
changes runtime behaviour and the class's role. Resolve by hand: make membership a
`bool`, iteration total, and if you truly need keyed lookup, it is a Registry —
rename it.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $sins = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $this->isSet($class)) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $finding = $this->contractBreach($method);

                if ($finding === null) {
                    continue;
                }

                $name = $method->name->toString();
                $sins[] = $this->sinAt(
                    $method->getStartLine(),
                    $finding,
                    $this->lineSnippet($content, $method->getStartLine()),
                    null,
                    'set-return:' . $name,
                    false,
                );
            }
        }

        return $sins === [] ? $this->righteous() : Judgment::fallen($sins);
    }

    /** The contract-breach message for a method on a marked set, or null. */
    private function contractBreach(Node\Stmt\ClassMethod $method): ?string
    {
        if (! $method->isPublic() || $method->isStatic()) {
            return null;
        }

        $name = $method->name->toString();

        if (str_starts_with($name, '__')) {
            return null;
        }

        if ($this->isKeyedLookup($method)) {
            return sprintf('Set method %s() is a keyed value lookup (it takes a key and returns a value). A set has no keys — it answers membership (`has(item): bool`) and iteration (`all`/`values`). If you need to look entries up by key, this is a Registry, not a Set: rename it `*Registry` (and let RegistryReturnContract govern it).', $name);
        }

        $type = $method->returnType;

        if ($type instanceof Node\Name && in_array($type->getLast(), $this->optionClasses(), true)) {
            return sprintf('Set method %s() returns an Option. A set is a TOTAL, iterate-only collection — `add` / `has(): bool` / `all()`/`values()`. It must not hand an Option across its boundary for callers to unwrap. Renaming to `find*` does NOT help — an Option-returning method on a set is the breach.', $name);
        }

        if ($this->isFinderName($name)) {
            return null;
        }

        if ($this->isNullable($type)) {
            return sprintf('Set getter %s() returns a nullable. A set is total over what it holds — membership is `has(): bool`, iteration is `all()`/`values()`. If a miss is a genuine, handled outcome, this is a keyed finder and belongs on a Registry; otherwise drop the maybe.', $name);
        }

        return null;
    }

    /**
     * A keyed VALUE lookup: a `get`/`get*` getter that TAKES a key parameter and
     * returns a non-bool value. `has()`/`contains()` (bool membership) and a
     * no-arg `all()`/`values()` are not keyed lookups.
     */
    private function isKeyedLookup(Node\Stmt\ClassMethod $method): bool
    {
        $name = strtolower($method->name->toString());

        if ($name !== 'get' && ! str_starts_with($name, 'get')) {
            return false;
        }

        if ($method->params === []) {
            return false; // a no-arg getter is not a keyed lookup
        }

        $type = $method->returnType;
        $bare = $type instanceof Node\NullableType ? $type->type : $type;

        // A bool getter is a membership test, not a value lookup.
        return ! ($bare instanceof Node\Identifier && strtolower($bare->toString()) === 'bool');
    }

    private function isNullable(?Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return true;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the class opts into the set contract: the `*Set` name suffix, a base
     * class / interface whose short name ends in a marker, or a `#[Set]` attribute.
     * Suffix-inclusive (unlike the registry contract's base-only markers) because
     * `*Set` is an unambiguous, settled name for the unkeyed collection.
     */
    private function isSet(Node\Stmt\Class_ $class): bool
    {
        $markers = $this->markers();

        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (in_array($attr->name->getLast(), $markers, true)) {
                    return true;
                }
            }
        }

        foreach ($class->implements as $interface) {
            if ($this->endsWithMarker($interface->getLast(), $markers)) {
                return true;
            }
        }

        if ($class->extends instanceof Node\Name && $this->endsWithMarker($class->extends->getLast(), $markers)) {
            return true;
        }

        return $class->name !== null && $this->endsWithMarker($class->name->toString(), $markers);
    }

    /**
     * @param  list<string>  $markers
     */
    private function endsWithMarker(string $name, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (str_ends_with($name, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function isFinderName(string $name): bool
    {
        $lower = strtolower($name);

        foreach (self::FINDER_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return str_ends_with($lower, 'ornull') || str_ends_with($lower, 'ordefault');
    }

    /**
     * @return list<string>
     */
    private function markers(): array
    {
        $markers = $this->config('markers', self::DEFAULT_MARKERS);

        return is_array($markers) && $markers !== [] ? array_values(array_map(static fn ($m): string => self::shortName((string) $m), $markers)) : self::DEFAULT_MARKERS;
    }

    /**
     * @return list<string>
     */
    private function optionClasses(): array
    {
        $classes = $this->config('option_classes', self::DEFAULT_OPTION_CLASSES);

        return is_array($classes) && $classes !== [] ? array_values(array_map(static fn ($c): string => self::shortName((string) $c), $classes)) : self::DEFAULT_OPTION_CLASSES;
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

}
