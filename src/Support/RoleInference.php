<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Markerless, framework-AGNOSTIC role inference from a class's STRUCTURAL
 * fingerprint (#135 Tier B) + MUTATION PROVENANCE — pure AST, no framework
 * assumptions, no call graph required. The seed of the #135 archetype catalog:
 * it infers what a class IS from how its OWN state is shaped and written, so the
 * registry-aware rules can stop relying on a `*Registry` name and catch the
 * UNMARKED classes too ("the interesting bugs hide in the unmarked ones").
 *
 * The single most discriminating signal (#135) is MUTATION PROVENANCE — where a
 * class's own state is written:
 *
 *   | Provenance of the class's state               | Inferred archetype       |
 *   |-----------------------------------------------|--------------------------|
 *   | never written, or written only in the ctor    | ImmutableValue (VO/DTO)  |
 *   | a keyed array `[$k] ??= compute()` only        | Memo (cache)             |
 *   | a keyed array written by a public mutator AND  | StoreRegistry            |
 *   |   read back by lookups on the SAME property     |   (encapsulated store)   |
 *   | a property written DIRECTLY by a public setter | MutableBag (config)      |
 *   | everything else                                | Unknown                  |
 *
 * The STORE/REGISTRY fingerprint reuses {@see RegistryShape} (a keyed-array prop
 * written by a public mutator AND read by lookups on the same prop) — the full
 * shape (writers + lookups on one keyed prop) is a STRONG Tier-B signal that the
 * class is structurally a store, strong enough alone for this family. A lone
 * array property is NOT a store.
 *
 * GENERIC BY DESIGN: classification is derived from the AST shape (property
 * declarations, assignment targets, method visibility), never a name/suffix/base
 * list. It must produce sensible results on plain PHP — no Laravel, no Spatie.
 */
final class RoleInference
{
    private function __construct(
        private readonly Archetype $archetype,
        private readonly ?string $storeProperty,
    ) {}

    /**
     * Infer the class's structural archetype + the detected store property.
     * Most-discriminating signal first: the full store/registry shape, then
     * memo, then provenance of plain (non-store) state.
     */
    public static function infer(Node\Stmt\Class_ $class): self
    {
        // STRONG Tier-B: the full store shape — a keyed-array prop written by a
        // public mutator AND read back by lookups on the same prop. This is the
        // encapsulated-store fingerprint; strong enough alone for this family.
        $shape = RegistryShape::detect($class);

        if ($shape !== null) {
            return new self(Archetype::StoreRegistry, $shape->storeProperties()[0] ?? null);
        }

        // MEMO/CACHE: a keyed array touched ONLY as `[$k] ??= compute()` (a
        // lazy populate-on-read), with no other public mutator funnelling into
        // it. A memo is not a store you register into — it fills itself.
        $memoProp = self::memoProperty($class);

        if ($memoProp !== null) {
            return new self(Archetype::Memo, $memoProp);
        }

        // Provenance of the class's plain (non-store) state.
        $writes = self::stateWrites($class);

        // IMMUTABLE VALUE / DTO: state is never written after construction —
        // either never written at all (readonly / ctor-promoted) or written
        // only inside the constructor.
        if (self::writtenOnlyInConstructor($writes) && self::hasState($class)) {
            return new self(Archetype::ImmutableValue, null);
        }

        // MUTABLE BAG / CONFIG: a property written DIRECTLY by a public setter
        // (a public method assigns `$this->prop = …` straight, not via a keyed
        // store and not funnelled through a private writer).
        $bagProp = self::publiclyWrittenScalarProperty($writes);

        if ($bagProp !== null) {
            return new self(Archetype::MutableBag, null);
        }

        return new self(Archetype::Unknown, null);
    }

    public function archetype(): Archetype
    {
        return $this->archetype;
    }

    /**
     * Whether the class is structurally a store/registry by its STRONG Tier-B
     * fingerprint alone (no marker needed) — the gate the registry-family rules
     * use to fire on UNMARKED classes.
     */
    public function isStore(): bool
    {
        return $this->archetype === Archetype::StoreRegistry;
    }

    /** The detected keyed store/memo property name, or null. */
    public function storeProperty(): ?string
    {
        return $this->storeProperty;
    }

    /**
     * The keyed array property that is ONLY ever written as `$this->p[$k] ??= …`
     * (a memoize) and never by a plain mutator, or null. A memo fills itself
     * lazily on read; it is not registered into. We require at least one such
     * coalesce-assign and NO plain `$this->p[$k] = …` write of the same prop
     * (which would make it a store, already handled by RegistryShape).
     */
    private static function memoProperty(Node\Stmt\Class_ $class): ?string
    {
        $finder = new NodeFinder;

        $coalesced = [];

        foreach ($finder->findInstanceOf($class->stmts, Expr\AssignOp\Coalesce::class) as $assign) {
            $prop = self::thisArrayProp($assign->var);

            if ($prop !== null) {
                $coalesced[$prop] = true;
            }
        }

        if ($coalesced === []) {
            return null;
        }

        // A plain keyed write of the same prop disqualifies it as a pure memo —
        // that is a store (RegistryShape handles it). Only `??=` keeps it a memo.
        foreach ($finder->findInstanceOf($class->stmts, Expr\Assign::class) as $assign) {
            $prop = self::thisArrayProp($assign->var);

            if ($prop !== null) {
                unset($coalesced[$prop]);
            }
        }

        $remaining = array_keys($coalesced);

        return $remaining[0] ?? null;
    }

    /**
     * Every write of `$this->prop` (whole-property or keyed) the class performs,
     * each tagged with the visibility of the enclosing method and whether the
     * target is a keyed (array-dim) or whole-property assignment.
     *
     * @return list<array{prop: string, public: bool, inConstructor: bool, keyed: bool}>
     */
    private static function stateWrites(Node\Stmt\Class_ $class): array
    {
        $finder = new NodeFinder;
        $writes = [];

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $isCtor = strtolower($method->name->toString()) === '__construct';
            $isPublic = $method->isPublic();

            foreach ($finder->findInstanceOf($method->stmts, Expr\Assign::class) as $assign) {
                $keyed = self::thisArrayProp($assign->var);
                $whole = self::thisWholeProp($assign->var);
                $prop = $keyed ?? $whole;

                if ($prop === null) {
                    continue;
                }

                $writes[] = [
                    'prop' => $prop,
                    'public' => $isPublic,
                    'inConstructor' => $isCtor,
                    'keyed' => $keyed !== null,
                ];
            }
        }

        return $writes;
    }

    /**
     * Whether ALL recorded state writes happen inside the constructor — the
     * immutable provenance. (Reaching here means RegistryShape and the memo
     * check already declined, so a keyed store is not in play.)
     *
     * @param  list<array{prop: string, public: bool, inConstructor: bool, keyed: bool}>  $writes
     */
    private static function writtenOnlyInConstructor(array $writes): bool
    {
        foreach ($writes as $write) {
            if (! $write['inConstructor']) {
                return false;
            }
        }

        return true;
    }

    /**
     * The first whole-property (non-keyed) state written DIRECTLY by a public,
     * non-constructor method — the mutable-bag/config provenance (a public
     * setter writing `$this->prop = …`). null when no such write exists.
     *
     * @param  list<array{prop: string, public: bool, inConstructor: bool, keyed: bool}>  $writes
     */
    private static function publiclyWrittenScalarProperty(array $writes): ?string
    {
        foreach ($writes as $write) {
            if ($write['public'] && ! $write['inConstructor'] && ! $write['keyed']) {
                return $write['prop'];
            }
        }

        return null;
    }

    /**
     * Whether the class carries any instance state at all — a declared property
     * or a constructor-promoted param. Distinguishes an immutable VALUE (state,
     * frozen after construction) from a stateless helper (no state to be
     * immutable about).
     */
    private static function hasState(Node\Stmt\Class_ $class): bool
    {
        foreach ($class->getProperties() as $property) {
            if (! $property->isStatic()) {
                return true;
            }
        }

        foreach ($class->getMethods() as $method) {
            if (strtolower($method->name->toString()) !== '__construct') {
                continue;
            }

            foreach ($method->params as $param) {
                if ($param->flags !== 0) {
                    return true; // a promoted (public/protected/private/readonly) param is state
                }
            }
        }

        return false;
    }

    /**
     * The store property name a `$this->prop[...]` keyed assignment/fetch targets,
     * or null when the node isn't a keyed fetch off a `$this->` property.
     */
    private static function thisArrayProp(Node $node): ?string
    {
        if (! $node instanceof Expr\ArrayDimFetch) {
            return null;
        }

        return self::propName($node->var);
    }

    /**
     * The property name a whole-property `$this->prop` assignment targets, or
     * null when the node is not a direct `$this->` property fetch (keyed fetches
     * are handled by {@see thisArrayProp}).
     */
    private static function thisWholeProp(Node $node): ?string
    {
        if (! $node instanceof Expr\PropertyFetch) {
            return null;
        }

        return self::propName($node);
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
