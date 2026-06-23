<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Contracts\ParameterizedRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use PhpParser\Node;
use PhpParser\NodeFinder;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileImports;
use PhpParser\ParserFactory;

/**
 * Flag a type union by width — 2 members warn, 3+ sin (configurable). `array |
 * string | null`, `int | float | string | null`. A value with three-plus shapes
 * is under-modelled: it pushes "what is this really?" onto every caller. When
 * the union is value-or-nothing (it includes null), the answer is an `Option`
 * (the null becomes the Option's absence, the rest its generic); otherwise a
 * small value object or a single type.
 */
#[IntroducedIn('1.81.0')]
class WideUnionTypeProphet extends PhpCommandment implements ParameterizedRepenter, NeedsCodebaseIndex
{
    private const DEFAULT_WARN_AT = 2;

    private const DEFAULT_SIN_AT = 3;

    /** At/below this implementer-count an interface is "narrow" enough to narrow TO. */
    private const DEFAULT_NARROW_CAP = 6;

    /**
     * Interfaces too BROAD to narrow a union to — retyping `A | B` to one of
     * these silently WIDENS the type and loses precision (#62 critical caveat).
     * Matched by short name; framework contract namespaces are refused wholesale.
     */
    private const OVER_BROAD_INTERFACES = [
        'stringable', 'jsonserializable', 'serializable', 'countable',
        'iteratoraggregate', 'iterator', 'traversable', 'arrayaccess',
        'arrayable', 'jsonable', 'responsable', 'htmlable', 'renderable',
    ];

    private const OVER_BROAD_NAMESPACES = [
        'Spatie\\LaravelData\\Contracts\\',
        'Illuminate\\Contracts\\',
    ];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    /** Builtin union members that map cleanly to a php-types `T` case. */
    private const T_MAP = [
        'array' => 'Array', 'string' => 'String', 'int' => 'Int', 'float' => 'Float',
        'bool' => 'Bool', 'object' => 'Object', 'iterable' => 'Iterable', 'callable' => 'Callable',
    ];

    public function description(): string
    {
        return 'Avoid wide type unions — model value-or-nothing as an Option';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    /**
     * The Union/Option primitives this prophet recommends legitimately hold a
     * union internally, so they must never flag themselves. Resolved from the
     * configured `support_namespace` (where the scaffold generates them).
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        $namespace = trim((string) $this->config('support_namespace', 'App\\Support'), '\\');

        if ($namespace === '') {
            return [];
        }

        return array_map(
            static fn (string $name): string => $namespace . '\\' . $name,
            ['Union', 'ScalarUnion', 'UnionCast', 'Option', 'ScalarOption'],
        );
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A parameter, return, or property type unions two or more members '
                . '(`array | string | null`) — an under-modelled value that forces '
                . 'every caller to re-decide what it is.'
            )
            ->leaveWhen(
                'It is a genuinely open scalar value (a config primitive) where any '
                . 'modelling would be artificial — and even then, prefer wrapping the '
                . 'absence in an Option. Also exempt inside an `#[Attribute]` class, '
                . 'where constructor args must be constant expressions (an Option '
                . 'cannot live there).'
            )
            ->whenUnsure(
                'If the union includes null, it is value-or-nothing → `Option<rest>`. '
                . 'If the members are classes that are one concept, give them a shared '
                . 'interface they all implement (introduce one if absent) and type as '
                . 'that. If several shapes are really one concept, make a value object. '
                . 'If it is two shapes that should be one, pick one.'
            );
    }

    public function detailedDescription(): string
    {
        $warnAt = $this->warnThreshold();
        $sinAt = $this->sinThreshold();

        return <<<SCRIPTURE
A type union is a value nobody has modelled — `array | string | null` says "it
might be one of these, you figure it out", and every caller re-derives what it
actually is. Almost always it is really value-or-nothing, or one concept wearing
several disguises. The rule graduates by the strongest signal — does it include
`null`?

  - includes `null`, {$sinAt}+ members  → SIN (value-or-nothing → `Option<rest>`,
    the one high-confidence fix; this blocks)
  - no `null` (always present, one-of-N) → WARNING (ad-hoc polymorphism — what
    PHP unions are FOR; consider a `Union` sum type / value object, advisory)
  - any union of {$warnAt}+ members below those bars → WARNING

A null-free union is never a blocking sin: when every shape is always present, a
union is one way to spell polymorphism. But when the members are CLASSES that are
really ONE CONCEPT (a leaf condition vs a nested group), the cleanest fix is a
shared interface they all implement, typed as that interface — zero wrapping, and
the `instanceof A || instanceof B` chains collapse to one. "They share no
interface" is a reason to consider INTRODUCING one, not to leave the union. The
blocker is reserved for the value-or-nothing case, where `Option` is the clear
answer.

Bad:
    Option | array | string | null \$isVisibleRule = null,   // (and a contradiction)
    array | string | null \$isVisibleRule = null,            // 3+ → sin
    string | int \$value,                                    // 2 → warning

Good — value-or-nothing is an Option (the null IS the absence):
    /** @var Option<array|string> */
    Option \$isVisibleRule,

Good — an all-scalar union has a ready-made home:
    ScalarUnion  \$value,    // string|int|float|bool      (always present)
    ScalarOption \$value,    // string|int|float|bool|null  (the null is absence)

Good — one concept wearing disguises is a value object:
    VisibilityRule \$isVisibleRule,

Good — a class union that is one concept is a shared interface:
    ResourceFilterNode \$node,   // ResourceFilterCondition | ResourceFilterGroup,
                                 // both implement ResourceFilterNode — no wrapping

WHAT FIRES — a native type or a `@param`/`@return`/`@var` docblock type whose
TOP-LEVEL union has >= {$warnAt} members (a union INSIDE a generic, like
`Option<array|string>`, does not count — that is correctly modelled).

WHAT DOES NOT — a simple nullable in EITHER syntax (`?T` AND `T | null` are the
same type, both exempt), a union nested inside a generic, a union inside an
`#[Attribute]` class (its constructor args must be constant expressions, so an
Option/Union can never live there — the suggestion is unactionable), a union on
a method marked `#[\Override]` (the signature is inherited — not yours to
change), the `Arrayable | array` typed-or-raw input contract (both members
describe the SAME data — one hydrated, one the plain array it serialises to —
so neither Option nor a Union sum type applies; collapsing it breaks every call
site), the Laravel render-or-redirect controller idiom
(`View | RedirectResponse`, `Response | RedirectResponse`, … — a controller
action that conditionally renders OR redirects; it is the framework contract,
not under-modelled polymorphism, and cannot collapse to one type or an Option),
or — when the warning band is disabled — a union below the sin
threshold. A 3+ union that
includes null is NOT a simple nullable: it still wraps two-or-more real shapes,
so it fires (and the null says the fix is `Option<rest>`).

Configure via:

    Backend\WideUnionTypeProphet::class => [
        'warn_at_types' => {$warnAt},   // 0 (or warnings_enabled => false) disables warnings
        'sin_at_types'  => {$sinAt},
        'support_namespace' => 'App\\\\Support',  // where Union & UnionCast live, for the auto-fix imports
    ],

AUTO-FIX — a null-free union on a Spatie Data property whose every member is a
builtin (array/string/int/…) is mechanically fixable: `repent` retypes it to
`Union` and adds `#[WithCastAndTransformer(UnionCast::class, allowed: [T::…])]`,
reading the allowed types straight from the union (no input needed). Set
`support_namespace` so the `Union`/`UnionCast` imports are added for you. A union
with a class member (`Money | string`) or with `null` is left for the human.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnAt = $this->warnThreshold();
        $sinAt = $this->sinThreshold();
        $floor = $warnAt > 0 ? min($warnAt, $sinAt) : $sinAt;
        $sins = [];
        $warnings = [];
        $flaggedLines = [];

        // Line regions where a wide union is UNACTIONABLE, so flagging is noise:
        //  - inside an `#[Attribute]` class (ctor args must be constant
        //    expressions — an Option/Union cannot live there);
        //  - on a method marked `#[\Override]` (the signature is inherited from
        //    an interface/base, so the type is not the author's to change).
        $exemptRanges = [
            ...$this->attributeClassRanges($ast),
            ...$this->overrideMethodRanges($ast),
        ];

        // Null-free union properties on a Spatie Data class can be rewritten to a
        // UnionCast-backed `Union` — keyed by union node id → its `T` members.
        $fixable = $this->fixableUnionFields($ast);

        // For the #62 shared-interface narrowing, resolve member FQCNs against
        // the file's namespace + imports.
        $namespace = FileImports::namespace($ast);
        $uses = FileImports::of($ast);

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\UnionType::class) as $union) {
            // `T | null` is a simple nullable — semantically identical to `?T`,
            // which is exempt. Flagging one syntax but not the other is
            // inconsistent (and punishes any house style that mandates the
            // spelled-out form). A 3+ union that includes null is different: it
            // still wraps two-or-more real shapes, so it stays flagged.
            if ($this->isSimpleNullableNative($union)) {
                continue;
            }

            // `Arrayable | array` is the typed-or-raw input contract, not an
            // under-modelled value — exempt it.
            if ($this->isArrayableConvenienceUnion($union)) {
                continue;
            }

            // `View | RedirectResponse` (render-or-redirect) is the Laravel
            // controller idiom, not under-modelled polymorphism — exempt it.
            if ($this->isRenderOrRedirectUnion($this->shortNamesOfNativeUnion($union))) {
                continue;
            }

            // A union mixing incompatible TYPE CATEGORIES (a callable form, a
            // class-string, or a scalar with an array) has no common supertype to
            // narrow to — it is a deliberate poly-form contract (closure-or-value,
            // predicate `bool|closure|class-string`, token-or-structure `string|array`),
            // not under-modelled class polymorphism. Exempt it (#139).
            if ($this->isPolyFormUnion($this->shortNamesOfNativeUnion($union))) {
                continue;
            }

            $count = count($union->types);

            if ($count >= $floor) {
                $line = $union->getStartLine();

                if ($this->withinRange($line, $exemptRanges)) {
                    continue;
                }

                $flaggedLines[$line] = true;
                $this->emit($line, $count, $this->nativeAtoms($union), $content, $warnAt, $sinAt, $sins, $warnings, $fixable[spl_object_id($union)]['members'] ?? null, $this->narrowCommonInterface($union, $uses, $namespace));
            }
        }

        // Docblock pass — the same wide union in @param/@return/@var, after
        // stripping generics (so `Option<array|string>` is not counted).
        foreach (explode("\n", $content) as $index => $text) {
            $line = $index + 1;

            if (isset($flaggedLines[$line])) {
                continue;
            }

            if ($this->withinRange($line, $exemptRanges)) {
                continue;
            }

            if (preg_match('/@(?:param|return|var)\s+(.+)$/', $text, $m)) {
                $atoms = $this->topLevelAtoms($this->cleanDocType($m[1]));

                // `Arrayable|array` typed-or-raw contract — exempt (see native pass).
                $shortAtoms = array_map(static fn (string $a): string => (string) (strrchr($a, '\\') ?: '\\' . $a), $atoms);
                $shortAtoms = array_map(static fn (string $a): string => ltrim($a, '\\'), $shortAtoms);
                sort($shortAtoms);

                if ($shortAtoms === ['array', 'arrayable']) {
                    continue;
                }

                // `View|RedirectResponse` render-or-redirect idiom — exempt.
                if ($this->isRenderOrRedirectUnion($shortAtoms)) {
                    continue;
                }

                // Poly-form contract (callable/class-string/scalar-or-array) — no
                // common supertype to narrow to — exempt (#139). Mirrors the native pass.
                if ($this->isPolyFormUnion($shortAtoms)) {
                    continue;
                }

                $count = $this->effectiveCount($atoms);

                if ($count >= $floor) {
                    $this->emit($line, $count, $atoms, $content, $warnAt, $sinAt, $sins, $warnings);
                }
            }
        }

        if ($sins !== []) {
            return $this->fallen($sins);
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * Classify and record a flagged union. A union is a SIN only when it is
     * value-or-nothing — it includes `null` AND has reached the sin threshold:
     * that is the high-confidence `Option<rest>` case. A null-free union is
     * ad-hoc polymorphism (what PHP unions are for) — a WARNING, not a blocker.
     *
     * @param  list<string>  $atoms  the union's member names (lowercased, null included)
     * @param  list<\JesseGall\CodeCommandments\Results\Sin>  $sins
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     * @param  list<string>|null  $fixableMembers  `T` case names when this union is an auto-fixable Data property
     * @param  array{fqcn: string, short: string}|null  $narrowInterface  the shared interface to narrow a class union to (#62)
     */
    private function emit(int $line, int $count, array $atoms, string $content, int $warnAt, int $sinAt, array &$sins, array &$warnings, ?array $fixableMembers = null, ?array $narrowInterface = null): void
    {
        $hasNull = in_array('null', $atoms, true);
        $isSin = $hasNull && $count >= $sinAt;

        // A null-free union below the warning floor (only possible when the
        // warning band is disabled) is not reportable at all.
        if (! $isSin && ($warnAt <= 0 || $count < $warnAt)) {
            return;
        }

        $snippet = $this->lineAt($content, $line);

        if ($isSin) {
            // A null-bearing union is never auto-fixable: this prophet's only
            // mechanical rewrite is narrowing a null-FREE union of project classes
            // to their shared interface (narrowCommonInterface short-circuits on any
            // scalar/null member). Marking it [AUTO-FIXABLE] by the SinRepenter
            // default would send `repent` into an endless no-op loop.
            $sins[] = $this->sinAt($line, $this->message($count, $atoms, true), $snippet, null, 'wide-union', false);

            return;
        }

        // A null-free union on a Spatie Data property CAN be modelled as a
        // `UnionCast`-backed `Union` — but #77: this changes the property's
        // runtime type, so every reader that uses it as its raw member type
        // (a string / array body) breaks. It is a SUGGESTION, not a blind
        // auto-fix: the human applies it and updates the readers.
        if ($fixableMembers !== null) {
            $allowed = implode(', ', array_map(static fn (string $m): string => 'T::' . $m, $fixableMembers));
            $message = $this->message($count, $atoms, false)
                . sprintf(
                    ' On this Spatie Data property, model it as a `#[WithCastAndTransformer(UnionCast::class, allowed: [%s])] public Union $…` (the Union sum type, enforced at hydration). NOT auto-fixable: it changes the property\'s runtime type, so update every reader that uses it as a raw %s.',
                    $allowed,
                    implode('/', $atoms),
                );

            $warnings[] = $this->warningAt($line, $message, $snippet, 'wide-union', false);

            return;
        }

        // #62: the members are project-owned classes that share a NARROW
        // interface — retyping to it collapses the union with zero wrapping.
        if ($narrowInterface !== null) {
            $message = $this->message($count, $atoms, false)
                . sprintf(
                    ' These members all implement the narrow shared interface `%s` — retype to it. AUTO-FIXABLE: `repent` narrows the union to `%s`.',
                    $narrowInterface['short'],
                    $narrowInterface['short'],
                );

            $warnings[] = $this->warningAt($line, $message, $snippet, 'wide-union', true);

            return;
        }

        $warnings[] = $this->warningAt($line, $this->message($count, $atoms, false), $snippet, 'wide-union', false);
    }

    /**
     * The finding message — tailored to the union's shape. An all-scalar union
     * has a ready-made home: `ScalarUnion` (always present) or `ScalarOption`
     * (when it also includes null — a nullable scalar, where the null is the
     * absence). Otherwise fall back to the general Option / Union / value-object
     * guidance.
     *
     * @param  list<string>  $atoms
     */
    private function message(int $count, array $atoms, bool $isSin): string
    {
        $head = sprintf(
            'This type unions %d members — a value worn as several shapes is under-modelled and pushes "what is this really?" onto every caller.',
            $count,
        );

        $hasNull = in_array('null', $atoms, true);
        $nonNull = array_values(array_filter($atoms, static fn (string $a): bool => $a !== 'null'));
        $allScalar = $nonNull !== [] && array_diff($nonNull, ['string', 'int', 'float', 'bool']) === [];

        if ($allScalar && $hasNull) {
            return $head . ' Every present member is a scalar and it includes null — this is a nullable scalar, so its home is `ScalarOption` (a present-or-absent scalar; the null becomes the absence).';
        }

        if ($allScalar) {
            return $head . ' Every member is a scalar — model it as a `ScalarUnion` (one present scalar, dispatched with `match()`).';
        }

        if ($isSin) {
            return $head . ' It includes null, so it is value-or-nothing → `Option<rest>` (the null becomes the Option\'s absence). If the rest is several shapes, that is `Option<Union<…>>` or a value object.';
        }

        return $head . ' It is always present (no null) but one-of-N types — that is ad-hoc polymorphism. If the members are CLASSES that are one concept (a leaf vs a nested group), give them a shared interface they all implement (introduce one if absent) and type as that — the `instanceof A || instanceof B` chains collapse to one; if they merely share behaviour, a `Union` sum type or a named value object; if they should be one type, pick one. (Add a `null` member and it becomes value-or-nothing → `Option`.)';
    }

    /**
     * For a null-free union of project-owned CLASSES, the NARROW interface they
     * all share (sealed/marker-like — implemented by few classes, never a
     * framework-broad contract), or null. Retyping `A | B` to it is the clean
     * fix (#62); over-broad interfaces are refused so the type is never widened.
     *
     * @param  array<string, string>  $uses
     * @return array{fqcn: string, short: string}|null
     */
    private function narrowCommonInterface(Node\UnionType $union, array $uses, ?string $namespace): ?array
    {
        if ($this->index === null) {
            return null;
        }

        $members = $this->classMemberFqcns($union, $uses, $namespace);

        if (count($members) < 2) {
            return null;
        }

        $common = $this->index->interfacesOf($members[0]);

        foreach (array_slice($members, 1) as $fqcn) {
            $common = array_values(array_intersect($common, $this->index->interfacesOf($fqcn)));
        }

        $cap = (int) $this->config('narrow_cap', self::DEFAULT_NARROW_CAP);
        $best = null;
        $bestCount = PHP_INT_MAX;

        foreach ($common as $interface) {
            if ($this->isOverBroad($interface)) {
                continue;
            }

            $implementers = count($this->index->implementersOf($interface));

            // 0 implementers means the index is missing it; only narrow to an
            // interface whose implementer-set is genuinely tight.
            if ($implementers > 0 && $implementers <= $cap && $implementers < $bestCount) {
                $best = $interface;
                $bestCount = $implementers;
            }
        }

        return $best === null ? null : ['fqcn' => $best, 'short' => $this->shortFqcn($best)];
    }

    /**
     * Resolved FQCNs of a union's members IFF EVERY member is a project-owned
     * class (a native member is a `Node\Identifier`, not `Node\Name`, so any
     * scalar/null member short-circuits this to an empty list).
     *
     * @param  array<string, string>  $uses
     * @return list<string>
     */
    private function classMemberFqcns(Node\UnionType $union, array $uses, ?string $namespace): array
    {
        $fqcns = [];

        foreach ($union->types as $type) {
            if (! $type instanceof Node\Name) {
                return [];
            }

            $fqcn = ltrim(NameResolver::resolve($type->toString(), $uses, $namespace), '\\');

            if ($this->index?->classByFqcn($fqcn) === null) {
                return []; // vendor / unknown — cannot analyse the hierarchy
            }

            $fqcns[] = $fqcn;
        }

        return $fqcns;
    }

    private function isOverBroad(string $interfaceFqcn): bool
    {
        if (in_array(strtolower($this->shortFqcn($interfaceFqcn)), self::OVER_BROAD_INTERFACES, true)) {
            return true;
        }

        foreach (self::OVER_BROAD_NAMESPACES as $prefix) {
            if (str_starts_with(ltrim($interfaceFqcn, '\\'), $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function shortFqcn(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }


    /**
     * @param  array<Node>  $ast
     * @return array<string, string>  alias => FQCN
     */
    /**
     * A native union's member names, lowercased (intersection / complex members
     * collapse to a non-scalar placeholder so they never read as scalar).
     *
     * @return list<string>
     */
    private function nativeAtoms(Node\UnionType $union): array
    {
        $atoms = [];

        foreach ($union->types as $type) {
            $atoms[] = ($type instanceof Node\Identifier || $type instanceof Node\Name)
                ? strtolower($type->toString())
                : 'object';
        }

        return $atoms;
    }

    /**
     * The type portion of a docblock tag value: drop the variable name and any
     * trailing description, and strip whitespace (so a space inside a generic —
     * `array<string, int>` — does not truncate the type).
     */
    private function cleanDocType(string $rest): string
    {
        // Drop a trailing single-line PHPDoc close (`… */`) and anything after,
        // so `@return View|RedirectResponse */` does not leak `*/` into the
        // last member name.
        $rest = preg_replace('#\*/.*$#s', '', $rest) ?? $rest;
        $type = preg_replace('/\$\w+.*$/', '', $rest) ?? $rest;

        return preg_replace('/\s+/', '', $type) ?? $type;
    }

    /**
     * The TOP-LEVEL member names of a docblock union type, lowercased, after
     * stripping generics — so `Option<array|string>` is `[option]`,
     * `array<string,int>|string|null` is `[array, string, null]`.
     *
     * @return list<string>
     */
    private function topLevelAtoms(string $type): array
    {
        $stripped = $type;

        // Collapse NESTED type syntax before splitting on `|` — a pipe inside
        // generics `<…>` or an array-shape `{…}` is not a top-level union member
        // separator. `array{0: int, 2: string|null}` is ONE atom (`array`), not a
        // `string`-vs-`null` union (#148). (Callable `(…)` is left intact so the
        // poly-form `Closure(…): T` detection still sees its `closure(` head.)
        do {
            $previous = $stripped;
            $stripped = preg_replace('/<[^<>]*>/', '', $stripped) ?? $stripped;
            $stripped = preg_replace('/\{[^{}]*\}/', '', $stripped) ?? $stripped;
        } while ($stripped !== $previous);

        // A leading `?` is the idiomatic nullable, not a union member — strip it
        // so `?Foo` reads as the single member `Foo` (and `Foo|null` as two).
        $atoms = array_values(array_filter(array_map('trim', explode('|', ltrim($stripped, '?')))));

        return array_map('strtolower', $atoms);
    }

    /**
     * The effective width of a docblock union: a simple nullable (one non-null
     * member + null, like `Foo|null`) counts as 1, exactly like `?Foo`. A 3+
     * union that includes null keeps its full count.
     *
     * @param  list<string>  $atoms
     */
    private function effectiveCount(array $atoms): int
    {
        $nonNull = array_filter($atoms, static fn (string $atom): bool => $atom !== 'null');

        if (in_array('null', $atoms, true) && count($nonNull) === 1) {
            return 1;
        }

        return count($atoms);
    }

    /**
     * The line ranges (start, end) of every `#[Attribute]`-marked class in the
     * file. A finding inside one of these is exempt — attribute constructor
     * arguments must be constant expressions, so an Option/Union can never live
     * there and the suggestion is unactionable.
     *
     * @param  array<Node>  $ast
     * @return list<array{int, int}>
     */
    private function attributeClassRanges(array $ast): array
    {
        $ranges = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->attrGroups as $group) {
                foreach ($group->attrs as $attr) {
                    if (strtolower($attr->name->getLast()) === 'attribute') {
                        $ranges[] = [$class->getStartLine(), $class->getEndLine()];

                        continue 3;
                    }
                }
            }
        }

        return $ranges;
    }

    /**
     * The line ranges of every method marked `#[\Override]`. Such a method's
     * signature is inherited from an interface or base class, so its type is not
     * the author's to change — flagging a wide union there is unactionable.
     *
     * @param  array<Node>  $ast
     * @return list<array{int, int}>
     */
    private function overrideMethodRanges(array $ast): array
    {
        $ranges = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\ClassMethod::class) as $method) {
            foreach ($method->attrGroups as $group) {
                foreach ($group->attrs as $attr) {
                    if (strtolower($attr->name->getLast()) === 'override') {
                        $ranges[] = [$method->getStartLine(), $method->getEndLine()];

                        continue 3;
                    }
                }
            }
        }

        return $ranges;
    }

    /**
     * @param  list<array{int, int}>  $ranges
     */
    private function withinRange(int $line, array $ranges): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($line >= $start && $line <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the union is the `Arrayable | array` convenience contract — the
     * canonical "typed-or-raw" input idiom (Spatie Data and Eloquent models
     * implement `Arrayable`, so a setter accepts either the object or the plain
     * array it serialises to). It is not value-or-nothing and not ad-hoc
     * polymorphism — both members describe the SAME data, one hydrated and one
     * raw — so neither Option nor a Union sum type applies. Collapsing it would
     * break every call site, so it is exempt. Matched by short name (any
     * `Arrayable` interface) and only at width 2 — add `null` or a third member
     * and the normal rules resume.
     */
    private function isArrayableConvenienceUnion(Node\UnionType $union): bool
    {
        if (count($union->types) !== 2) {
            return false;
        }

        $names = [];

        foreach ($union->types as $type) {
            if ($type instanceof Node\Name) {
                $names[] = strtolower($type->getLast());
            } elseif ($type instanceof Node\Identifier) {
                $names[] = strtolower($type->toString());
            } else {
                return false;
            }
        }

        sort($names);

        return $names === ['array', 'arrayable'];
    }

    /**
     * The member short names of a native union, lowercased (a non-name member
     * collapses to the placeholder `object`).
     *
     * @return list<string>
     */
    private function shortNamesOfNativeUnion(Node\UnionType $union): array
    {
        $names = [];

        foreach ($union->types as $type) {
            $names[] = match (true) {
                $type instanceof Node\Name => strtolower($type->getLast()),
                $type instanceof Node\Identifier => strtolower($type->toString()),
                default => 'object',
            };
        }

        return $names;
    }

    /**
     * Whether the union is the Laravel render-or-redirect controller idiom —
     * exactly two members, one `RedirectResponse` and the other a renderable
     * (`View`, or any `*Response`: `Response`, `JsonResponse`, …). A controller
     * action that conditionally renders OR redirects legitimately returns this;
     * it is the framework contract, not under-modelled polymorphism, and cannot
     * collapse to one type or an Option. Matched by short name at width 2.
     *
     * @param  list<string>  $shortNames  member short names, lowercased
     */
    private function isRenderOrRedirectUnion(array $shortNames): bool
    {
        if (count($shortNames) !== 2) {
            return false;
        }

        if (! in_array('redirectresponse', $shortNames, true)) {
            return false;
        }

        $other = $shortNames[0] === 'redirectresponse' ? $shortNames[1] : $shortNames[0];

        return $other === 'view' || str_ends_with($other, 'response');
    }

    /**
     * A deliberate POLY-FORM contract — a union whose members span incompatible
     * type CATEGORIES, so there is no common supertype to narrow them to (which is
     * the only fix this prophet offers). These are intentional multi-form APIs, not
     * under-modelled class polymorphism, and collapsing them would remove the
     * affordance (#139):
     *   - any member is a CALLABLE form (`closure`, `callable`, a `Closure(...): T`
     *     / `callable(...)` docblock type) — closure-or-value (lazy-or-eager) or a
     *     predicate `bool | closure | class-string`;
     *   - any member is a `class-string` / `callable-string` selector.
     *
     * (`string | array` is deliberately NOT exempted: unlike a callable, it CAN be
     * modelled — a `Union` value object / normalisation — so it stays the prophet's
     * advisory nudge; absolve the genuinely-intentional token-or-structure ones.)
     *
     * @param  list<string>  $shortNames  member short names, lowercased
     */
    private function isPolyFormUnion(array $shortNames): bool
    {
        // A null-bearing union is value-or-nothing (→ `Option<rest>`, the prophet's
        // primary high-confidence fix) — NEVER a poly-form exemption, even when the
        // rest mixes categories (`closure | null` stays value-or-nothing).
        if (in_array('null', $shortNames, true)) {
            return false;
        }

        foreach ($shortNames as $name) {
            $bare = ltrim($name, '(');

            if (in_array($bare, ['closure', 'callable'], true)
                || str_starts_with($bare, 'closure(')
                || str_starts_with($bare, 'callable(')
                || str_starts_with($bare, 'class-string')
                || str_starts_with($bare, 'callable-string')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a native union is a simple nullable — exactly two members, one of
     * which is `null` (`T | null`). The spelled-out twin of `?T`.
     */
    private function isSimpleNullableNative(Node\UnionType $union): bool
    {
        if (count($union->types) !== 2) {
            return false;
        }

        foreach ($union->types as $type) {
            if (($type instanceof Node\Identifier || $type instanceof Node\Name)
                && strtolower($type->toString()) === 'null') {
                return true;
            }
        }

        return false;
    }

    /**
     * Smallest union size that warns. 0 (or `warnings_enabled => false`) disables
     * the warning band — only sins fire then.
     */
    private function warnThreshold(): int
    {
        if ($this->config('warnings_enabled', true) === false) {
            return 0;
        }

        $value = $this->config('warn_at_types', self::DEFAULT_WARN_AT);

        return is_numeric($value) ? max(0, (int) $value) : self::DEFAULT_WARN_AT;
    }

    /**
     * Smallest union size that is a sin.
     */
    private function sinThreshold(): int
    {
        $value = $this->config('sin_at_types', self::DEFAULT_SIN_AT);

        return is_numeric($value) ? max(2, (int) $value) : self::DEFAULT_SIN_AT;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }

    // --- Auto-fix: rewrite a null-free union Data property into a Union ---------

    public function repentInputs(): array
    {
        // The allowed types are read straight from the union, so no input.
        return [];
    }

    public function setRepentInput(array $values): void
    {
        // No inputs to receive.
    }

    public function canRepent(string $filePath): bool
    {
        return str_ends_with($filePath, '.php');
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        // #62: the ONLY auto-fix is narrowing a class union to its shared
        // interface. #77: the Spatie-Data `UnionCast`-backed `Union` rewrite is
        // NOT auto-applied — it changes the property's runtime type and breaks
        // every reader of the raw member values (it stays an advisory suggestion).
        $namespace = FileImports::namespace($ast);
        $uses = FileImports::of($ast);
        $interfaceFixes = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\UnionType::class) as $union) {
            $narrow = $this->narrowCommonInterface($union, $uses, $namespace);

            if ($narrow !== null) {
                $interfaceFixes[] = ['typeNode' => $union, 'interface' => $narrow];
            }
        }

        if ($interfaceFixes === []) {
            return RepentanceResult::unchanged();
        }

        $edits = [];
        $penance = [];
        $interfaceImports = [];

        foreach ($interfaceFixes as $fix) {
            $type = $fix['typeNode'];
            $edits[] = ['start' => $type->getStartFilePos(), 'end' => $type->getEndFilePos(), 'text' => $fix['interface']['short']];
            $penance[] = "Narrowed a class union to its shared interface {$fix['interface']['short']}";
            $interfaceImports[] = $fix['interface']['fqcn'];
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        // #62: import each narrow interface so its short name resolves — unless it
        // already lives in the file's own namespace (where the short name resolves
        // with no import). ensureUse itself skips an already-present import.
        foreach (array_unique($interfaceImports) as $interfaceFqcn) {
            $interfaceNs = ($pos = strrpos($interfaceFqcn, '\\')) !== false ? substr($interfaceFqcn, 0, $pos) : null;

            if ($interfaceNs !== ($namespace ?? null)) {
                $content = $this->ensureUse($content, $interfaceFqcn);
            }
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * Null-free union properties on a Spatie Data class whose every member maps
     * to a `T` case — keyed by union-node id.
     *
     * @param  array<Node>  $ast
     * @return array<int, array{owner: Node, typeNode: Node\UnionType, members: list<string>}>
     */
    private function fixableUnionFields(array $ast): array
    {
        $map = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if (! $class->extends instanceof Node\Name || ! str_ends_with($class->extends->getLast(), 'Data')) {
                continue;
            }

            $constructor = $class->getMethod('__construct');

            if ($constructor !== null) {
                foreach ($constructor->params as $param) {
                    if ($param->flags !== 0 && $param->attrGroups === []) {
                        $this->addFixable($map, $param->type, $param);
                    }
                }
            }

            foreach ($class->getProperties() as $property) {
                if ($property->attrGroups === []) {
                    $this->addFixable($map, $property->type, $property);
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{owner: Node, typeNode: Node\UnionType, members: list<string>}>  $map
     */
    private function addFixable(array &$map, ?Node $type, Node $owner): void
    {
        if (! $type instanceof Node\UnionType || count($type->types) < 2) {
            return;
        }

        $members = [];

        foreach ($type->types as $member) {
            // A class member (Node\Name) or `null` makes this not a clean,
            // null-free, builtin-only union — leave it to the human.
            if (! $member instanceof Node\Identifier || ! isset(self::T_MAP[strtolower($member->toString())])) {
                return;
            }

            $members[] = self::T_MAP[strtolower($member->toString())];
        }

        $map[spl_object_id($type)] = ['owner' => $owner, 'typeNode' => $type, 'members' => $members];
    }

    /**
     * The leading whitespace of the line containing the given byte offset.
     */
    private function indentAt(string $content, int $pos): string
    {
        $lineStart = strrpos(substr($content, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        return preg_replace('/\S.*$/s', '', substr($content, $lineStart, $pos - $lineStart)) ?? '';
    }

    private function ensureUse(string $content, string $fqcn): string
    {
        if (preg_match('/^\s*use\s+' . preg_quote($fqcn, '/') . '\s*;/m', $content) === 1) {
            return $content;
        }

        if (preg_match('/^namespace\s+[^;]+;/m', $content, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return $content;
        }

        $insertAt = $m[0][1] + strlen($m[0][0]);

        return substr($content, 0, $insertAt) . "\n\nuse {$fqcn};" . substr($content, $insertAt);
    }
}
