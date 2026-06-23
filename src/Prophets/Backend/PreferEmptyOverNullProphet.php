<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use JesseGall\CodeCommandments\Support\NullDistinguishedCallCensus;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * The collection/bag sibling of PreferOptionOverNull: when a return type, typed
 * property, or null-defaulted param is `T | null` (or `?T`) and T has a natural
 * EMPTY identity — array, a Collection / DataCollection / Fluent / value-bag, or
 * any class that is Countable / Traversable / Arrayable — suggest returning an
 * EMPTY instance instead of null.
 *
 * An empty collection IS the absence: callers iterate / ->get() / ->all() /
 * count() with no null-guard AND no Option to unwrap. Scalar / single-object
 * absence is still Option's job (PreferOptionOverNull); this models the
 * collection case, so Option<Collection> never pushes ->getOr([]) onto callers.
 *
 * Advisory, never a sin — LEAVE when a caller must distinguish ABSENT from EMPTY
 * (cache miss vs empty result, 404 vs empty list).
 */
#[IntroducedIn('1.124.0')]
class PreferEmptyOverNullProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /** Vendor/framework types with a natural empty identity (matched by short name). */
    private const KNOWN_COLLECTION_SHORT = [
        'Collection', 'LazyCollection', 'EloquentCollection', 'DataCollection',
        'PaginatedDataCollection', 'Fluent',
    ];

    /** Interfaces (by short name) that give a type an empty identity. */
    private const COLLECTION_INTERFACES = [
        'Countable', 'IteratorAggregate', 'Traversable', 'ArrayAccess', 'Arrayable',
    ];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Return an empty collection/bag instead of null — an empty instance is the absence';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A return type / typed property / null-defaulted param is `T | null` (or `?T`) where T is a collection-like type (array, Collection, DataCollection, Fluent/value-bag, or any Countable/Traversable/Arrayable). An empty instance models the absence with no null-guard and no Option.')
            ->leaveWhen('a caller must DISTINGUISH absent from empty — a cache miss vs an empty result, "not found" (404) vs "found but empty". Then null (or Option) carries information an empty value cannot.')
            ->whenUnsure('if every caller treats "no value" and "empty" the same (they iterate / ->all() / count()), drop the `| null` and return the empty instance (`[]` / `T::empty()` / `new T()`); if any caller branches on the null, keep it.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A `Collection | null` (or `?Collection`, `array | null`, `ValueBag | null`)
return is a null nobody needs: the type already has an empty identity, so an
empty instance IS "no value". Returning null forces every caller to null-guard
before iterating; returning the empty collection lets them iterate straight
through.

Bad — null is the absence:
    public function rows(): ?Collection
    {
        return $this->found ? collect($this->found) : null;
    }
    // caller: if (($r = $x->rows()) !== null) { foreach ($r as ...) }

Good — empty IS the absence:
    public function rows(): Collection
    {
        return $this->found ? collect($this->found) : new Collection();
    }
    // caller: foreach ($x->rows() as ...)

The empty instance to return: `array` -> `[]`; a class with a static no-arg
`empty()`/`make()` -> `T::empty()`; a no-arg-constructible class -> `new T()`.

WHAT FIRES — a `T | null` / `?T` / `null | T` type (return, typed property, or
null-defaulted param) where stripping `null` leaves exactly ONE collection-like
T: `array`, a Collection / LazyCollection / DataCollection / Fluent, or a project
class that is Countable / Traversable / Arrayable (or extends one of those). A
3+ member union defers to WideUnionType.

WHAT DOES NOT — scalar or single-object `T | null` (that is PreferOptionOverNull's
Option case), and any method where a caller must tell ABSENT from EMPTY apart.
Advisory: weigh the call site before dropping the null.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $namespace = $this->fileNamespace($ast);
        $uses = $this->fileUses($ast);
        $nullDistinguished = $this->index !== null
            ? NullDistinguishedCallCensus::methodNames($this->index)
            : [];
        $warnings = [];

        foreach ($this->nullableCollectionTypes($ast) as $found) {
            $inner = $this->collectionInnerType($found['type'], $uses, $namespace);

            if ($inner === null) {
                continue;
            }

            // #89: a caller branches on the null — it genuinely distinguishes
            // ABSENT from EMPTY (the prophet's own LEAVE-WHEN), so collapsing
            // null -> [] would change behaviour. Honour the exclusion.
            if (isset($found['method']) && isset($nullDistinguished[$found['method']])) {
                continue;
            }

            $line = $found['type']->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf('%s is `%s | null`, but %s has an empty identity — return %s instead of null, so callers never null-guard a collection.', $found['label'], $inner['display'], $inner['display'], $inner['empty']),
                $this->lineSnippet($content, $line),
                'prefer-empty:' . $found['symbol'],
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Nullable type positions worth checking: method returns, typed properties,
     * and null-defaulted params.
     *
     * @param  array<Node>  $ast
     * @return list<array{type: Node, label: string, symbol: string}>
     */
    private function nullableCollectionTypes(array $ast): array
    {
        $finder = new NodeFinder;
        $out = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class) as $method) {
            if ($method->returnType !== null && $this->isNullableUnion($method->returnType)
                && ! $this->isStructShapedArray($method->returnType, $method)) {
                $out[] = ['type' => $method->returnType, 'label' => 'The return type of ' . $method->name->toString() . '()', 'symbol' => 'return:' . $method->name->toString(), 'method' => $method->name->toString()];
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Property::class) as $property) {
            // #86: a PRIVATE nullable collection property is almost always an
            // internal lazy-init memo (`private array|null $resources = null;`
            // resolved with `??=`), where null = "not loaded yet" and [] =
            // "loaded but empty" genuinely differ. That is the rule's own
            // distinguish-absent-from-empty LEAVE-WHEN — skip private fields and
            // judge only the API surface (return types + public/protected props).
            if ($property->isPrivate()) {
                continue;
            }

            if ($property->type !== null && $this->isNullableUnion($property->type)
                && ! $this->isStructShapedArray($property->type, $property)) {
                $name = $property->props[0]->name->toString() ?? 'property';
                $out[] = ['type' => $property->type, 'label' => 'The property $' . $name, 'symbol' => 'prop:' . $name];
            }
        }

        return $out;
    }

    private function isNullableUnion(Node $type): bool
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
     * True when the type is a nullable bare `array` whose docblock declares an
     * `array{…}` SHAPE — a struct/tuple (e.g. `array{match: string, offset: int}`),
     * NOT a collection. Such a value has no empty identity: `[]` is not a valid
     * instance and `null` legitimately means "absent". Steering it to `[]` is
     * wrong, so it is exempt (the rule's distinguish-absent-from-empty LEAVE-WHEN).
     */
    private function isStructShapedArray(Node $type, Node $owner): bool
    {
        $members = $type instanceof Node\NullableType
            ? [$type->type]
            : array_values(array_filter(
                $type instanceof Node\UnionType ? $type->types : [],
                static fn (Node $m): bool => ! ($m instanceof Node\Identifier && strtolower($m->toString()) === 'null'),
            ));

        if (count($members) !== 1
            || ! ($members[0] instanceof Node\Identifier)
            || strtolower($members[0]->toString()) !== 'array') {
            return false;
        }

        $doc = $owner->getDocComment();

        // `array{…}` is the shape syntax; `array<…>` / `list<…>` are collections
        // (an empty instance IS valid) and stay flagged.
        return $doc !== null && preg_match('/\barray\s*\{/i', $doc->getText()) === 1;
    }

    /**
     * When the nullable type, with `null` removed, is a SINGLE collection-like
     * type, return its display name + the empty instance to use; else null.
     *
     * @param  array<string, string>  $uses
     * @return array{display: string, empty: string}|null
     */
    private function collectionInnerType(Node $type, array $uses, ?string $namespace): ?array
    {
        $members = $type instanceof Node\NullableType
            ? [$type->type]
            : array_values(array_filter(
                $type instanceof Node\UnionType ? $type->types : [],
                static fn (Node $m): bool => ! ($m instanceof Node\Identifier && strtolower($m->toString()) === 'null'),
            ));

        // Exactly one non-null member, or a 3+ union defers to WideUnionType.
        if (count($members) !== 1) {
            return null;
        }

        $member = $members[0];

        if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'array') {
            return ['display' => 'array', 'empty' => '`[]`'];
        }

        if (! $member instanceof Node\Name) {
            return null;
        }

        $short = $member->getLast();

        if (! $this->isCollectionLike($member, $uses, $namespace)) {
            return null;
        }

        return ['display' => $short, 'empty' => $this->emptyInstanceFor($short)];
    }

    private function emptyInstanceFor(string $short): string
    {
        // Heuristic display only — the agent picks the constructible form.
        return sprintf('an empty %s (`%s::empty()` / `new %s()`)', $short, $short, $short);
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function isCollectionLike(Node\Name $member, array $uses, ?string $namespace): bool
    {
        if (in_array($member->getLast(), self::KNOWN_COLLECTION_SHORT, true)) {
            return true;
        }

        if ($this->index === null) {
            return false;
        }

        $fqcn = ltrim(NameResolver::resolve($member->toString(), $uses, $namespace), '\\');

        if ($this->index->classByFqcn($fqcn) === null) {
            return false; // vendor / unknown and not a known collection name
        }

        foreach ($this->index->interfacesOf($fqcn) as $interface) {
            if (in_array($this->shortOf($interface), self::COLLECTION_INTERFACES, true)) {
                return true;
            }
        }

        // An ancestor that is itself a known collection (e.g. extends Fluent).
        $cursor = $fqcn;
        $depth = 0;

        while ($cursor !== null && $depth++ < 16) {
            $summary = $this->index->classByFqcn(ltrim($cursor, '\\'));

            if ($summary === null) {
                break;
            }

            if ($summary->parent !== null && in_array($this->shortOf($summary->parent), self::KNOWN_COLLECTION_SHORT, true)) {
                return true;
            }

            $cursor = $summary->parent;
        }

        return false;
    }

    private function shortOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }


    /**
     * @param  array<Node>  $ast
     */
    private function fileNamespace(array $ast): ?string
    {
        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Namespace_::class) as $ns) {
            return $ns->name?->toString();
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string, string>
     */
    private function fileUses(array $ast): array
    {
        $uses = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $u) {
                $uses[$u->getAlias()->toString()] = $u->name->toString();
            }
        }

        return $uses;
    }
}
