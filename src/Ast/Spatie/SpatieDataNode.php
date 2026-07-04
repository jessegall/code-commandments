<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use JesseGall\CodeCommandments\Ast\Support\PageObject;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\UnionType;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;

/**
 * The `spatie/laravel-data` knowledge, as a node: whether a class IS a `Data` subclass, whether a
 * `new`/`::from()` targets one, whether the class is RICH (has a cast/map/nest/factory), and the
 * `::collect()`-migration semantics (per-item hydration, the tolerant-catch and keyed-map
 * exemptions). The `Data` FQCN lives here once; a detector reads `$n->isDataClass()`. Reached by
 * type-hinting it in a `where` closure.
 */
final class SpatieDataNode extends NodeMatch
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    /**
     * The Spatie CONTAINER-injection attributes — the ones that pull a service dependency out of the
     * container into a property (`#[FromContainer]`, `#[FromContainerProperty]`). A property carrying one
     * is a COLLABORATOR the page builds itself with, not page data. (The value-injection attributes —
     * `FromRouteParameter`, `FromAuthenticatedUser` — inject payload values that may legitimately
     * serialize, so they are NOT in this list.) Stated here once as the package's contract.
     */
    private const array CONTAINER_INJECTION_ATTRIBUTES = [
        'FromContainer',
        'FromContainerProperty',
    ];

    /** The attribute that keeps a property out of the serialized payload AND the generated TypeScript. */
    private const string HIDDEN = 'Hidden';

    /**
     * Date/time types Spatie casts out of the box via the built-in `DateTimeInterfaceCast` — so a
     * value-object property of one never needs an author-written cast. Matched by short name.
     */
    private const array NATIVE_CAST_TYPES = [
        'DateTimeInterface',
        'DateTime',
        'DateTimeImmutable',
        'Carbon',
        'CarbonImmutable',
    ];

    /**
     * The built-in output transformers whose target TS type the typescript-transformer already knows
     * (a `DateTimeInterface` → `string`, an `Arrayable` → its array) — so a `#[WithTransformer]` naming
     * one needs no explicit TS type. A CUSTOM transformer's output shape is invisible to the generator.
     */
    private const array KNOWN_TS_TRANSFORMERS = [
        'DateTimeInterfaceTransformer',
        'ArrayableTransformer',
    ];

    /**
     * Does this `#[WithTransformer(...)]` change a property's wire shape WITHOUT a paired
     * `#[TypeScriptType]` / `#[LiteralTypeScriptType]` declaring it? A built-in transformer the generator
     * already maps is exempt.
     */
    public function transformerLacksTsType(): bool
    {
        if (! $this->node instanceof Attribute) {
            return false;
        }

        if (in_array($this->firstArgumentClassShortName($this->node), self::KNOWN_TS_TRANSFORMERS, true)) {
            return false;
        }

        $carrier = $this->walkUp(static fn (Node $node): bool => $node instanceof Property || $node instanceof Param);

        if ($carrier === null) {
            return false;
        }

        $field = $this->codebase->wrap($carrier, $this->file)->asField();

        return $field !== null && ! $field->hasAttribute('TypeScriptType', 'LiteralTypeScriptType');
    }

    /**
     * The short class name of an attribute's first `X::class` argument, or null when it isn't a class literal.
     */
    private function firstArgumentClassShortName(Attribute $attribute): ?string
    {
        $argument = $attribute->args[0]->value ?? null;

        return $argument instanceof ClassConstFetch && $argument->class instanceof Name
            ? self::shortName($argument->class->toString())
            : null;
    }

    /**
     * Is this class declaration a Spatie `Data` subclass?
     */
    public function isDataClass(): bool
    {
        return $this->codebase->extends($this->enclosingClassName(), self::DATA);
    }

    /**
     * Is this `new X(...)` constructing a `Data` subclass?
     */
    public function isNewData(): bool
    {
        return $this->codebase->extends($this->newClassName(), self::DATA);
    }

    /**
     * Is this a static call whose receiver class is a `Data` subclass — e.g. `SomeData::from(...)`?
     */
    public function onDataClass(): bool
    {
        return $this->codebase->extends($this->staticCallClass(), self::DATA);
    }

    /**
     * Is the `Data` class this `new` constructs RICH — does it have a cast, name map, nested-Data
     * hydration, or a magic `fromX()` factory that a raw `new` would skip? (Delegated to the shared
     * {@see DataClassShape} shape analysis, which the repent scribe reuses too.)
     */
    public function isRichData(): bool
    {
        return DataClassShape::forCodebase($this->codebase)->isRich($this->newClassName(), $this->codebase);
    }

    /**
     * Is the class this node is in (or IS) a PAGE OBJECT — a `Data` class that composes more than one
     * nested `Data` AND travels back in a response? True whether this node is the class declaration
     * (`whereClass()`) or a statement inside it (an `app(...)` call, a constructor assignment), so every
     * page-object detector gates on the same predicate. (Delegated to the shared {@see PageObject} policy.)
     */
    public function isPageObject(): bool
    {
        return $this->isDataClass()
            && PageObject::forCodebase($this->codebase)->isPageObject($this->enclosingClassName());
    }

    /**
     * Does this class have a PUBLIC container-injected SERVICE that is NOT `#[Hidden]`? An injected
     * collaborator (a `#[FromContainer]` repository, projector, or the request) is construction
     * machinery, not page data — left public and un-hidden it serializes and leaks into the generated
     * TypeScript type. Read off the generic field reader: a public field carrying a container-injection
     * attribute but no `#[Hidden]`, whose type is NOT itself a `Data` (an injected nested `Data` is
     * legitimate payload, not a service).
     */
    public function hasUnhiddenInjectedService(): bool
    {
        foreach ($this->fields() as $field) {
            if ($field->isPublic
                && $field->hasAttribute(...self::CONTAINER_INJECTION_ATTRIBUTES)
                && ! $field->hasAttribute(self::HIDDEN)
                && ! $this->typeIsData($field->type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this declared type a `Data` subclass — an injected nested payload rather than a service?
     */
    private function typeIsData(?Node $type): bool
    {
        $class = TypeName::class($type);

        return $class !== null && $this->codebase->extends($class, self::DATA);
    }

    /**
     * Does this node hand-flatten a VALUE OBJECT into the wire array of a PUBLIC slot — a single array
     * literal whose every value is fetched off the SAME receiver, that receiver resolving to a non-`Data`,
     * non-enum object? The array reaches a public slot three ways: a getter hook, a `#[Computed]` method,
     * or a constructor assignment to a public declared slot.
     */
    public function flattensValueObjectToArray(): bool
    {
        $array = $this->publicSlotArrayOutput();

        if ($array === null) {
            return false;
        }

        $receiver = self::sharedFetchReceiver($array);
        $function = $this->enclosingFunction();

        if ($receiver === null || $function === null) {
            return false;
        }

        $type = TypeResolver::forCodebase($this->codebase)->typeOf($receiver, $function, $this->enclosingClassName());

        return $type !== null
            && ! $this->codebase->extends($type, self::DATA)
            && ! $this->codebase->isEnum($type);
    }

    /**
     * The array literal that produces a PUBLIC slot's value at this node — the body of a getter hook or
     * `#[Computed]` method, or the RHS of a constructor assignment to a public declared slot. Null otherwise.
     */
    private function publicSlotArrayOutput(): ?Array_
    {
        if ($this->node instanceof Assign) {
            return $this->node->expr instanceof Array_ && $this->assignedPropertyIsPublicSlot()
                ? $this->node->expr
                : null;
        }

        return $this->soleArrayLiteralOutput();
    }

    /**
     * Is this field a PUBLIC value-object slot HAND-BUILT at every construction site of its `Data`, via the
     * whole-program {@see DataConstructions} index? Excludes a slot already carrying a cast attribute, a
     * nested `Data`, and a natively-cast type (enum, date/time).
     */
    public function alwaysHandBuiltAtConstruction(): bool
    {
        $field = $this->asField();
        $data = $this->enclosingClassName();

        if ($field === null || ! $field->isPublic || $data === null || ! $this->isDataClass()) {
            return false;
        }

        if ($field->hasAttribute('WithCast', 'WithCastAndTransformer', 'WithCastable')) {
            return false;
        }

        $type = TypeName::class($field->type);

        if ($type === null || $this->codebase->extends($type, self::DATA) || $this->castsNatively($type)) {
            return false;
        }

        return DataConstructions::forCodebase($this->codebase)->alwaysHandBuilt($data, $field->name, $type);
    }

    /**
     * Does Spatie cast this type out of the box — an enum (native) or a date/time (the built-in
     * `DateTimeInterfaceCast`) — so a custom `#[WithCast]` is never the fix?
     */
    private function castsNatively(string $type): bool
    {
        return $this->codebase->isEnum($type)
            || in_array(self::shortName($type), self::NATIVE_CAST_TYPES, true)
            || $this->codebase->implements($type, 'DateTimeInterface');
    }

    /**
     * Is the property this `$this->x = …` assignment targets a PUBLIC declared slot of the page object —
     * a serialized payload field (not a promoted param, which the framework already fills)? That is the
     * thing that should be a computed hook rather than an imperative constructor fill.
     */
    public function assignedPropertyIsPublicSlot(): bool
    {
        $name = $this->assignedPropertyName();

        if ($name === null) {
            return false;
        }

        foreach ($this->fields() as $field) {
            if ($field->name === $name) {
                return $field->isPublic && ! $field->isPromoted;
            }
        }

        return false;
    }

    /**
     * Is this assignment's right-hand side a DEFERRED value — a `Lazy::…()` closure or an Inertia
     * `DeferProp`/`MergeProp` — whose whole point is to NOT run eagerly? Hoisting it into a `get` hook
     * would evaluate it on every read and destroy the deferral, so it belongs in the constructor.
     */
    public function assignmentRhsIsDeferred(): bool
    {
        if (! $this->node instanceof Assign) {
            return false;
        }

        $rhs = $this->node->expr;

        if ($rhs instanceof StaticCall && $rhs->class instanceof Name) {
            return self::shortName($rhs->class->toString()) === 'Lazy';
        }

        return $rhs instanceof New_
            && $rhs->class instanceof Name
            && str_contains(self::shortName($rhs->class->toString()), 'Prop');
    }

    /**
     * Is the slot this assignment targets declared as a DEFERRED type — its type union names `Lazy` or
     * an Inertia `…Prop` (`DeferProp`/`MergeProp`)? Such a slot is deferred by contract (whatever factory
     * builds it — `Table::asClosureLazy(): Lazy`, `Lazy::closure(...)`), so its constructor assignment is
     * legitimate and must not be hoisted into an eager `get` hook.
     */
    public function assignedSlotTypeIsDeferred(): bool
    {
        $name = $this->assignedPropertyName();

        if ($name === null) {
            return false;
        }

        foreach ($this->fields() as $field) {
            if ($field->name === $name) {
                return $this->typeNamesDeferral($field->type);
            }
        }

        return false;
    }

    /**
     * Does this type node name a deferral wrapper — `Lazy` or an Inertia `…Prop` — unwrapping `?T` and
     * scanning a union/intersection?
     */
    private function typeNamesDeferral(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            return $this->typeNamesDeferral($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $inner) {
                if ($this->typeNamesDeferral($inner)) {
                    return true;
                }
            }

            return false;
        }

        if (! $type instanceof Name) {
            return false;
        }

        $short = self::shortName($type->toString());

        return $short === 'Lazy' || str_ends_with($short, 'Prop');
    }

    /**
     * Is the property this assignment targets written more than once in the constructor — a slot built
     * up in steps, which can't collapse into a single `get` expression?
     */
    public function propertyAssignedMoreThanOnce(): bool
    {
        $name = $this->assignedPropertyName();

        if ($name === null) {
            return false;
        }

        $count = $this->getConstructor(fn (ClassMethod $ctor): int => count(array_filter(
            new NodeFinder()->findInstanceOf($ctor->stmts ?? [], Assign::class),
            static fn (Assign $assign): bool => self::assignsThisPropertyNamed($assign, $name),
        )));

        return ($count ?? 0) > 1;
    }

    private static function assignsThisPropertyNamed(Assign $assign, string $name): bool
    {
        return $assign->var instanceof PropertyFetch
            && $assign->var->var instanceof Variable
            && $assign->var->var->name === 'this'
            && $assign->var->name instanceof Identifier
            && $assign->var->name->toString() === $name;
    }

    /**
     * Is this node inside a loop OR an `array_map` callback — the two shapes the spatie-data skill
     * names as per-item hydration that `::collect()` replaces.
     */
    public function isPerItemHydration(): bool
    {
        return $this->isWithinLoop() || $this->isWithinArrayMap();
    }

    /**
     * Is this `::from()` NOT a straight per-row construction — guarded by a branch (`if`, `match`,
     * `?:`) or buried in a nested callback (an `Option::inspect`/`->map`, etc.) between it and its
     * loop, rather than run once per element? Such a loop FILTERS or conditionally builds, and
     * `::collect()` maps every row 1:1 — it can't express the skip — so it is not the manual-
     * hydration smell. `array_map`'s own callback is the recognised mapping idiom, so it is NOT
     * treated as a nested callback here. Only meaningful inside a loop (the `array_map` path has
     * no loop to gate against and returns false).
     */
    public function isConditionalConstruction(): bool
    {
        $loop = $this->walkUp(static fn (Node $node): bool =>
            $node instanceof Foreach_ || $node instanceof For_ || $node instanceof While_ || $node instanceof Do_);

        if ($loop === null) {
            return false;
        }

        for ($node = $this->node?->getAttribute('parent'); $node instanceof Node && $node !== $loop; $node = $node->getAttribute('parent')) {
            if ($node instanceof If_ || $node instanceof Else_ || $node instanceof ElseIf_ || $node instanceof Match_ || $node instanceof Ternary) {
                return true;
            }

            if (($node instanceof Closure || $node instanceof ArrowFunction) && ! self::isArrayMapArgument($node->getAttribute('parent'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this node inside a `try` whose `catch` SKIPS the failed item — a `continue` or `return`
     * in a catch clause? That marks a TOLERANT decoder (drop a malformed entry and keep going);
     * `::collect()` is all-or-nothing and throws on the first bad row, so it cannot express the
     * per-entry skip. The try is matched only when it is itself inside a loop, so a method-level
     * try/catch around the whole map doesn't grant the exemption.
     */
    public function isWithinTolerantCatch(): bool
    {
        $try = $this->walkUp(static fn (Node $node): bool => $node instanceof TryCatch);

        if (! $try instanceof TryCatch || ! self::within($try, static fn (Node $n): bool =>
            $n instanceof Foreach_ || $n instanceof For_ || $n instanceof While_ || $n instanceof Do_)) {
            return false;
        }

        foreach ($try->catches as $catch) {
            if ((new NodeFinder)->findFirst($catch->stmts, static fn (Node $n): bool =>
                $n instanceof Continue_ || $n instanceof Return_) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this node the right-hand side of an assignment into a KEYED map — `$out[$id] = X::from(...)`?
     * `::collect()` returns a LIST; it cannot key by a computed value, so a keyed-map build is not
     * the one-pass mapping the skill replaces. A plain list append (`$out[] = …`) is NOT exempt.
     */
    public function isKeyedMapAssignment(): bool
    {
        $assign = $this->walkUp(static fn (Node $node): bool => $node instanceof Assign);

        return $assign instanceof Assign
            && $assign->var instanceof ArrayDimFetch
            && $assign->var->dim !== null;
    }
}
