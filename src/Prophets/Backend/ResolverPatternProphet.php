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
use PhpParser\PrettyPrinter;

/**
 * Steers first-match dispatch and predicate code toward the resolver +
 * Predicate architecture: nominate dispatch chains as resolvers, suggest
 * composing boolean chains from Predicate objects, extract a resolver's
 * inline predicates into named classes — and SIN a resolver that is just a
 * pile of inline closures (the half-done extraction that buys nothing).
 */
#[IntroducedIn('1.62.0')]
class ResolverPatternProphet extends PhpCommandment
{
    private const DEFAULT_SUFFIX = 'Resolver';

    /** Functions whose result is a boolean test worth naming. */
    private const PREDICATE_FUNCTIONS = [
        'str_starts_with', 'str_ends_with', 'str_contains',
        'in_array', 'array_search', 'array_key_exists',
        'preg_match', 'ctype_digit', 'ctype_alpha',
        'class_exists', 'method_exists', 'function_exists', 'property_exists',
        'is_a', 'is_subclass_of',
    ];

    public function description(): string
    {
        return 'Drive first-match dispatch and predicate code into the resolver + Predicate pattern';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A method is a first-match dispatch chain (>= 3 predicate guards / '
                . '`match (true)` arms producing one type) — a resolver in disguise; '
                . 'OR a >= 3-guard boolean method — a composite Predicate; OR an '
                . 'existing resolver still carries inline predicate tests instead '
                . 'of named Predicate classes.'
            )
            ->leaveWhen(
                'The branches are not pure dispatch (they transform, throw, or '
                . 'return unrelated shapes), or a lone inline test is a genuine '
                . 'one-off that reads clearly where it is.'
            )
            ->whenUnsure(
                'Read the full rule below — the goal is the resolver + Predicate '
                . 'structure: a Resolver of NAMED Predicates, reusing the kernel '
                . '(`IsNull`/`IsEnum`/`HasClass`/`HasPrefix`) and creating domain Predicates '
                . 'for type-specific tests. A resolver of inline closures is a sin.'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
THE MISSION
===========
First-match dispatch (map an input to one of several outputs by a chain of
tests) and ad-hoc boolean logic belong in the resolver + Predicate pattern:
a Resolver COMPOSED from NAMED Predicate objects + a strategy, NOT an if/match
chain and NOT a resolver of inline closures. Each test becomes a class you
name, reuse, and compose. Run `scaffold` for the kernel under
`Support\Resolvers`: a composable `Resolver`, `ResolveStrategy`
(`FirstResultWins`, `CollectResults`), and a `Predicate` kernel (`IsNull`,
`IsEnum`, `HasClass`, `HasPrefix`, `Equals`, `AllOf`/`AnyOf`/`Negated`).

When you fix a finding here, do the WHOLE job — a resolver whose entries are
still inline `fn (...) => test ? … : null` is a SIN, not progress.

MODE 1 — DISPATCH CHAIN → COMPOSED RESOLVER
-------------------------------------------
A method that is a first-match dispatch chain is a resolver in disguise:

    public static function parse(?string $token): self
    {
        if ($token === null)                       { return self::mixed(); }
        if (str_starts_with($token, 'resource:'))  { return self::resource(...); }
        if (str_starts_with($token, 'list:'))      { return self::listOf(...); }
        if (in_array($token, self::SCALARS, true)) { return self::scalar($token); }
        return self::classRef($token);
    }

Fires on >= 3 predicate guards (`if`/`switch`) OR `match (true)` arms that
produce one type. COMPOSE a Resolver and delegate:

    public static function parse(?string $token): self
    {
        return Resolver::firstResultWins(
            IsNull::make()->then(WireType::mixed(...)),
            HasPrefix::of('resource:')->transform(StripPrefix::of('resource:'))->then(WireType::resource(...)),
            HasPrefix::of('list:')->transform(StripPrefix::of('list:'))->then(WireType::listOf(...)),
            IsScalarToken::make()->then(WireType::scalar(...)),   // domain predicate
        )->resolve($token) ?? self::classRef($token);
    }

Each entry is a NAMED Predicate paired with a result factory via `->then()` —
never an inline test. To pre-process the matched input, add a transform:
`->transform($t)->then(Factory(...))` runs `$t` on the value before the
factory, so the factory stays a first-class callable (no `substr(...)` inside
a closure). `$t` is any callable; reusable ones extend `Transform`
(`StripPrefix::of('list:')`). `Resolver::collect(...)` gathers ALL matches (a list)
instead of the first; any other combine rule is a `ResolveStrategy` passed to
`Resolver::using($strategy, ...entries)`.

The `->then()` argument is the RESULT FACTORY (it produces the resolved value)
— NOT a transform (a transform pre-processes the matched input). When a chain
repeatedly inlines a domain factory closure
(`->then(fn ($r) => $this->expandX($r->descriptor, $r->node))`), the factories
are the only un-named part of the chain. Name them like the predicates: a
first-class callable when the factory is a pure forward, or an invokable
factory class under `Support\Resolvers\Factories` otherwise:

    final class ExpandInputBag
    {
        public function __invoke(DescriptorExpansionRequest $r): Node
        {
            return /* … */;
        }
    }

    DescriptorKeyIs::of(InputBagNode::KEY)->then(new ExpandInputBag(...));

The kernel ships two ready-made result factories there: `Capture::make()`
(identity — return the value unchanged) and `Wrap::make()` (`$v => [$v]`).

MODE 2 — BOOLEAN CHAIN → COMPOSITE PREDICATE
--------------------------------------------
A >= 3-guard method returning `bool` is a composite Predicate. Build it from
named Predicate objects and the kernel combinators:

    // was: if (a) return true; if (b) return true; if (c) return false; …
    AnyOf::of(IsMixed::make(), IsListType::make(), IsScalarType::make())

MODE 3 — EXTRACT a resolver's inline predicates / fix entries
-------------------------------------------------------------
A composed resolver's entries must be NAMED Predicates. Give each inline test
a class:

  - REUSE THE KERNEL when one fits: `$x === null` → `IsNull::make()`;
    `$x instanceof SomeEnum` → `IsEnum::for(...)`; `$x instanceof SomeClass`
    (dispatch on an object's type) → `HasClass::of(SomeClass::class)`;
    `str_starts_with(...)` → `HasPrefix::of(...)`. Do not re-create these.
  - GENERIC but not in the kernel → add it to the SHARED
    `Support\Resolvers\Predicates\`.
  - DOMAIN-BOUND (reads a type's constants — `self::SCALARS`, `WireType::MIXED`)
    → the resolver's OWN `Support\Resolvers\<Name>\Predicates\`.

An entry that just FORWARDS to one method (`fn ($r) => $this->candidate($r)`)
should be the first-class callable `$this->candidate(...)`.

PREDICATE CONVENTIONS
---------------------
  - NAMED STATIC FACTORY, never `new` at the call site: `HasPrefix::of('list:')`,
    `IsEnum::for(NodeType::class)`, `IsNull::make()`. PRIVATE constructor so the
    factory is the only way in (a no-arg predicate hands back a flyweight).
  - A predicate is for the CHAIN. NEVER instantiate one to call it once inline:
    `(new IsNull())($x)` — or `$p = new IsNull(); $p($x)` — is WORSE than the
    plain `$x === null`. Use the plain test for a one-off, or the chain.

THE SIN — an UGLY resolver
--------------------------
`Resolver::firstResultWins(fn (...) => test ? … : null, …)` with >= 3 inline
predicate closures is the original chain with extra boilerplate. SIN: make
each entry a named Predicate `->then(factory)`.

WHAT DOES NOT FIRE — a value-mapping ternary, a bare `var === var`, a method
that transforms / throws / returns unrelated shapes, a predicate passed as a
callback argument (`Option::first($xs, new IsX())`), and a `match` on an enum
subject (PreferTypeMethodOverInlineDispatch's rule).

Configuration:

    Backend\ResolverPatternProphet::class => [
        'suffix' => 'Resolver',          // class-name suffix that marks a resolver
        'base_classes' => [],            // classes whose extension marks a resolver
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $printer = new PrettyPrinter\Standard;
        $warnings = [];

        /** @var array<Node\Stmt\Class_> $classes */
        $classes = $finder->findInstanceOf($ast, Node\Stmt\Class_::class);

        $sins = [];

        // Nominate: a method that is a first-match dispatch chain (a resolver
        // in disguise) or a composite predicate. Never flags a one-off boolean.
        foreach ($classes as $class) {
            foreach ($this->dispatchChainMethods($finder, $class) as $chain) {
                $warnings[] = $this->warningAt($chain['line'], $this->nominateMessage($chain), null, $chain['kind']);
            }
        }

        // Police composed resolvers — `Resolver::using/firstResultWins/collect(...)`.
        $this->judgeCompositions($finder, $printer, $ast, $sins, $warnings);

        // Anti-pattern, anywhere: a Predicate instantiated and invoked inline
        // for a single check — worse than the plain test it replaces.
        foreach ($this->scatteredPredicateInvocations($finder, $ast) as [$line, $name]) {
            $warnings[] = $this->warningAt($line, $this->scatteredMessage($name), null, 'scattered-predicate');
        }

        if ($sins === [] && $warnings === []) {
            return $this->righteous();
        }

        return new Judgment(sins: $sins, warnings: $warnings);
    }

    /**
     * Each `Resolver::using(...)` / `::firstResultWins(...)` / `::collect(...)`
     * composition: entries must be NAMED predicates paired with a result
     * factory, not inline closures. >= 3 inline predicate closures = SIN; a
     * closure that just forwards to one call should be a first-class callable.
     *
     * @param  array<Node>  $ast
     * @param  list<\JesseGall\CodeCommandments\Results\Sin>  $sins
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function judgeCompositions(NodeFinder $finder, PrettyPrinter\Standard $printer, array $ast, array &$sins, array &$warnings): void
    {
        /** @var array<Expr\StaticCall> $calls */
        $calls = $finder->findInstanceOf($ast, Expr\StaticCall::class);

        foreach ($calls as $call) {
            if (! $call->class instanceof Node\Name || $call->class->getLast() !== 'Resolver'
                || ! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), ['using', 'firstResultWins', 'collect'], true)
                || $call->isFirstClassCallable()
            ) {
                continue;
            }

            $args = $call->getArgs();
            // `using($strategy, ...entries)` — the first arg is the strategy.
            $entries = $call->name->toString() === 'using' ? array_slice($args, 1) : $args;

            $inline = [];
            $inlineFactories = [];
            $stripPrefixEntries = [];

            foreach ($entries as $arg) {
                if ($arg->value instanceof Expr\MethodCall && $this->stripsPrefixInThen($arg->value)) {
                    $warnings[] = $this->warningAt(
                        $arg->value->getStartLine(),
                        'Resolver entry strips its prefix with `substr(..., strlen(...))` inside `->then()` — move it to a transform: `HasPrefix::of(PREFIX)->transform(StripPrefix::of(PREFIX))->then(Factory(...))`, so the factory receives the stripped remainder and stays a first-class callable.',
                        null,
                        'prefix-substr',
                    );

                    continue;
                }

                // A `Predicate->…->then(<closure>)` entry: the closure is the
                // RESULT FACTORY. A pure forward should be a first-class
                // callable; a real adapter doing domain work should be a named
                // invokable factory class.
                if ($arg->value instanceof Expr\MethodCall) {
                    if (($prefix = $this->doubledStripPrefix($arg->value, $printer)) !== null) {
                        $stripPrefixEntries[] = $arg->value->getStartLine();
                    }

                    $then = $this->thenClosureArg($arg->value);

                    if ($then instanceof Expr\ArrowFunction && ($forward = $this->forwardedCallable($then)) !== null) {
                        $warnings[] = $this->warningAt(
                            $then->getStartLine(),
                            sprintf('Resolver `->then()` factory just forwards to one call — use the first-class callable `%s` instead.', $forward),
                            null,
                            'redundant-then-closure',
                        );
                    } elseif ($then !== null && $this->isDomainFactoryClosure($then)) {
                        $inlineFactories[] = $then->getStartLine();
                    }

                    continue;
                }

                if (! $arg->value instanceof Expr\ArrowFunction) {
                    continue;
                }

                if ($this->chainPredicateOf($arg->value) !== null) {
                    $inline[] = [$arg->value->getStartLine(), $printer->prettyPrintExpr($arg->value)];
                } elseif (($forward = $this->forwardedCallable($arg->value)) !== null) {
                    $warnings[] = $this->warningAt(
                        $arg->value->getStartLine(),
                        sprintf('Resolver entry `%s` just forwards to one call — use the first-class callable `%s` instead.', $this->truncate($printer->prettyPrintExpr($arg->value)), $forward),
                        null,
                        'redundant-closure',
                    );
                }
            }

            if (count($inline) >= 3) {
                $sins[] = $this->sinAt(
                    $inline[0][0],
                    sprintf(
                        'This composed resolver is %d inline predicate closures — `Resolver::…(fn (...) => test ? … : null, …)` is the original chain with extra boilerplate. Make each entry a NAMED Predicate paired with a factory (reuse `IsNull`/`IsEnum`/`HasPrefix`; create a domain Predicate otherwise): `HasPrefix::of(…)->then(Factory::make(...))`.',
                        count($inline),
                    ),
                    null,
                    null,
                    'ugly-resolver',
                );
            } else {
                foreach ($inline as [$line, $printed]) {
                    $warnings[] = $this->warningAt(
                        $line,
                        sprintf('Resolver entry `%s` inlines a predicate — make it a named Predicate paired with a factory (`Predicate->then(...)`).', $this->truncate($printed)),
                        null,
                        'inline-predicate',
                    );
                }
            }

            // A resolver whose entries repeatedly inline a domain factory closure
            // (`->then(fn ($r) => $this->expandX($r->…)))`) is restating the same
            // boilerplate per entry. Name them: invokable factory classes under
            // Support\Resolvers\Factories, like the predicates are named.
            if (count($inlineFactories) >= 3) {
                $warnings[] = $this->warningAt(
                    $inlineFactories[0],
                    sprintf(
                        'This resolver has %d inline `->then()` factory closures (`fn ($x) => $this->build($x->…))`) — the result factories are the only un-named part of the chain. Extract each into a NAMED invokable factory class under `Support\\Resolvers\\Factories` (e.g. `final class ExpandInputBag { public function __invoke(Request $r): Node { … } }`) and reference it — `Predicate->then(new ExpandInputBag(...))` — or reshape the method to take the matched value and pass a first-class callable. Keeps the chain declarative and the factories named, testable, and reusable. (These are FACTORIES — they produce the result — not transforms, which pre-process the matched input.)',
                        count($inlineFactories),
                    ),
                    null,
                    'inline-then-factories',
                );
            }

            // Repeated `HasPrefix::of(P)->transform(StripPrefix::of(P))` states
            // the prefix TWICE per entry — error-prone and noisy. Wrap the kernel
            // in a domain Resolver decorator whose builder declares P once.
            if (count($stripPrefixEntries) >= 2) {
                $warnings[] = $this->warningAt(
                    $stripPrefixEntries[0],
                    sprintf(
                        'This resolver has %d entries shaped `HasPrefix::of(P)->transform(StripPrefix::of(P))` — the prefix P is declared TWICE per entry (the predicate and the transform), and the two can silently drift. Wrap the kernel in a domain Resolver decorator (e.g. `WireTypeTokenResolver`) exposing a builder that declares P once and forwards to the kernel — `->stripPrefix(self::LIST_PREFIX, self::listOf(...))` building `HasPrefix::of(P)->transform(StripPrefix::of(P))->then(factory)` internally. Reads as the domain operation, not the mechanism.',
                        count($stripPrefixEntries),
                    ),
                    null,
                    'doubled-strip-prefix',
                );
            }
        }
    }

    /**
     * The (printed) prefix of an entry shaped
     * `HasPrefix::of(P)->transform(StripPrefix::of(P))->…`, when the SAME prefix
     * P is passed to both `HasPrefix::of()` and `StripPrefix::of()` — else null.
     */
    private function doubledStripPrefix(Expr\MethodCall $entry, PrettyPrinter\Standard $printer): ?string
    {
        $hasPrefix = null;
        $stripPrefix = null;

        foreach ((new NodeFinder)->findInstanceOf([$entry], Expr\StaticCall::class) as $call) {
            if (! $call->class instanceof Node\Name || ! $call->name instanceof Node\Identifier
                || $call->name->toString() !== 'of' || $call->isFirstClassCallable()
            ) {
                continue;
            }

            $arg = $call->getArgs()[0]->value ?? null;

            if ($arg === null) {
                continue;
            }

            $printed = $printer->prettyPrintExpr($arg);

            if ($call->class->getLast() === 'HasPrefix') {
                $hasPrefix = $printed;
            } elseif ($call->class->getLast() === 'StripPrefix') {
                $stripPrefix = $printed;
            }
        }

        return $hasPrefix !== null && $hasPrefix === $stripPrefix ? $hasPrefix : null;
    }

    /**
     * The closure passed to a chain entry's terminal `->then(<closure>)`, or
     * null when the entry does not end in `->then()` with an inline closure.
     */
    private function thenClosureArg(Expr\MethodCall $entry): Expr\ArrowFunction|Expr\Closure|null
    {
        if (! $entry->name instanceof Node\Identifier || $entry->name->toString() !== 'then'
            || $entry->isFirstClassCallable()
        ) {
            return null;
        }

        $args = $entry->getArgs();
        $first = $args[0]->value ?? null;

        return $first instanceof Expr\ArrowFunction || $first instanceof Expr\Closure ? $first : null;
    }

    /**
     * Whether a `->then()` factory closure does real domain work (its body is a
     * method/static/func call or a `new`) AND is not a pure forward (which would
     * be a first-class callable). Those deserve a named invokable factory.
     */
    private function isDomainFactoryClosure(Expr\ArrowFunction|Expr\Closure $fn): bool
    {
        if ($fn instanceof Expr\ArrowFunction && $this->forwardedCallable($fn) !== null) {
            return false;
        }

        $body = $this->closureResultExpr($fn);

        return $body instanceof Expr\MethodCall
            || $body instanceof Expr\StaticCall
            || $body instanceof Expr\New_
            || $body instanceof Expr\FuncCall;
    }

    private function closureResultExpr(Expr\ArrowFunction|Expr\Closure $fn): ?Node
    {
        if ($fn instanceof Expr\ArrowFunction) {
            return $fn->expr;
        }

        foreach ($fn->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_) {
                return $stmt->expr;
            }
        }

        return null;
    }

    /**
     * Whether a chain entry is `HasPrefix::of(...)->then(fn (...) => …substr…)`
     * — stripping the prefix by hand inside the factory, which a
     * `->transform(StripPrefix::of(...))` does for you.
     */
    private function stripsPrefixInThen(Expr\MethodCall $call): bool
    {
        if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'then'
            || ! $this->rootsInHasPrefix($call->var)
            || $call->isFirstClassCallable()
        ) {
            return false;
        }

        $args = $call->getArgs();

        if ($args === [] || ! $args[0]->value instanceof Expr\ArrowFunction) {
            return false;
        }

        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf([$args[0]->value->expr], Expr\FuncCall::class) as $fc) {
            if ($fc->name instanceof Node\Name && strtolower($fc->name->toString()) === 'substr') {
                return true;
            }
        }

        return false;
    }

    private function rootsInHasPrefix(Node $expr): bool
    {
        if ($expr instanceof Expr\StaticCall && $expr->class instanceof Node\Name) {
            return $expr->class->getLast() === 'HasPrefix';
        }

        if ($expr instanceof Expr\MethodCall) {
            return $this->rootsInHasPrefix($expr->var);
        }

        return false;
    }

    /**
     * For an arrow function that merely forwards its params to one call
     * (`fn ($a, $b) => $this->x($a, $b)`), the first-class-callable form
     * (`$this->x(...)`), or null when it transforms / re-orders / drops args.
     */
    private function forwardedCallable(Expr\ArrowFunction $arrow): ?string
    {
        $body = $arrow->expr;

        // A first-class callable body (`fn () => $this->x(...)`) has no real args
        // to forward and would assert on getArgs().
        if (($body instanceof Expr\MethodCall || $body instanceof Expr\StaticCall || $body instanceof Expr\FuncCall)
            && $body->isFirstClassCallable()
        ) {
            return null;
        }

        $args = match (true) {
            $body instanceof Expr\MethodCall, $body instanceof Expr\StaticCall, $body instanceof Expr\FuncCall => $body->getArgs(),
            default => null,
        };

        if ($args === null) {
            return null;
        }

        $params = $arrow->params;

        if (count($args) !== count($params)) {
            return null;
        }

        foreach ($params as $i => $param) {
            $arg = $args[$i];

            if ($arg->name !== null || $arg->unpack
                || ! $arg->value instanceof Expr\Variable
                || ! $param->var instanceof Expr\Variable
                || $arg->value->name !== $param->var->name
            ) {
                return null;
            }
        }

        $printer = new PrettyPrinter\Standard;

        return match (true) {
            $body instanceof Expr\MethodCall && $body->name instanceof Node\Identifier
                => $printer->prettyPrintExpr($body->var) . '->' . $body->name->toString() . '(...)',
            $body instanceof Expr\StaticCall && $body->class instanceof Node\Name && $body->name instanceof Node\Identifier
                => $body->class->toString() . '::' . $body->name->toString() . '(...)',
            $body instanceof Expr\FuncCall && $body->name instanceof Node\Name
                => $body->name->toString() . '(...)',
            default => null,
        };
    }

    /**
     * Whether the class extends the resolver base — a REAL chain resolver,
     * whose `resolvers()` entries must be named Predicates. A class merely
     * NAMED `*Resolver` (a domain service) is not policed this way: its helper
     * booleans are not chain predicates.
     */
    private function isChainResolver(Node\Stmt\Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        $extends = ltrim($class->extends->toString(), '\\');
        $short = $this->shortName($extends);

        // The scaffolded base is named `Resolver`.
        if ($short === 'Resolver') {
            return true;
        }

        $bases = $this->config('base_classes', []);

        if (is_array($bases)) {
            foreach ($bases as $base) {
                $baseFqcn = ltrim((string) $base, '\\');

                if ($extends === $baseFqcn || $short === $this->shortName($baseFqcn)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A Predicate instantiated and INVOKED inline (`(new IsNull())($x)`,
     * `((new IsNull())->or(new Equals(...)))($x)`) — making an object just to
     * call it once is worse than the plain test and scatters predicates
     * outside any chain.
     *
     * @param  array<Node>  $ast
     * @return list<array{0: int, 1: string}>
     */
    private function scatteredPredicateInvocations(NodeFinder $finder, array $ast): array
    {
        $uses = $this->collectUses($ast);
        $out = [];

        /** @var array<Expr\FuncCall> $calls */
        $calls = $finder->findInstanceOf($ast, Expr\FuncCall::class);

        foreach ($calls as $call) {
            // `(expr)(...)` — the callee is an expression, not a function name.
            if ($call->name instanceof Node\Name) {
                continue;
            }

            $rooted = $this->rootNewClass($call->name);

            if ($rooted === null) {
                continue;
            }

            if ($this->isPredicateFqcn($uses[$rooted] ?? $rooted)) {
                $out[] = [$call->getStartLine(), $rooted];
            }
        }

        // Form B: `$p = new Predicate(); … $p($x)` — instantiated into a local
        // and invoked. Same smell, laundered through a variable.
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class) as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $predVars = [];

            foreach ($finder->findInstanceOf($method->stmts, Expr\Assign::class) as $assign) {
                if ($assign->var instanceof Expr\Variable && is_string($assign->var->name)
                    && $assign->expr instanceof Expr\New_ && $assign->expr->class instanceof Node\Name
                ) {
                    $cls = $assign->expr->class->getLast();

                    if ($this->isPredicateFqcn($uses[$cls] ?? $cls)) {
                        $predVars[$assign->var->name] = [$cls, $assign->getStartLine()];
                    }
                }
            }

            if ($predVars === []) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Expr\FuncCall::class) as $call) {
                if ($call->name instanceof Expr\Variable && is_string($call->name->name)
                    && isset($predVars[$call->name->name])
                ) {
                    [$cls, $line] = $predVars[$call->name->name];
                    $out[$line . ':' . $cls] = [$line, $cls];
                }
            }
        }

        return array_values($out);
    }

    private function isPredicateFqcn(string $fqcn): bool
    {
        return str_contains($fqcn, '\\Predicates\\') || str_ends_with($fqcn, '\\Predicates');
    }

    /**
     * The short class name of the `new X(...)` at the root of an invoked
     * expression (through `->and()`/`->or()`/`->not()` chains), or null.
     */
    private function rootNewClass(Node $expr): ?string
    {
        if ($expr instanceof Expr\New_ && $expr->class instanceof Node\Name) {
            return $expr->class->getLast();
        }

        if ($expr instanceof Expr\MethodCall) {
            return $this->rootNewClass($expr->var);
        }

        return null;
    }

    private function scatteredMessage(string $name): string
    {
        return sprintf(
            'A `%s` predicate is instantiated and invoked inline for a single check — making an object just to call it once is worse than the plain test, and scatters predicates outside any chain. Use the plain expression here, or — if this is part of first-match dispatch — build a chain Resolver and put the predicate IN the chain.',
            $name,
        );
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string, string>  alias => FQCN
     */
    private function collectUses(array $ast): array
    {
        $uses = [];
        $finder = new NodeFinder;

        /** @var array<Node\Stmt\Use_> $useStmts */
        $useStmts = $finder->findInstanceOf($ast, Node\Stmt\Use_::class);

        foreach ($useStmts as $use) {
            foreach ($use->uses as $useUse) {
                $alias = $useUse->alias?->toString() ?? $useUse->name->getLast();
                $uses[$alias] = $useUse->name->toString();
            }
        }

        return $uses;
    }

    private function messageFor(Expr $expr, string $printed, string $baseName, NodeFinder $finder): string
    {
        // Reuse the kernel where a generic predicate already exists.
        $kernel = $this->kernelPredicateFor($expr);

        if ($kernel !== null) {
            return sprintf(
                'Inline predicate `%s` in a resolver — reuse the kernel `%s` from `Resolvers\\Predicates` instead of inlining the test.',
                $this->truncate($printed),
                $kernel,
            );
        }

        $domainBound = $finder->findInstanceOf([$expr], Expr\ClassConstFetch::class) !== [];

        $home = $domainBound
            ? sprintf('the resolver\'s own `Resolvers\\%s\\Predicates` (it reads a type\'s constants)', $baseName)
            : 'the shared `Resolvers\\Predicates` (it is generic)';

        return sprintf(
            'Inline predicate `%s` in a resolver — extract it to a named Predicate class in %s and reference that instead of inlining the test.',
            $this->truncate($printed),
            $home,
        );
    }

    /**
     * The kernel Predicate that already covers this test, if any.
     */
    private function kernelPredicateFor(Expr $expr): ?string
    {
        // `$x === null` / `null === $x`
        if (($expr instanceof Expr\BinaryOp\Identical || $expr instanceof Expr\BinaryOp\NotIdentical)
            && ($this->isNullConst($expr->left) || $this->isNullConst($expr->right))
        ) {
            return 'IsNull';
        }

        // `$x instanceof SomeType` — `IsEnum::for(...)` when the type is an enum,
        // `HasClass::of(...)` when dispatching on an object's class/interface.
        if ($expr instanceof Expr\Instanceof_) {
            return 'IsEnum::for(...)/HasClass::of(...)';
        }

        // `str_starts_with($x, PREFIX)`
        if ($expr instanceof Expr\FuncCall && $expr->name instanceof Node\Name
            && strtolower($expr->name->toString()) === 'str_starts_with'
        ) {
            return 'HasPrefix';
        }

        return null;
    }

    /**
     * @param  array{method: string, type: string, guards: int, kind: string, resolver: string}  $chain
     */
    private function nominateMessage(array $chain): string
    {
        if ($chain['kind'] === 'composite_predicate') {
            return sprintf(
                '%s() is a boolean decision stitched from %d predicate guards — that is a composite Predicate, not a resolver. Build it from named Predicate objects combined with `->and()`/`->or()`/`->not()` (the kernel `AllOf`/`AnyOf`/`Negated`), reusing `IsNull`/`IsEnum` where they fit, instead of an if/match chain.',
                $chain['method'],
                $chain['guards'],
            );
        }

        return sprintf(
            '%s() is a first-match dispatch chain — %d predicate guards each producing a %s. That is a resolver in disguise: compose it with `Resolver::firstResultWins(...)` (or `Resolver::collect(...)` to gather all matches), each guard becoming a NAMED Predicate paired with a factory via `->then(...)` — reuse `IsNull`/`IsEnum`/`HasPrefix`, create domain Predicates for type-specific tests. Do NOT compose it from inline `fn (...) => test ? … : null` closures — that is the same chain with extra boilerplate.',
            $chain['method'],
            $chain['guards'],
            $chain['type'],
        );
    }

    /**
     * Methods that are a first-match dispatch chain — a resolver in disguise.
     *
     * @return list<array{method: string, type: string, guards: int, resolver: string, line: int}>
     */
    private function dispatchChainMethods(NodeFinder $finder, Node\Stmt\Class_ $class): array
    {
        $out = [];

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $chain = $this->asDispatchChain($finder, $method);

            if ($chain === null) {
                continue;
            }

            // Name the suggested resolver after the produced type — the class
            // itself when it builds `self`, otherwise the constructed class.
            $produced = in_array($chain['type'], ['self', 'static'], true)
                ? ($class->name?->toString() ?? 'Type')
                : $chain['type'];

            $out[] = [...$chain, 'resolver' => $produced, 'line' => $method->getStartLine()];
        }

        return $out;
    }

    /**
     * A method qualifies as a first-match dispatch chain when >= 3 of its
     * decisions are predicate-guarded — counting both `if (pred) { return … }`
     * guards AND `match (true) { pred => … }` arms. The PRODUCED type is the
     * method's declared return type when present, else the common construction
     * class. A `bool` producer is a composite predicate, not a resolver.
     *
     * @return array{method: string, type: string, guards: int, kind: string}|null
     */
    private function asDispatchChain(NodeFinder $finder, Node\Stmt\ClassMethod $method): ?array
    {
        $guards = $this->countGuards($finder, $method);

        if ($guards < 3) {
            return null;
        }

        $returnType = $this->declaredReturnType($method);

        // A bool producer is a composite Predicate (compose from the kernel),
        // not a resolver.
        if ($returnType !== null && strtolower($returnType) === 'bool') {
            return ['method' => $method->name->toString(), 'type' => 'bool', 'guards' => $guards, 'kind' => 'composite_predicate'];
        }

        // Carve-out (#17): a first-match dispatch chain produces DISTINCT results
        // per branch. When the guards all early-return the SAME expression (a
        // shared fallback / validity gate, not predicate->factory alternatives),
        // or the body is a procedure that transforms/throws (a try/catch), it is
        // not a resolver — forcing it into one would obscure the gate logic.
        if ($this->hasTryCatch($method) || $this->distinctGuardReturns($finder, $method) < 2) {
            return null;
        }

        // Anchor the produced type: the declared return type, or — when none —
        // the construction class shared by every return (the stricter signal).
        $type = $returnType !== null && ! in_array(strtolower($returnType), ['void', 'never', 'mixed'], true)
            ? $returnType
            : $this->commonConstructionType($finder, $method);

        if ($type === null) {
            return null;
        }

        return ['method' => $method->name->toString(), 'type' => $type, 'guards' => $guards, 'kind' => 'resolver'];
    }

    /**
     * Count predicate-guarded decisions: `if (pred) { return … }` guards and
     * `match (true) { pred => … }` arms.
     */
    private function countGuards(NodeFinder $finder, Node\Stmt\ClassMethod $method): int
    {
        $guards = 0;

        foreach ($finder->findInstanceOf($method->stmts, Node\Stmt\If_::class) as $if) {
            if ($if->else === null && $if->elseifs === [] && $this->isPredicateExpr($if->cond)
                && count($if->stmts) === 1 && $if->stmts[0] instanceof Node\Stmt\Return_
            ) {
                $guards++;
            }
        }

        foreach ($finder->findInstanceOf($method->stmts, Expr\Match_::class) as $match) {
            if (! $this->isTrueConst($match->cond)) {
                continue;
            }

            foreach ($match->arms as $arm) {
                foreach ($arm->conds ?? [] as $cond) {
                    if ($this->isPredicateExpr($cond)) {
                        $guards++;
                    }
                }
            }
        }

        return $guards;
    }

    /**
     * Whether the method body contains a try/catch — a marker of a procedure
     * that transforms / throws, the scripture's documented carve-out (#17).
     */
    private function hasTryCatch(Node\Stmt\ClassMethod $method): bool
    {
        return (new NodeFinder)->findFirstInstanceOf($method->stmts ?? [], Node\Stmt\TryCatch::class) !== null;
    }

    /**
     * The number of DISTINCT expressions the predicate guards return — `if (pred)
     * { return X }` guards and `match (true) { pred => X }` arms. A genuine
     * dispatch chain produces a different result per branch; guards that all
     * return the SAME fallback are a validity gate, not a resolver (#17).
     */
    private function distinctGuardReturns(NodeFinder $finder, Node\Stmt\ClassMethod $method): int
    {
        $printer = new PrettyPrinter\Standard;
        $exprs = [];

        foreach ($finder->findInstanceOf($method->stmts, Node\Stmt\If_::class) as $if) {
            if ($if->else === null && $if->elseifs === [] && $this->isPredicateExpr($if->cond)
                && count($if->stmts) === 1 && $if->stmts[0] instanceof Node\Stmt\Return_
                && $if->stmts[0]->expr !== null
            ) {
                $exprs[$printer->prettyPrintExpr($if->stmts[0]->expr)] = true;
            }
        }

        foreach ($finder->findInstanceOf($method->stmts, Expr\Match_::class) as $match) {
            if (! $this->isTrueConst($match->cond)) {
                continue;
            }

            foreach ($match->arms as $arm) {
                foreach ($arm->conds ?? [] as $cond) {
                    if ($this->isPredicateExpr($cond)) {
                        $exprs[$printer->prettyPrintExpr($arm->body)] = true;
                    }
                }
            }
        }

        return count($exprs);
    }

    /**
     * The construction class shared by EVERY return, or null when the returns
     * are not all constructions of one class.
     */
    private function commonConstructionType(NodeFinder $finder, Node\Stmt\ClassMethod $method): ?string
    {
        /** @var array<Node\Stmt\Return_> $returns */
        $returns = $finder->findInstanceOf($method->stmts, Node\Stmt\Return_::class);

        if (count($returns) < 4) {
            return null;
        }

        $type = null;

        foreach ($returns as $return) {
            if ($return->expr === null) {
                return null;
            }

            $constructed = $this->constructionTargetClass($return->expr);

            if ($constructed === null || ($type !== null && $type !== $constructed)) {
                return null;
            }

            $type = $constructed;
        }

        return $type;
    }

    private function declaredReturnType(Node\Stmt\ClassMethod $method): ?string
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }

        if ($type instanceof Node\Name) {
            return $type->getLast();
        }

        return null;
    }

    /**
     * The short class name a `Type::factory(...)` / `new Type(...)` constructs,
     * or null when the expression is not a construction.
     */
    private function constructionTargetClass(Expr $expr): ?string
    {
        if ($expr instanceof Expr\StaticCall && $expr->class instanceof Node\Name) {
            return $expr->class->getLast();
        }

        if ($expr instanceof Expr\New_ && $expr->class instanceof Node\Name) {
            return $expr->class->getLast();
        }

        return null;
    }

    /**
     * Every extractable predicate used as a DECISION inside the resolver — the
     * "is this the one?" tests that drive the chain. Collected from the shapes
     * a chain actually takes:
     *
     *   - `fn (...) => COND ? result : null`        (skip-or-match closure)
     *   - `fn (...): bool => COND`                  (boolean matcher closure)
     *   - `if (COND) { return … }`                  (guard-chain branch)
     *   - `match (true) { COND => …, … }`           (predicate-arm match)
     *
     * Transforms are excluded: a value-mapping ternary (`$x instanceof Foo ?
     * $x : …`, both arms values) and a bare `var === var` equality (not a
     * reusable named concept) never qualify.
     *
     * @return list<array{0: Expr, 1: int}>
     */
    private function chainClosurePredicates(NodeFinder $finder, Node\Stmt\Class_ $class): array
    {
        $out = [];

        /** @var array<Expr\ArrowFunction> $arrows */
        $arrows = $finder->findInstanceOf([$class], Expr\ArrowFunction::class);

        foreach ($arrows as $arrow) {
            $predicate = $this->chainPredicateOf($arrow);

            if ($predicate !== null) {
                $out[] = [$predicate, $predicate->getStartLine()];
            }
        }

        return $out;
    }


    /**
     * The extractable predicate of a chain-matcher arrow function, or null when
     * the arrow function is not a matcher (e.g. a transform).
     */
    private function chainPredicateOf(Expr\ArrowFunction $arrow): ?Expr
    {
        $body = $arrow->expr;

        // `COND ? result : null` / `COND ? null : result` — a skip-or-match
        // chain entry. One arm MUST be the null sentinel.
        if ($body instanceof Expr\Ternary
            && $body->cond !== null
            && ($this->isNullConst($body->if) || $this->isNullConst($body->else))
            && $this->isPredicateExpr($body->cond)
        ) {
            return $body->cond;
        }

        // `fn (...): bool => <predicate>` — an explicit boolean matcher.
        if ($this->returnsBool($arrow) && $this->isPredicateExpr($body)) {
            return $body;
        }

        return null;
    }

    private function isNullConst(?Node $node): bool
    {
        return $node instanceof Expr\ConstFetch
            && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'null';
    }

    private function returnsBool(Expr\ArrowFunction $arrow): bool
    {
        return $arrow->returnType instanceof Node\Identifier
            && strtolower($arrow->returnType->toString()) === 'bool';
    }

    private function isPredicateExpr(Expr $expr): bool
    {
        if ($expr instanceof Expr\Instanceof_) {
            return true;
        }

        // A comparison only reads as a reusable predicate when it tests against
        // a KNOWN value — `$x === null`, `$type === WireType::MIXED`. A bare
        // `$a === $b` (two runtime values) is not a named concept.
        if ($expr instanceof Expr\BinaryOp\Identical
            || $expr instanceof Expr\BinaryOp\NotIdentical
            || $expr instanceof Expr\BinaryOp\Equal
            || $expr instanceof Expr\BinaryOp\NotEqual
        ) {
            return $this->isConstantish($expr->left) || $this->isConstantish($expr->right);
        }

        // Logical combinations — predicate-shaped if any operand is.
        if ($expr instanceof Expr\BooleanNot) {
            return $this->isPredicateExpr($expr->expr);
        }

        if ($expr instanceof Expr\BinaryOp\BooleanAnd || $expr instanceof Expr\BinaryOp\BooleanOr) {
            return $this->isPredicateExpr($expr->left) || $this->isPredicateExpr($expr->right);
        }

        if ($expr instanceof Expr\FuncCall && $expr->name instanceof Node\Name) {
            $fn = strtolower($expr->name->toString());

            return in_array($fn, self::PREDICATE_FUNCTIONS, true) || str_starts_with($fn, 'is_');
        }

        return false;
    }

    /**
     * A known, named value — a literal, a global constant (null/true/false…),
     * or a class constant / enum case. NOT a plain variable or property.
     */
    private function isConstantish(Node $node): bool
    {
        return $node instanceof Node\Scalar
            || $node instanceof Expr\ConstFetch
            || $node instanceof Expr\ClassConstFetch;
    }

    private function isTrueConst(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch
            && $expr->name instanceof Node\Name
            && strtolower($expr->name->toString()) === 'true';
    }

    private function isResolverClass(Node\Stmt\Class_ $class): bool
    {
        $suffix = (string) $this->config('suffix', self::DEFAULT_SUFFIX);

        if ($class->name !== null && $suffix !== '' && str_ends_with($class->name->toString(), $suffix)) {
            return true;
        }

        $bases = $this->config('base_classes', []);

        if ($class->extends !== null && is_array($bases)) {
            $extends = ltrim($class->extends->toString(), '\\');

            foreach ($bases as $base) {
                $baseFqcn = ltrim((string) $base, '\\');
                $baseShort = $this->shortName($baseFqcn);

                if ($extends === $baseFqcn || $extends === $baseShort || str_ends_with($extends, '\\' . $baseShort)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolverBaseName(Node\Stmt\Class_ $class): string
    {
        $name = $class->name?->toString() ?? 'Resolver';
        $suffix = (string) $this->config('suffix', self::DEFAULT_SUFFIX);

        if ($suffix !== '' && str_ends_with($name, $suffix) && $name !== $suffix) {
            return substr($name, 0, -strlen($suffix));
        }

        return $name;
    }

    private function truncate(string $value, int $max = 60): string
    {
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return strlen($value) > $max ? substr($value, 0, $max - 1) . '…' : $value;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
