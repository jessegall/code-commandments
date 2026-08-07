<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

use JesseGall\CodeCommandments\Support\ClassName;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use JesseGall\CodeCommandments\Ast\Support\PageObject;
use JesseGall\CodeCommandments\Ast\Support\TypeResolver;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
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
     * A `DataPipe` — a hydration hook the framework builds once and CACHES; it resolves collaborators per call.
     */
    public const string DATA_PIPE = 'Spatie\\LaravelData\\DataPipes\\DataPipe';

    /**
     * A `Cast` — built once and cached, handed the loose `$properties` array as its contract.
     */
    public const string CAST = 'Spatie\\LaravelData\\Casts\\Cast';

    /**
     * Spatie types the framework builds ONCE and reuses (cached), so a request-scoped constructor
     * dependency would go stale between calls — they resolve collaborators from the container per call
     * instead AND take the framework's loose array parameters. Exempt from container-reach + array-bag.
     */
    public const array NO_CONTAINER_CONTRACTS = [self::DATA_PIPE, self::CAST];

    /**
     * The Spatie CONTAINER-injection attributes — the ones that pull a service dependency out of the
     * container into a property (`#[FromContainer]`, `#[FromContainerProperty]`). A property carrying one
     * is a COLLABORATOR the page builds itself with, not page data. Scoped to container injection: the
     * value-injection attributes (`#[FromRouteParameter]`, `#[FromAuthenticatedUser]`) inject payload
     * values that may legitimately serialize, so they belong to the wire. Stated here once as the contract.
     */
    private const array CONTAINER_INJECTION_ATTRIBUTES = [
        'FromContainer',
        'FromContainerProperty',
    ];

    /**
     * The attribute that keeps a property out of the serialized payload AND the generated TypeScript.
     */
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
     * The Spatie marker type for a field that may be genuinely ABSENT (omitted from output, not `null`).
     */
    public const string OPTIONAL = 'Spatie\\LaravelData\\Optional';

    /**
     * The Spatie TypeScript-transformer attribute that compiles a Data class to a frontend type.
     */
    public const string TYPE_SCRIPT = 'Spatie\\TypeScriptTransformer\\Attributes\\TypeScript';

    /**
     * Spatie's collection wrapper — a valid RETURN of `::collect()`, but never a PROPERTY type.
     */
    public const string DATA_COLLECTION = 'Spatie\\LaravelData\\DataCollection';

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
            ? ClassName::short($argument->class->toString())
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
     * Does this node live in Data-class TERRITORY — a `Data` subclass itself, or a trait
     * any of whose consumers is one (a page-object concern)? The scope a Data-idiom
     * exemption (computed hooks, serialized fields) extends to: what a Data class pulls
     * in via a trait is written FOR Data classes.
     */
    public function inDataScope(): bool
    {
        if ($this->isDataClass()) {
            return true;
        }

        return array_any(
            $this->codebase->usersOfTrait($this->enclosingClassName()),
            fn (string $user): bool => $this->codebase->extends($user, self::DATA),
        );
    }

    /**
     * Is this a `Data` class the transformer compiles to a frontend type — a `Data` subclass carrying
     * `#[TypeScript]`? That attribute IS the "this shape travels to the frontend" signal: every public
     * field is emitted into `generated.ts` and read by a `.vue`.
     */
    public function isTypeScriptData(): bool
    {
        return $this->isDataClass() && $this->hasAttribute('TypeScript');
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
            || in_array(ClassName::short($type), self::NATIVE_CAST_TYPES, true)
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
            return ClassName::short($rhs->class->toString()) === 'Lazy';
        }

        return $rhs instanceof New_
            && $rhs->class instanceof Name
            && str_contains(ClassName::short($rhs->class->toString()), 'Prop');
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

        $short = ClassName::short($type->toString());

        return $short === 'Lazy' || str_ends_with($short, 'Prop');
    }

    /**
     * Does this constructor assignment's RHS read request-SCOPED / ambient state — a static `X::current()`
     * accessor, directly or one hop inside a method it calls (`$this->projector->project()` whose body reads
     * `SomeResolver::current()`)? Such a value is captured at BUILD time on purpose; hoisting it into a `get`
     * hook re-evaluates it at ACCESS time against whatever scope is bound then, returning the wrong context's
     * result. Left eager (never repented, never flagged).
     */
    public function assignmentReadsScopedState(): bool
    {
        return $this->node instanceof Assign
            && $this->scopedStateWithin($this->node->expr, $this->enclosingFunction(), $this->enclosingClassName(), 4, []);
    }

    /**
     * Does $node read request-scoped / ambient state within $depth call hops — a `::current()`/`->current()`
     * scoped accessor, directly OR inside any method, `::from` constructor, or `new` it reaches? Bounded and
     * cycle-guarded. Hoisting such a slot into a `get` hook would re-resolve it at ACCESS time against a
     * since-rebound context and stamp the wrong data (why EditorShell/EditorPage build eagerly). $function
     * and $selfClass are the scope $node lives in, so a `$this->x->m()` receiver resolves correctly as the
     * recursion crosses into each callee.
     *
     * @param  array<string, true>  $visited  class::method keys already walked
     */
    private function scopedStateWithin(Node $node, ?FunctionLike $function, ?string $selfClass, int $depth, array $visited): bool
    {
        if (self::readsScopedAccessor($node)) {
            return true;
        }

        if ($depth <= 0 || $function === null) {
            return false;
        }

        $resolver = TypeResolver::forCodebase($this->codebase);

        foreach (new NodeFinder()->findInstanceOf($node, MethodCall::class) as $call) {
            if ($call->name instanceof Identifier
                && $this->crossInto($resolver->typeOf($call->var, $function, $selfClass), $call->name->toString(), $depth, $visited)) {
                return true;
            }
        }

        foreach (new NodeFinder()->findInstanceOf($node, StaticCall::class) as $call) {
            if (! ($call->name instanceof Identifier && $call->class instanceof Name)) {
                continue;
            }

            $method = $call->name->toString() === 'from' ? '__construct' : $call->name->toString();

            if ($this->crossInto($this->resolveSelfClass($call->class->toString(), $selfClass), $method, $depth, $visited)) {
                return true;
            }
        }

        foreach (new NodeFinder()->findInstanceOf($node, New_::class) as $new) {
            if ($new->class instanceof Name
                && $this->crossInto($this->resolveSelfClass($new->class->toString(), $selfClass), '__construct', $depth, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recurse into $class::$method's body one hop deeper, guarding against cycles.
     */
    private function crossInto(?string $class, string $method, int $depth, array $visited): bool
    {
        if ($class === null) {
            return false;
        }

        $key = "{$class}::{$method}";

        if (isset($visited[$key])) {
            return false;
        }

        $visited[$key] = true;
        $classNode = $this->codebase->classNamed($class)->node;

        if (! $classNode instanceof \PhpParser\Node\Stmt\ClassLike) {
            return false;
        }

        $callee = $classNode->getMethod($method);

        return $callee !== null && $this->scopedStateWithin($callee, $callee, $class, $depth - 1, $visited);
    }

    /**
     * Resolve `self`/`static` to the class the call sits in; else the written name.
     */
    private function resolveSelfClass(string $class, ?string $selfClass): ?string
    {
        return in_array($class, ['self', 'static'], true) ? $selfClass : ltrim($class, '\\');
    }

    /**
     * Is the property this assignment fills marked `#[Eager]` — the author's explicit opt-out of the
     * lazify, pinning a deliberately-eager constructor build the detector can't otherwise infer safe?
     */
    public function assignedSlotIsEager(): bool
    {
        $name = $this->assignedPropertyName();
        $class = $this->enclosingClass();

        if ($name === null || ! $class instanceof \PhpParser\Node\Stmt\ClassLike) {
            return false;
        }

        foreach ($class->getProperties() as $property) {
            if (count($property->props) !== 1 || $property->props[0]->name->toString() !== $name) {
                continue;
            }

            foreach ($property->attrGroups as $group) {
                foreach ($group->attrs as $attr) {
                    $attrName = ltrim($attr->name->toString(), '\\');

                    if ($attrName === 'Eager' || str_ends_with($attrName, '\\Eager')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Does $node contain a `::current()` / `->current()` scoped/ambient-context accessor call?
     */
    private static function readsScopedAccessor(Node $node): bool
    {
        foreach (new NodeFinder()->findInstanceOf($node, StaticCall::class) as $call) {
            if ($call->name instanceof Identifier && $call->name->toString() === 'current') {
                return true;
            }
        }

        foreach (new NodeFinder()->findInstanceOf($node, MethodCall::class) as $call) {
            if ($call->name instanceof Identifier && $call->name->toString() === 'current') {
                return true;
            }
        }

        return false;
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

        $count = $this->fromConstructor(fn (ClassMethod $ctor): int => count(array_filter(
            new NodeFinder()->findInstanceOf($ctor->stmts ?? [], Assign::class),
            static fn (Assign $assign) => self::assignsThisPropertyNamed($assign, $name),
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
     * Is this an "absent" Optional construction — a raw `new Optional` OR Spatie's `Optional::create()`
     * factory? The null→Optional map reads the same either way, so every rule about it works on both forms
     * (a producer that adopts the preferred `Optional::create()` must not slip past the map detector).
     */
    public function isOptionalAbsentMarker(): bool
    {
        if ($this->node instanceof New_) {
            return ltrim((string) $this->newClassName(), '\\') === self::OPTIONAL;
        }

        return $this->node instanceof StaticCall
            && $this->node->class instanceof Name
            && ltrim($this->node->class->toString(), '\\') === self::OPTIONAL
            && $this->node->name instanceof Identifier
            && $this->node->name->toString() === 'create';
    }

    /**
     * Is this Optional construction a hand-rolled null→Optional MAP — the fallback arm of a ternary
     * (`$x === null ? Optional::create() : Foo::from($x)`) or the right of a `??` (`expr() ?? Optional::create()`)?
     * That maps "absent" onto `Optional` at a producer, which belongs in ONE named factory
     * (`Foo::optionalOrMissing($x)`), not re-derived at every call site. A `new Optional` used as a PARAMETER
     * DEFAULT (`T | Optional $x = new Optional`) or a bare `return Optional::create()` is the correct shape, NOT this.
     */
    public function isOptionalNullFallback(): bool
    {
        if (! $this->isOptionalAbsentMarker()) {
            return false;
        }

        $parent = $this->node->getAttribute('parent');

        if ($parent instanceof Ternary) {
            if ($parent->if !== $this->node && $parent->else !== $this->node) {
                return false;
            }

            // ONLY a null-guard ternary is the null→Optional map. A boolean-guarded ternary
            // (`$stockpile->relationLoaded('warehouse') ? $stockpile->warehouse : Optional::create()`) is a
            // legitimate lazy-relation include, not a hand-rolled map — the short-ternary `$x ?: Optional` is a
            // null-ish guard and counts.
            return $parent->if === null || new AstNode($parent->cond)->isNullComparison();
        }

        return $parent instanceof Coalesce && $parent->right === $this->node;
    }

    /**
     * Is this Optional construction the fallback INSIDE the shared conversion home itself — the ONE named
     * factory the {@see isOptionalNullFallback} rule tells producers to create, so it must not flag itself?
     * Two shapes qualify:
     *   • the ternary form whose other arm hydrates `self`/`static` (`$x === null ? Optional::create() :
     *     static::from($x)`) — the factory maps a generic payload onto ITS OWN type; and
     *   • the coalesce form whose left is a bare PARAMETER passthrough (`fromNullable($value) => $value ??
     *     Optional::create()`) — a generic null→Optional converter for values a Data trait can't serve
     *     (enums, scalars), the one designated home for the map.
     * A producer instead hydrates a concrete OTHER type (`OptRange::from(...)`) or coalesces OWN STATE
     * (`$this->memo ?? Optional::create()`) — those re-derive the map at the producer and still fire.
     */
    public function isSharedOptionalFactory(): bool
    {
        if (! $this->isOptionalAbsentMarker()) {
            return false;
        }

        $parent = $this->node->getAttribute('parent');

        if ($parent instanceof Coalesce) {
            return $parent->right === $this->node && $this->coalescesEnclosingParameter($parent->left);
        }

        if (! $parent instanceof Ternary) {
            return false;
        }

        $present = $parent->if === $this->node ? $parent->else : $parent->if;

        return $present instanceof StaticCall
            && $present->class instanceof Name
            && in_array($present->class->toString(), ['self', 'static'], true)
            && $present->name instanceof Identifier
            && $present->name->toString() === 'from';
    }

    /**
     * Is this coalesce left operand a bare reference to a PARAMETER of the enclosing function — the passthrough
     * tell of a generic converter (`fromNullable($value) => $value ?? Optional::create()`), as opposed to own
     * state (`$this->memo`) or a producer's `Type::from(...)` that re-derives the map?
     */
    private function coalescesEnclosingParameter(Node $left): bool
    {
        if (! $left instanceof Variable || ! is_string($left->name)) {
            return false;
        }

        $function = $this->enclosingFunction();

        if ($function === null) {
            return false;
        }

        return array_any(
            $function->getParams(),
            static fn (Param $param): bool => $param->var instanceof Variable && $param->var->name === $left->name,
        );
    }

    /**
     * Is this `new Optional` one a static factory call could REPLACE — i.e., in a runtime expression, not a
     * constant-expression context? A parameter/property DEFAULT (`T | Optional $x = new Optional`) and an
     * attribute argument MUST stay `new` (a static call is illegal there); everywhere else, prefer Spatie's
     * built-in `Optional::create()` factory over the raw constructor.
     */
    public function isReplaceableNewOptional(): bool
    {
        if (! $this->node instanceof New_ || ltrim((string) $this->newClassName(), '\\') !== self::OPTIONAL) {
            return false;
        }

        $parent = $this->node->getAttribute('parent');

        if ($parent instanceof Param || $parent instanceof PropertyItem) {
            return false; // a default value — must stay `new Optional`
        }

        return $this->walkUp(static fn (Node $node): bool => $node instanceof Attribute) === null;
    }

    /**
     * The names of this Data class's PUBLIC fields typed `T|Optional` — the ones Spatie OMITS from the wire
     * when absent. A hand-written mirror may legitimately leave these out, so the mirror comparison must not
     * penalise their absence. Complements the generic {@see AstNode::publicFieldNames} with the Optional fact.
     *
     * @return list<string>
     */
    public function optionalPublicFieldNames(): array
    {
        $names = [];

        foreach ($this->fields() as $field) {
            if ($field->isPublic && self::typeIncludesOptional($field->type)) {
                $names[] = $field->name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Is EVERY promoted constructor property of this Data class typed `T|Optional` — an all-optional DTO
     * whose type promises that nothing is ever present? The `Optional` sibling of the all-nullable smell:
     * the tell that the absence belongs on the CONTAINER field where this object is used (`Type|Optional`),
     * not scattered across these leaves (which lets the object exist half-formed).
     */
    public function everyConstructorParamOptional(): bool
    {
        $promoted = $this->fromConstructor(
            static fn (ClassMethod $ctor): array => array_filter($ctor->params, static fn (Param $p): bool => $p->flags !== 0),
        );

        if (! is_array($promoted) || $promoted === []) {
            return false;
        }

        foreach ($promoted as $param) {
            if (! self::typeIncludesOptional($param->type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Is this `get` property hook on a `Data` class MISSING `#[Computed]`? A get-only virtual property that
     * is not `#[Computed]` is read by Spatie as a hydration INPUT — it expects the key in `::from()` (which a
     * get-only hook can't receive) and mis-serializes, so the class crashes or silently drops the field.
     */
    public function hookMissingComputed(): bool
    {
        if (! $this->node instanceof PropertyHook) {
            return false;
        }

        if (! $this->codebase->extends($this->enclosingClassName(), self::DATA)) {
            return false;
        }

        $property = $this->node->getAttribute('parent');

        if (! $property instanceof Property || self::carriesComputed($property)) {
            return false;
        }

        // Only a get-ONLY hook is a purely computed value; a property that also has a `set` hook is a real
        // read-write field Spatie can legitimately hydrate, so it's not the missing-`#[Computed]` trap.
        foreach ($property->hooks as $hook) {
            if ($hook->name->toString() === 'set') {
                return false;
            }
        }

        return true;
    }

    /**
     * Does the property already carry a `#[Computed]` attribute (short or fully-qualified)?
     */
    private static function carriesComputed(Property $property): bool
    {
        foreach ($property->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = ltrim($attr->name->toString(), '\\');

                if ($name === 'Computed' || str_ends_with($name, '\\Computed')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Is this class a PAGE OBJECT missing `#[TypeScript]`? A page object travels back in a response and is
     * read by a `.vue` page — so it MUST generate a frontend type; without `#[TypeScript]` the page consumes
     * an untyped `any` and the whole page/prop contract goes unchecked. (A nested leaf `Data` need not be
     * annotated on its own — the transformer reaches it through the annotated page — so this gates on
     * page-object-ness, not every `Data`.)
     */
    public function pageObjectMissingTypeScript(): bool
    {
        $class = $this->enclosingClass();

        return $class instanceof \PhpParser\Node\Stmt\ClassLike
            && $this->isPageObject()
            && ! self::classHasAttribute($class, 'TypeScript');
    }

    /**
     * Is this field on a `#[TypeScript]` Data class typed as a nested `Data` CLASS that itself LACKS
     * `#[TypeScript]`? The typescript-transformer then emits the property as `undefined` (a silent hole in
     * the generated contract). Exempts a `#[Hidden]` field (off the wire) and one with a
     * `#[TypeScriptType]`/`#[LiteralTypeScriptType]` override (its wire shape is declared on the property).
     * A nested ENUM is NOT flagged — the transformer's enum collector auto-generates a type for any enum it
     * meets, tagged or not, so an untagged enum is never `undefined`. Only judges a nested Data PARSED in
     * this codebase — a vendor type we can't see is left alone.
     */
    public function nestedWireTypeMissingTypeScript(): bool
    {
        $class = $this->enclosingClass();

        if (! $class instanceof \PhpParser\Node\Stmt\ClassLike
            || ! $this->codebase->extends($this->enclosingClassName(), self::DATA)
            || ! self::classHasAttribute($class, 'TypeScript')) {
            return false;
        }

        $field = $this->asField();

        if ($field === null
            || ! $field->isPublic
            || $field->hasAttribute('Hidden')
            || $field->hasAttribute('TypeScriptType', 'LiteralTypeScriptType')) {
            return false;
        }

        $nested = $this->nestedWireTypeFqcn();

        if ($nested === null || ! $this->codebase->extends($nested, self::DATA)) {
            return false; // a scalar, an array, or an ENUM (auto-generated by the enum collector) — no hole
        }

        $nestedDeclaration = $this->codebase->declarationMatch($nested);

        return $nestedDeclaration !== null
            && $nestedDeclaration->node instanceof \PhpParser\Node\Stmt\ClassLike
            && ! self::classHasAttribute($nestedDeclaration->node, 'TypeScript');
    }

    /**
     * The FQCN of a field's on-the-wire nested type — its `#[DataCollectionOf(X)]` element, else the sole class of its declared type (unwrapping `?T` / `T|null` / `T|Optional`). Null for a scalar/array/multi-class type. The repent reads it to find the class to stamp.
     */
    public function nestedWireTypeFqcn(): ?string
    {
        if (! $this->node instanceof Param && ! $this->node instanceof Property) {
            return null;
        }

        return self::dataCollectionOfElement($this->node) ?? self::soleClassNameOfType($this->node->type);
    }

    /**
     * The element FQCN named by a `#[DataCollectionOf(X::class)]` on this field, or null.
     */
    private static function dataCollectionOfElement(Param|Property $node): ?string
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = ltrim($attr->name->toString(), '\\');

                if ($name === 'DataCollectionOf' || str_ends_with($name, '\\DataCollectionOf')) {
                    $arg = $attr->args[0]->value ?? null;

                    if ($arg instanceof ClassConstFetch && $arg->class instanceof Name) {
                        return ltrim($arg->class->toString(), '\\');
                    }
                }
            }
        }

        return null;
    }

    /**
     * The single class FQCN of a type, unwrapping `?T` and dropping `null`/`Optional` from a union; null for a scalar (an `Identifier`), an array, or a multi-class union.
     */
    private static function soleClassNameOfType(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return self::soleClassNameOfType($type->type);
        }

        if ($type instanceof Name) {
            return ltrim($type->toString(), '\\');
        }

        if ($type instanceof UnionType) {
            $classes = [];

            foreach ($type->types as $member) {
                if (! $member instanceof Name) {
                    continue; // an `Identifier` null / a scalar member — skip
                }

                $name = ltrim($member->toString(), '\\');

                if (strtolower($name) !== 'null' && $name !== self::OPTIONAL) {
                    $classes[] = $name;
                }
            }

            return count($classes) === 1 ? $classes[0] : null;
        }

        return null;
    }

    /**
     * Is this field TYPED as `DataCollection` (`DataCollection $x`, `DataCollection|null`, or a hook of that
     * type) on a Data class? A collection property must be `array` (preferred) or `Collection` with
     * `#[DataCollectionOf(X)]`; typing it `DataCollection` emits malformed TypeScript (`undefined<number, X>`)
     * and skips the element-typed hydration/validation `#[DataCollectionOf]` drives.
     */
    public function propertyTypedAsDataCollection(): bool
    {
        if (! $this->codebase->extends($this->enclosingClassName(), self::DATA)) {
            return false;
        }

        $type = match (true) {
            $this->node instanceof Param => $this->node->type,
            $this->node instanceof Property => $this->node->type,
            default => null,
        };

        return self::typeMentionsDataCollection($type);
    }

    /**
     * Does this type declaration name `DataCollection` (bare or in a nullable union)?
     */
    private static function typeMentionsDataCollection(?Node $type): bool
    {
        if ($type instanceof Name) {
            return ltrim($type->toString(), '\\') === self::DATA_COLLECTION;
        }

        if ($type instanceof NullableType) {
            return self::typeMentionsDataCollection($type->type);
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Name && ltrim($member->toString(), '\\') === self::DATA_COLLECTION) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Is this a nullable NESTED-OBJECT field (`T | null` / `?T`, T a `Data` subclass or enum) on a
     * `#[TypeScript]` Data class — where `T | Optional` belongs instead? On the wire a `?T = null` ships
     * `"field": null`; `Optional` OMITS it, which is what the frontend's `x?.` reads for "absent". A
     * nullable SCALAR is left alone (an explicit `null` can be meaningful); a nested object that's null
     * almost always means "not there", i.e. `Optional`.
     */
    public function nullableWireObject(): bool
    {
        $class = $this->enclosingClass();

        if (! $class instanceof \PhpParser\Node\Stmt\ClassLike
            || ! $this->codebase->extends($this->enclosingClassName(), self::DATA)
            || ! self::classHasAttribute($class, 'TypeScript')) {
            return false;
        }

        // A HOOKED (computed) property is not a hydration slot — `Optional` means "key absent
        // from the payload", but a get-hook derives its value and hydration never writes it
        // (spatie even force-evaluates an Optional-typed hook via isset() during ::from(),
        // before non-promoted state exists). `T | null` is the honest shape there (#394, #395).
        if ($this->isHookedProperty()) {
            return false;
        }

        $type = match (true) {
            $this->node instanceof Param => $this->node->type,
            $this->node instanceof Property => $this->node->type,
            default => null,
        };
        $inner = self::nullableClassName($type);

        return $inner !== null && ($this->codebase->extends($inner, self::DATA) || $this->codebase->isEnum($inner));
    }

    /**
     * Does $class carry an attribute named $short (by short name or fully-qualified)?
     */
    private static function classHasAttribute(\PhpParser\Node\Stmt\ClassLike $class, string $short): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = ltrim($attr->name->toString(), '\\');

                if ($name === $short || str_ends_with($name, '\\' . $short)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The single class name T of a `T | null` / `?T` type — else null (not nullable, or not one class).
     */
    private static function nullableClassName(?Node $type): ?string
    {
        if ($type instanceof NullableType && $type->type instanceof Name) {
            return ltrim($type->type->toString(), '\\');
        }

        if (! $type instanceof UnionType) {
            return null;
        }

        $classes = [];
        $nullable = false;

        foreach ($type->types as $member) {
            // `null` arrives as an Identifier or a Name depending on how the union was written; both
            // say the same thing, so they are one branch.
            if (($member instanceof Identifier || $member instanceof Name) && strtolower($member->toString()) === 'null') {
                $nullable = true;
            } elseif ($member instanceof Name) {
                $classes[] = ltrim($member->toString(), '\\');
            }
        }

        return $nullable && count($classes) === 1 ? $classes[0] : null;
    }

    /**
     * Does this type declaration union in the Spatie `Optional` marker (`T|Optional`)?
     */
    private static function typeIncludesOptional(?Node $type): bool
    {
        if (! $type instanceof UnionType) {
            return false;
        }

        foreach ($type->types as $member) {
            if ($member instanceof Name && ltrim($member->toString(), '\\') === self::OPTIONAL) {
                return true;
            }
        }

        return false;
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
     * Is this `::from()` fed an ARRAY LITERAL built in place — a hand-written field mapping
     * (`::from(['value' => $option->value, 'label' => $option->label])`) rather than the raw
     * item? That is a cross-shape TRANSFORMATION, not re-hydration: `::collect()` maps rows
     * through `from()` verbatim and cannot express the projection, so the loop/map is the
     * only place the mapping can live.
     */
    public function isInlineProjection(): bool
    {
        if (! $this->node instanceof StaticCall) {
            return false;
        }

        $first = $this->node->args[0] ?? null;

        return $first instanceof Arg && $first->value instanceof Array_;
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

    /**
     * The DESTINATION this value hydrates into — walking up from this node to the property-keyed item of an
     * enclosing single-argument `SomeData::from([...])` on a `Data` class, crossing at most one list-literal
     * wrapper (so an element of a `#[DataCollectionOf]` list resolves too). Null when this node is not in
     * such a hydration-argument position. The ONE walk every "auto-hydration would do this" detector shares.
     */
    public function hydrationSlot(): ?HydrationSlot
    {
        if (! $this->node instanceof Node) {
            return null;
        }

        [$key, $keyedItem, $valueInList] = $this->keyedHydrationItem($this->node);

        if ($key === null || ! $keyedItem instanceof ArrayItem) {
            return null;
        }

        $call = $this->soleFromCallOf($keyedItem);

        if ($call === null) {
            return null;
        }

        $owner = $this->constructedFrom($call);

        if ($owner === null || ! $this->codebase->extends($owner, self::DATA)) {
            return null;
        }

        $resolver = TypeResolver::forCodebase($this->codebase);
        $declared = $resolver->propertyTypeOf($owner, $key);
        $element = $resolver->collectionElementOf($owner, $key);

        return new HydrationSlot(
            ownerFqcn: $owner,
            property: $key,
            declaredType: $declared,
            isCollection: $element !== null,
            elementType: $element ?? $declared,
            valueInList: $valueInList,
            destHasCast: $this->propertyHasCast($owner, $key),
        );
    }

    /**
     * Is this `<recv>->value` node an enum unwrapped straight back into its OWN enum slot — the receiver
     * resolves to an enum, and the `::from([...])` property it feeds is typed as that SAME enum? Then
     * Spatie's enum cast re-hydrates the scalar into the enum, so the `->value` is a needless round-trip
     * (the mirror of {@see constructsNativeCastValue}: that constructs scalar→enum, this destructures
     * enum→scalar). The detector prefilters to a `->value` fetch; here we decide what the receiver IS.
     */
    public function isEnumUnwrapIntoItsOwnSlot(): bool
    {
        if (! $this->node instanceof PropertyFetch && ! $this->node instanceof NullsafePropertyFetch) {
            return false;
        }

        $function = $this->enclosingFunction();

        if ($function === null) {
            return false;
        }

        $enum = TypeResolver::forCodebase($this->codebase)->typeOf($this->node->var, $function, $this->enclosingClassName());

        if ($enum === null || ! $this->codebase->isEnum($enum)) {
            return false; // the receiver isn't an enum — `->value` is an ordinary member, not a backing unwrap
        }

        $slotType = $this->hydrationSlot()?->elementType ?? $this->forwardedEnumSlotType();

        return $slotType !== null && ltrim($slotType, '\\') === ltrim($enum, '\\');
    }

    /**
     * The destination property type for a `->value` that reaches its `::from()` ONE factory-method hop away
     * — `make() { return $this->build(['status' => $e->value]); }` where `build($attrs)` does
     * `SomeData::from($attrs)`. Traces the array through the single call it is passed into, resolves that
     * callee's body, and reads the type of the forwarded slot. Null when the array isn't forwarded straight
     * into a one-hop `::from`. Bounded to one hop on purpose (deeper chains stay uncaught rather than guessed).
     */
    private function forwardedEnumSlotType(): ?string
    {
        if (! $this->node instanceof Node) {
            return null;
        }

        [$key, $item] = $this->keyedHydrationItem($this->node);
        $array = $item instanceof ArrayItem ? $item->getAttribute('parent') : null;
        $arg = $array instanceof Array_ ? $array->getAttribute('parent') : null;

        if ($key === null || ! $arg instanceof Arg) {
            return null;
        }

        [$callee, $ownerClass] = $this->resolveCallee($arg->getAttribute('parent'));
        $param = $callee === null ? null : ($callee->params[$this->argPosition($arg)] ?? null);

        if (! $param instanceof Param || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
            return null;
        }

        foreach (new NodeFinder()->findInstanceOf($callee, StaticCall::class) as $from) {
            if (! ($from->name instanceof Identifier && $from->name->toString() === 'from' && $this->soleArgumentIsVariable($from, $param->var->name))) {
                continue;
            }

            $owner = $this->constructedFromInClass($from, $ownerClass);

            if ($owner !== null && $this->codebase->extends($owner, self::DATA)) {
                return TypeResolver::forCodebase($this->codebase)->propertyTypeOf($owner, $key);
            }
        }

        return null;
    }

    /**
     * The declared method a call resolves to, plus the FQCN of the class that owns it — for a
     * `$recv->method(...)` (receiver type resolved) or a `Class::method(...)`. `[null, null]` otherwise.
     *
     * @return array{0: ?\PhpParser\Node\Stmt\ClassMethod, 1: ?string}
     */
    private function resolveCallee(mixed $call): array
    {
        $function = $this->enclosingFunction();

        if ($function === null) {
            return [null, null];
        }

        if ($call instanceof MethodCall && $call->name instanceof Identifier) {
            $owner = TypeResolver::forCodebase($this->codebase)->typeOf($call->var, $function, $this->enclosingClassName());
        } elseif ($call instanceof StaticCall && $call->name instanceof Identifier && $call->class instanceof Name) {
            $class = $call->class->toString();
            $owner = in_array($class, ['self', 'static'], true) ? $this->enclosingClassName() : ltrim($class, '\\');
        } else {
            return [null, null];
        }

        $node = $owner === null ? null : $this->codebase->classNamed($owner)->node;

        return $node instanceof \PhpParser\Node\Stmt\ClassLike
            ? [$node->getMethod($call->name->toString()), $owner]
            : [null, null];
    }

    /**
     * The zero-based position of $arg among its call's real arguments.
     */
    private function argPosition(Arg $arg): int
    {
        $call = $arg->getAttribute('parent');
        $args = ($call instanceof MethodCall || $call instanceof StaticCall)
            ? array_values(array_filter($call->args, static fn ($a): bool => $a instanceof Arg))
            : [];

        return (int) array_search($arg, $args, true);
    }

    /**
     * Like {@see constructedFrom}, but resolving `self`/`static` against $ownerClass (the callee's own class).
     */
    private function constructedFromInClass(StaticCall $call, ?string $ownerClass): ?string
    {
        if (! $call->class instanceof Name) {
            return null;
        }

        $class = $call->class->toString();

        return in_array($class, ['self', 'static'], true) ? $ownerClass : ltrim($class, '\\');
    }

    /**
     * Walk up from $node to the nearest property-keyed `ArrayItem`, crossing at most one list-literal
     * wrapper. Returns `[key, item, valueInList]` — the string key, the keyed item, and whether $node sat
     * inside a list (an element of a collection). `[null, null, false]` when no such item is reachable.
     *
     * @return array{0: ?string, 1: ?ArrayItem, 2: bool}
     */
    private function keyedHydrationItem(Node $node): array
    {
        $crossed = 0;

        for ($current = $node; ; ) {
            $parent = $current->getAttribute('parent');

            if ($parent instanceof ArrayItem) {
                if ($parent->key instanceof String_) {
                    return [$parent->key->value, $parent, $crossed >= 1];
                }

                $current = $parent; // an unkeyed element of a list — keep climbing to its list

                continue;
            }

            if ($parent instanceof Array_) {
                if (++$crossed > 1) {
                    return [null, null, false]; // deeper than one list wrapper — not a simple slot
                }

                $current = $parent;

                continue;
            }

            return [null, null, false]; // any other parent before a key — not a hydration argument
        }
    }

    /**
     * The `Data::from(...)` call whose sole array argument is the array holding $keyedItem — either directly
     * (`Data::from([...])`) or through one local hop (`$a = [...]; Data::from($a)`, the array assigned to a
     * once-assigned local then passed straight in). Null otherwise.
     */
    private function soleFromCallOf(ArrayItem $keyedItem): ?StaticCall
    {
        $array = $keyedItem->getAttribute('parent');

        if (! $array instanceof Array_) {
            return null;
        }

        $parent = $array->getAttribute('parent');

        if ($parent instanceof Arg) {
            return $this->fromCallOwningArgument($parent);
        }

        if ($parent instanceof Assign && $parent->var instanceof Variable && is_string($parent->var->name)) {
            return $this->fromCallForLocal($parent->var->name);
        }

        return null;
    }

    /**
     * The `Data::from(...)` call whose SOLE argument is $arg, or null when $arg isn't the one argument of a
     * `from` static call.
     */
    private function fromCallOwningArgument(Arg $arg): ?StaticCall
    {
        $call = $arg->getAttribute('parent');

        if (! $call instanceof StaticCall || ! $call->name instanceof Identifier || $call->name->toString() !== 'from') {
            return null;
        }

        $args = array_values(array_filter($call->args, static fn ($a): bool => $a instanceof Arg));

        return count($args) === 1 && $args[0] === $arg ? $call : null;
    }

    /**
     * A `Data::from($local)` in the enclosing function whose sole argument is the once-assigned local $var —
     * the one-hop indirection where the source array is built into a variable and passed straight in. The
     * single-assignment guard keeps a reassigned variable from tracing through the wrong array.
     */
    private function fromCallForLocal(string $var): ?StaticCall
    {
        $function = $this->enclosingFunction();

        if ($function === null || $this->assignmentCount($function, $var) !== 1) {
            return null;
        }

        foreach (new NodeFinder()->findInstanceOf($function, StaticCall::class) as $call) {
            if ($call->name instanceof Identifier
                && $call->name->toString() === 'from'
                && $this->soleArgumentIsVariable($call, $var)) {
                return $call;
            }
        }

        return null;
    }

    private function assignmentCount(Node $function, string $var): int
    {
        return count(array_filter(
            new NodeFinder()->findInstanceOf($function, Assign::class),
            static fn (Assign $assign): bool => $assign->var instanceof Variable && $assign->var->name === $var,
        ));
    }

    private function soleArgumentIsVariable(StaticCall $call, string $var): bool
    {
        $args = array_values(array_filter($call->args, static fn ($a): bool => $a instanceof Arg));

        return count($args) === 1 && $args[0]->value instanceof Variable && $args[0]->value->name === $var;
    }

    /**
     * The `Data` class a `::from()` call constructs — resolving `self`/`static` to its enclosing class.
     */
    private function constructedFrom(StaticCall $call): ?string
    {
        if (! $call->class instanceof Name) {
            return null;
        }

        $class = $call->class->toString();

        return in_array($class, ['self', 'static'], true)
            ? $this->codebase->wrap($call, $this->file)->enclosingClassName()
            : ltrim($class, '\\');
    }

    /**
     * Does property $prop on $owner carry a cast-injecting attribute (so the object form is intended)?
     */
    private function propertyHasCast(string $owner, string $prop): bool
    {
        foreach ($this->codebase->classNamed($owner)->fields() as $field) {
            if ($field->name === $prop) {
                return $field->hasAttribute('WithCast', 'WithCastAndTransformer', 'WithCastable');
            }
        }

        return false;
    }

    /**
     * Is this `X::from(...)` called with exactly one ARRAY-LITERAL argument — the redundant nested-hydration
     * shape (`X::from(['a' => 1])`), as opposed to an object/model conversion (`X::from($model)`)?
     */
    public function fromArgIsArrayLiteral(): bool
    {
        $args = $this->arguments();

        return count($args) === 1 && $args[0]->value instanceof Array_;
    }

    /**
     * The class the value at this hydration slot is CONSTRUCTED as — this node's own `X::from(...)` receiver,
     * resolving `self`/`static`. The N1 exact-match compares this against the slot's element type.
     */
    public function constructedClass(): ?string
    {
        return $this->node instanceof StaticCall ? $this->constructedFrom($this->node) : null;
    }

    /**
     * Does this `X::from([...])` fill a slot the parent `::from` would AUTO-BUILD from the same array — the
     * destination's element type is EXACTLY the constructed class (no subtype into a supertype slot), and the
     * value's list-vs-single shape matches the slot's collection-vs-single shape? Then the wrapper is ceremony.
     */
    public function hydratesAnAutoBuiltSlot(): bool
    {
        $slot = $this->hydrationSlot();

        if ($slot === null || $slot->valueInList !== $slot->isCollection) {
            return false;
        }

        return $slot->elementType !== null && $slot->elementType === $this->constructedClass();
    }

    /**
     * Does this node's hydration slot carry a `#[WithCast]`-family attribute — where the object form may be
     * intended, so the nested construction is not redundant?
     */
    public function hydrationSlotHasCast(): bool
    {
        return $this->hydrationSlot()?->destHasCast ?? false;
    }

    /**
     * The static factory an `array_map(...)` at this node maps over its list — `array_map(E::for(...), $xs)`
     * or `array_map(fn ($x) => E::for($x), $xs)`. Null when this isn't an `array_map` whose callback is a
     * single static-factory call. {@see FactoryRef} carries whether the callback reaches beyond its own item.
     */
    public function mappedFactory(): ?FactoryRef
    {
        if ($this->callName() !== 'array_map') {
            return null;
        }

        $callable = $this->arguments()[0]->value ?? null;

        if ($callable === null) {
            return null;
        }

        [$staticCall, $closesOver] = $this->factoryCallOf($callable);

        if (! $staticCall instanceof StaticCall || ! $staticCall->class instanceof Name || ! $staticCall->name instanceof Identifier) {
            return null;
        }

        $class = $staticCall->class->toString();
        $class = in_array($class, ['self', 'static'], true) ? ($this->enclosingClassName() ?? $class) : ltrim($class, '\\');

        $function = $this->enclosingFunction();
        $returns = $function === null
            ? null
            : TypeResolver::forCodebase($this->codebase)->typeOf($staticCall, $function, $this->enclosingClassName());

        return new FactoryRef($class, $staticCall->name->toString(), $returns, $closesOver);
    }

    /**
     * Does this `array_map(...)` fill a `#[DataCollectionOf(E)]` slot by DERIVING each element through a
     * factory that should live in a cast — `array_map(E::for(...), $xs)` where `E::for` returns the element
     * type `E` (a `Data`), the factory is NOT the `from`/`collect` auto-hydration entrypoint, and the
     * callback doesn't close over context a per-item cast couldn't reach?
     */
    public function mappedFactoryDerivesElement(): bool
    {
        $slot = $this->hydrationSlot();
        $factory = $this->mappedFactory();

        if ($slot === null || ! $slot->isCollection || $slot->elementType === null || $factory === null) {
            return false;
        }

        if (in_array($factory->method, ['from', 'collect'], true) || $factory->closesOverContext) {
            return false;
        }

        return $this->extendsData($factory->class) && $factory->returnsType === $slot->elementType;
    }

    private function extendsData(string $class): bool
    {
        return $this->codebase->extends($class, self::DATA);
    }

    /**
     * The static call an `array_map` callback invokes, and whether the callback reaches beyond its own
     * parameters — a first-class callable (`E::for(...)`), or an arrow/closure whose body is a single static
     * call. `[null, false]` when the callback post-processes the result or isn't a single static call.
     *
     * @return array{0: ?StaticCall, 1: bool}
     */
    private function factoryCallOf(Node $callable): array
    {
        if ($callable instanceof StaticCall) {
            return [$callable, false]; // first-class callable `E::for(...)` — no bound context
        }

        if ($callable instanceof ArrowFunction && $callable->expr instanceof StaticCall) {
            return [$callable->expr, $this->referencesBeyond($callable->expr, $callable)];
        }

        if ($callable instanceof Closure && ! empty($callable->uses)) {
            return [null, true]; // a `use (...)` import is captured context a per-item cast can't reach
        }

        if ($callable instanceof Closure) {
            $return = $this->soleReturn($callable);

            return $return instanceof StaticCall ? [$return, $this->referencesBeyond($return, $callable)] : [null, false];
        }

        return [null, false];
    }

    /**
     * Does $call reference a variable that isn't one of $fn's parameters (or `$this`) — captured context the
     * mapping closes over, which a per-item cast can't be handed?
     */
    private function referencesBeyond(StaticCall $call, Node $fn): bool
    {
        $params = [];

        foreach ($fn->getParams() as $param) {
            if ($param->var instanceof Variable && is_string($param->var->name)) {
                $params[$param->var->name] = true;
            }
        }

        foreach (new NodeFinder()->findInstanceOf($call, Variable::class) as $variable) {
            if (is_string($variable->name) && ! isset($params[$variable->name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The sole `return <expr>` value of a closure body, or null when it isn't a single return statement.
     */
    private function soleReturn(Closure $closure): ?Node
    {
        $returns = new NodeFinder()->findInstanceOf($closure->stmts, \PhpParser\Node\Stmt\Return_::class);

        return count($returns) === 1 ? $returns[0]->expr : null;
    }

    /**
     * Does this node CONSTRUCT a natively-cast value — an enum from its backing scalar (`Enum::from($x)`), a
     * `new DateTime*(...)`, or `Carbon::parse($x)` — the shapes Spatie hydrates straight from the raw value?
     */
    public function constructsNativeCastValue(): bool
    {
        if ($this->staticCallMethod() === 'from') {
            $class = $this->staticCallClass();

            return $class !== null && $this->codebase->isEnum(ltrim($class, '\\'));
        }

        if ($this->staticCallMethod() === 'parse') {
            $class = $this->staticCallClass();

            return $class !== null && in_array(ClassName::short($class), self::NATIVE_CAST_TYPES, true);
        }

        $new = $this->newClassName();

        return $new !== null && $this->castsNativelyPublic(ltrim($new, '\\'));
    }

    /**
     * Is this construction called with exactly one argument — no timezone / format second argument that the
     * native cast wouldn't reproduce?
     */
    public function hasSingleArgument(): bool
    {
        return count($this->arguments()) === 1;
    }

    /**
     * Does this node's hydration slot accept the natively-cast value being constructed — the SAME enum for an
     * `Enum::from(...)`, or a (non-enum) date/time slot for a date/time construction? A cross-kind mismatch
     * (enum construction into a date slot, or vice versa) is not redundant and is spared.
     */
    public function slotAcceptsNativeCast(): bool
    {
        $slot = $this->hydrationSlot();

        if ($slot === null || $slot->declaredType === null) {
            return false;
        }

        $enumClass = $this->staticCallMethod() === 'from' ? ltrim((string) $this->staticCallClass(), '\\') : '';

        if ($enumClass !== '' && $this->codebase->isEnum($enumClass)) {
            return $slot->declaredType === $enumClass; // enum construction → exact enum slot
        }

        return $this->castsNativelyPublic($slot->declaredType) && ! $this->codebase->isEnum($slot->declaredType);
    }

    /**
     * Public wrapper over {@see castsNatively} for detectors that gate a destination type.
     */
    public function castsNativelyPublic(string $type): bool
    {
        return $this->castsNatively($type);
    }

    /**
     * Is this `SomeData::from([...])` a HAND-WRITTEN name remap — every value a bare `$src['snake_key']` off
     * the SAME source array, and every key the camelCase of that snake key (with at least one real rename)?
     * A single class-level `#[MapInputName(SnakeCaseMapper::class)]` + `::from($src)` replaces the whole
     * translation. Any transformed value, a mixed source, or a key that isn't a pure snake→camel of its
     * fetch disqualifies it — so a deliberate, non-mechanical mapping is spared.
     */
    public function isHandKeyRemap(): bool
    {
        if ($this->staticCallMethod() !== 'from' || ! $this->onDataClass() || ! $this->fromArgIsArrayLiteral()) {
            return false;
        }

        $array = $this->arguments()[0]->value;

        if (! $array instanceof Array_ || count($array->items) < 2) {
            return false;
        }

        $source = null;
        $renamed = false;

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem
                || ! $item->key instanceof String_
                || ! $item->value instanceof ArrayDimFetch
                || ! $item->value->var instanceof Variable
                || ! is_string($item->value->var->name)
                || ! $item->value->dim instanceof String_) {
                return false; // a transformed value or a non-`$src['key']` fetch — not a mechanical remap
            }

            $source ??= $item->value->var->name;

            if ($item->value->var->name !== $source || self::snake($item->key->value) !== $item->value->dim->value) {
                return false; // a different source, or a key that isn't the snake→camel of its fetch
            }

            $renamed = $renamed || $item->key->value !== $item->value->dim->value;
        }

        return $renamed;
    }

    /**
     * The snake_case form of a camelCase identifier — the SnakeCaseMapper's transform.
     */
    private static function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }

    /**
     * Is this `X::from(...)->toArray()` (or `new X(...)->toArray()`) a redundant ROUND-TRIP — its result
     * sits in a `::from` slot typed `X` (a nested `Data`, or a `#[DataCollectionOf(X)]` element) that
     * re-hydrates the array right back into `X`? Build → array → build. Drop the `->toArray()`: the slot
     * takes the object (or the source array) directly.
     */
    public function isRedundantToArrayRoundtrip(): bool
    {
        if (! $this->node instanceof MethodCall
            || ! $this->node->name instanceof Identifier
            || $this->node->name->toString() !== 'toArray') {
            return false;
        }

        $function = $this->enclosingFunction();

        if ($function === null) {
            return false;
        }

        // The receiver's real type — a `Data` object, however it was built (`$d`, `X::from(...)`, `new X`).
        $receiver = TypeResolver::forCodebase($this->codebase)->typeOf($this->node->var, $function, $this->enclosingClassName());

        if ($receiver === null || ! $this->extendsData($receiver)) {
            return false;
        }

        $slot = $this->hydrationSlot();

        return $slot !== null && $slot->elementType === $receiver;
    }
}
