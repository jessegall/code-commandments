<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallConsumptionCensus;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\RegistryLeak;
use JesseGall\CodeCommandments\Support\RegistryShape;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Throwable;

/**
 * Enforce the registry contract on a class that opts in via a marker (a base
 * class named `Registry` or extending one, an interface named `Registry`, or a
 * `#[Registry]` attribute): a registry returns
 * the requested item or THROWS — it does not hand back `Option<T>` or `T | null`.
 *
 * A registry is a TOTAL lookup over a known keyspace; asking for an unregistered
 * key is a programming error, not an expected branch every caller must unwrap.
 * Presence and retrieval split into `has(key): bool` and `get(key): T`. (Same
 * shape as PSR-11 `has()`/`get()` and Laravel `bound()`/`make()`.) The author
 * asserted "this is a registry" with the marker, so the contract is unambiguous.
 *
 * Tier 1 only (marker-driven); the un-marked "looks like a registry" heuristic is
 * intentionally not implemented — that is where false positives breed.
 */
#[IntroducedIn('1.125.0')]
class RegistryReturnContractProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_MARKERS = ['Registry'];

    private const DEFAULT_OPTION_CLASSES = ['Option'];

    /** Getter names that ANNOUNCE nullability is normal — left even on a registry. */
    private const FINDER_PREFIXES = ['find', 'search', 'try', 'lookup'];

    private ?CodebaseIndex $index = null;

    private ?CallConsumptionCensus $census = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
        $this->census = null; // rebuild against the new index
    }

    public function description(): string
    {
        return 'A registry returns the item or throws — not Option<T> or T | null (with a has() companion)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A PUBLIC getter on a class marked as a `Registry` (a base class named `Registry` or extending one, a `Registry` interface, or a `#[Registry]` attribute) returns `Option<T>` or `T | null`. A registry is a total lookup — a miss is a wiring bug, so it should return T or throw, with a `has()` companion.')
            ->leaveWhen('the method NAME announces nullability is normal — `find*`, `search*`, `try*`, `lookup*`, `*OrNull`, `*OrDefault`, or a `<thing>For<Other>` directional lookup (`keyForClass`, `classForKey`) — those are genuine value-or-nothing finders, not the registry contract.')
            ->whenUnsure('if a miss means "you asked for something that was never registered" (a bug), return T and throw; if a miss is an expected, branched-on outcome (a cache, a finder), it is not a registry getter — rename it or drop the marker.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A registry is a TOTAL lookup over a known keyspace — "give me the pipeline for
this class". The expected case is "it's there"; a miss means you asked for a key
that was never registered, which is a programming error. Modelling that as
`Option<T>` / `T | null` pushes a null-check onto every caller for a situation
that is almost always a bug, and scatters `->getOrThrow()` / `?? throw` ceremony
around a throw the registry should own.

Bad — the registry leaks an Option:
    public function pipeline(string $class): Option
    {
        return $this->pipelines[$class] ??= $this->reflect($class);
    }
    // every caller: ->getOrThrow() or if (->isEmpty()) throw …

Good — return-or-throw, with a has() companion:
    public function hasPipeline(string $class): bool
    {
        return $this->resolve($class)->hasValue();
    }
    public function pipeline(string $class): PipelineSpec
    {
        return $this->resolve($class)->getOrThrow();
    }

The internal `Option` memo stays — it just stops leaking across the public
boundary, so callers read "is it there? then get it" and the throw lives where
the keyspace knowledge does.

WHAT FIRES — a PUBLIC method on a class carrying the `Registry` marker (a base
class named `Registry` or extending one, an interface named `Registry`, or
`#[Registry]`) whose return type is `Option<T>`, `?T`, or `T | null`.

WHAT DOES NOT — a finder-named getter (`find*`/`search*`/`try*`/`lookup*`/
`*OrNull`/`*OrDefault`, or a `<thing>For<Other>` directional lookup like
`keyForClass`/`classForKey`: the absence is a real, handled outcome), a
non-public method, a `bool` `has()`/`is()`, or an `Option` used only INTERNALLY
(a private memo field). The marker is the opt-in, so there is no "is this really
a registry" guessing.

NOT auto-fixable — retyping a maybe-getter to throw CHANGES RUNTIME BEHAVIOUR
(callers handling the miss would suddenly throw). Resolve by hand: if a miss is a
wiring bug, return T and throw with a `has()` companion; if a miss is expected,
it is a finder — rename it (`find*`/`*ForX`/…) or drop the marker.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $sins = [];
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            // MARKED registry → the imperative sin path (Option OR nullable getters).
            if ($this->isRegistry($class, $ast)) {
                foreach ($class->getMethods() as $method) {
                    $kind = $this->leakyGetter($method);

                    if ($kind === null) {
                        continue;
                    }

                    $name = $method->name->toString();
                    $sins[] = $this->sinAt(
                        $method->getStartLine(),
                        sprintf('Registry getter %s() returns %s. If a miss is a wiring bug, return T and throw, with a `has%s()` companion. If a miss is a genuine, handled outcome (callers branch on null / `?->` / `?? default`), this is a FINDER, not a registry getter — rename it (`find*`/`try*`/`*ForX`/`*OrNull`) or drop the marker. Resolve by hand: retyping to throw changes runtime behaviour, so it is deliberately not auto-fixed.', $name, $kind === 'option' ? 'an Option' : 'a nullable', ucfirst($name)),
                        $this->lineAt($content, $method->getStartLine()),
                        null,
                        'registry-return:' . $name,
                        false,
                    );
                }

                continue;
            }

            // UNMARKED but registry-SHAPED → advisory WARNING path. A heuristic
            // (the author didn't opt in with a marker), so it never blocks — but a
            // warning still drives the root-cause precedence/guard. Raw `?T` only:
            // a getter already returning Option has opted into genuine absence, so
            // it is left alone (protects a real Option<T> resolver/registry).
            $shape = RegistryShape::detect($class);

            if ($shape === null) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $warning = $this->markerlessWarning($class, $shape, $method, $ast, $content);

                if ($warning !== null) {
                    $warnings[] = $warning;
                }
            }
        }

        if ($sins === [] && $warnings === []) {
            return $this->righteous();
        }

        return new Judgment(sins: $sins, warnings: $warnings);
    }

    /**
     * The markerless (shape-detected) registry warning for one method, or null.
     * Delegates the firing decision to the shared {@see RegistryLeak} predicate
     * so the registry-scoped NoNullCoalesceToNull auto-fix flags the exact same
     * getters (keeping the auto-fix downstream of this cause).
     *
     * @param  array<Node>  $ast
     */
    private function markerlessWarning(Node\Stmt\Class_ $class, RegistryShape $shape, Node\Stmt\ClassMethod $method, array $ast, string $content): ?Warning
    {
        if (! RegistryLeak::isLeakyNullableGetter($class, $shape, $method, $this->classFqcn($class, $ast), $this->census())) {
            return null;
        }

        $name = $method->name->toString();

        return $this->warningAt(
            $method->getStartLine(),
            sprintf('%s() returns a nullable on a class with the registry shape (you `register`/store into it, then look up). A miss is a wiring bug, not a valid "no value": return T and throw — with a `has%s()` companion / a named exception — or, if absence is genuine, model it as an Option at the source. (Heuristic: no `Registry` marker; mark the class or extend a base for full enforcement.)', $name, ucfirst($name)),
            $this->lineAt($content, $method->getStartLine()),
            'registry-return-shape:' . $name,
        );
    }

    private function census(): ?CallConsumptionCensus
    {
        if ($this->index === null) {
            return null;
        }

        return $this->census ??= new CallConsumptionCensus($this->index);
    }

    /**
     * #84: resolve the marker TRANSITIVELY — a class is a registry if it (or any
     * ancestor) carries the marker interface or `#[Registry]` attribute. The
     * idiomatic shape is one abstract base marked once, with N concrete
     * subclasses; forcing the marker onto every leaf is the boilerplate a base
     * exists to remove.
     *
     * @param  array<Node>  $ast
     */
    private function isRegistry(Node\Stmt\Class_ $class, array $ast): bool
    {
        $markers = $this->markers();

        // Single-file markers (no index needed): the class's own attribute or
        // implements clause; the class IS the marker base (e.g. `abstract class
        // Registry`); or it directly `extends` a class named after a marker
        // (`class FooRegistry extends Registry`) — the idiomatic abstract-base
        // convention that #103 showed went entirely undetected.
        if ($this->classCarriesMarker($class, $markers)
            || ($class->name !== null && in_array($class->name->toString(), $markers, true))
            || ($class->extends instanceof Node\Name && in_array($class->extends->getLast(), $markers, true))
        ) {
            return true;
        }

        if ($this->index === null) {
            return false;
        }

        $fqcn = $this->classFqcn($class, $ast);

        if ($fqcn === null) {
            return false;
        }

        // A marker interface inherited through ANY ancestor (interfacesOf walks
        // the parent chain + each level's interfaces).
        foreach ($this->index->interfacesOf($fqcn) as $interface) {
            if (in_array(self::shortName($interface), $markers, true)) {
                return true;
            }
        }

        // A `#[Registry]` attribute on an ANCESTOR class (attributes don't inherit
        // at runtime, but a base marker is the author's intent — honour it).
        $cursor = $this->index->classByFqcn($fqcn)?->parent;
        $depth = 0;

        while ($cursor !== null && $depth++ < 16) {
            // A transitive ANCESTOR named after a marker (e.g. extends a base
            // `Registry` several levels up, possibly in another file).
            if (in_array(self::shortName($cursor), $markers, true)) {
                return true;
            }

            $summary = $this->index->classByFqcn(ltrim($cursor, '\\'));

            if ($summary === null) {
                break;
            }

            if ($this->fileClassHasMarkerAttribute($summary->filePath, self::shortName($cursor), $markers)) {
                return true;
            }

            $cursor = $summary->parent;
        }

        return false;
    }

    /**
     * @param  list<string>  $markers
     */
    private function classCarriesMarker(Node\Stmt\Class_ $class, array $markers): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (in_array($attr->name->getLast(), $markers, true)) {
                    return true;
                }
            }
        }

        foreach ($class->implements as $interface) {
            if (in_array($interface->getLast(), $markers, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function classFqcn(Node\Stmt\Class_ $class, array $ast): ?string
    {
        if ($class->name === null) {
            return null;
        }

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Namespace_::class) as $ns) {
            $namespace = $ns->name?->toString();

            return $namespace !== null && $namespace !== '' ? $namespace . '\\' . $class->name->toString() : $class->name->toString();
        }

        return $class->name->toString();
    }

    /**
     * @param  list<string>  $markers
     */
    private function fileClassHasMarkerAttribute(string $filePath, string $shortName, array $markers): bool
    {
        $content = @file_get_contents($filePath);

        if ($content === false) {
            return false;
        }

        try {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);
        } catch (Throwable) {
            return false;
        }

        if ($ast === null) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $shortName && $this->classCarriesMarker($class, $markers)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 'option' / 'nullable' when $method is a public getter leaking absence, else
     * null (finder names, non-public, and non-leaky returns are exempt).
     */
    private function leakyGetter(Node\Stmt\ClassMethod $method): ?string
    {
        if (! $method->isPublic() || $method->isStatic()) {
            return null;
        }

        $name = $method->name->toString();

        if ($this->isFinderName($name) || str_starts_with($name, '__')) {
            return null;
        }

        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            return 'nullable';
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    return 'nullable';
                }
            }

            return null;
        }

        if ($type instanceof Node\Name && in_array($type->getLast(), $this->optionClasses(), true)) {
            return 'option';
        }

        return null;
    }

    private function isFinderName(string $name): bool
    {
        $lower = strtolower($name);

        foreach (self::FINDER_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        if (str_ends_with($lower, 'ornull') || str_ends_with($lower, 'ordefault')) {
            return true;
        }

        // #114: a `<thing>For<Other>` directional lookup (keyForClass,
        // classForKey, slugForToken, resourceTypeForModel) is a finder — "the X
        // for Y, if any" — whose absence is a real, handled outcome, not a
        // registry's must-exist get(). Treat it like find*/try*.
        return preg_match('/[a-z0-9]For[A-Z]/', $name) === 1;
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

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
