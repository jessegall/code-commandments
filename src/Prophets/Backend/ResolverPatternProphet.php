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
                . '(`IsNull`/`IsEnum`/`HasPrefix`) and creating domain Predicates '
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
a Resolver of NAMED Predicate objects, NOT an if/match chain and NOT a
resolver full of inline closures. Each test becomes a class you can name,
reuse, and compose. The package SCAFFOLDS the building blocks — run
`scaffold`: a `Resolver` base and a `Predicate` kernel (`IsNull`, `IsEnum`,
`HasPrefix`, `AllOf`/`AnyOf`/`Negated`) under `Support\Resolvers`.

When you fix a finding from this rule, do the WHOLE job — half-measures (a
resolver whose entries are still inline `fn (...) => test ? … : null`) are a
SIN, not progress.

MODE 1 — DISPATCH CHAIN → RESOLVER
----------------------------------
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
produce one type. Extract to a Resolver and DELEGATE:

    public static function parse(?string $token): self
    {
        return new WireTypeResolver()->resolve($token) ?? self::classRef($token);
    }

    final class WireTypeResolver extends Resolver   // Resolvers\WireType\
    {
        protected function resolvers(): iterable
        {
            return [
                // reuse kernel predicates; first-class callables for the result
                new IsNull()->when(WireType::mixed(...)),
                self::prefixed(WireType::RESOURCE_PREFIX, WireType::resource(...)),
                self::nested(WireType::LIST_PREFIX,       WireType::listOf(...)),  // remainder pre-parsed
                new IsScalarToken()->when(WireType::scalar(...)),                  // domain predicate
            ];
        }
    }

Each entry is a NAMED Predicate + a result factory — never an inline test.

MODE 2 — BOOLEAN CHAIN → COMPOSITE PREDICATE
--------------------------------------------
A >= 3-guard method returning `bool` is a composite Predicate. Build it from
named Predicate objects combined with the kernel combinators:

    // was: if (a) return true; if (b) return true; if (c) return false; …
    new AnyOf(new IsMixed(), new IsListType(), new IsScalarType())

MODE 3 — EXTRACT a resolver's inline predicates
-----------------------------------------------
An existing resolver must read as named Predicates. An inline test is a
concept in disguise — give it a class:

  - REUSE THE KERNEL when one fits: `$x === null` → `IsNull`;
    `$x instanceof SomeEnum` → `IsEnum::for(...)`; `str_starts_with(...)` →
    `HasPrefix`. Do not re-create these.
  - GENERIC (no domain knowledge) but not in the kernel → add it to the
    SHARED `Support\Resolvers\Predicates\`.
  - DOMAIN-BOUND (reads a type's constants — `self::SCALARS`, `WireType::MIXED`)
    → that resolver's OWN `Support\Resolvers\<Name>\Predicates\`.

THE SIN — an UGLY resolver
--------------------------
A resolver whose chain is >= 3 inline predicate closures
(`fn (...) => test ? … : null`) is the original chain with extra boilerplate
— it gained nothing. That is a SIN: extract every test to a named Predicate
(reusing the kernel) and drive the chain with those.

WHAT DOES NOT FIRE — a value-mapping ternary (`$x instanceof Foo ? $x : …`,
both arms values), a bare `var === var` comparison (not a named concept), a
method that transforms / throws / returns unrelated shapes, and a `match`
on an enum subject (that is PreferTypeMethodOverInlineDispatch's rule).

Advisory for the nudges (naming + placement are yours); the ugly-resolver
case is a sin. Not auto-fixed.

Configuration:

    Backend\ResolverPatternProphet::class => [
        'suffix' => 'Resolver',          // class-name suffix that marks a resolver
        'base_classes' => [              // …or extend one of these
            // 'App\\Support\\Resolvers\\Resolver',
        ],
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

        foreach ($classes as $class) {
            if ($this->isResolverClass($class)) {
                $this->judgeResolver($finder, $printer, $class, $sins, $warnings);

                continue;
            }

            // Not a resolver yet — nominate one when a method is a first-match
            // dispatch chain (a resolver in disguise) or a composite predicate.
            foreach ($this->dispatchChainMethods($finder, $class) as $chain) {
                $warnings[] = $this->warningAt($chain['line'], $this->nominateMessage($chain), null, $chain['kind']);
            }
        }

        if ($sins === [] && $warnings === []) {
            return $this->righteous();
        }

        return new Judgment(sins: $sins, warnings: $warnings);
    }

    /**
     * An existing resolver: SIN it when >= 3 chain entries still inline a
     * predicate (the half-done extraction that buys nothing over the original
     * chain); otherwise nudge each remaining inline predicate out.
     *
     * @param  list<\JesseGall\CodeCommandments\Results\Sin>  $sins
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function judgeResolver(NodeFinder $finder, PrettyPrinter\Standard $printer, Node\Stmt\Class_ $class, array &$sins, array &$warnings): void
    {
        $baseName = $this->resolverBaseName($class);
        $closures = $this->chainClosurePredicates($finder, $class);

        if (count($closures) >= 3) {
            $line = $closures[0][1];
            $sins[] = $this->sinAt(
                $line,
                sprintf(
                    'This resolver is just %d inline predicate closures — a resolver of `fn (...) => test ? … : null` entries is the original chain with extra boilerplate. Extract every test to a named Predicate (reuse the kernel `IsNull`/`IsEnum`/`HasPrefix` where one fits, create a domain Predicate otherwise) and drive the chain with those.',
                    count($closures),
                ),
                null,
                null,
                'ugly-resolver',
            );
        } else {
            foreach ($closures as [$expr, $line]) {
                $warnings[] = $this->warningAt($line, $this->messageFor($expr, $printer->prettyPrintExpr($expr), $baseName, $finder), null, 'inline-predicate');
            }
        }

        // `if`/`match(true)` predicate conditions inside the resolver — always
        // a nudge to extract.
        $seen = [];

        foreach ($this->conditionPredicates($finder, $class) as [$expr, $line]) {
            $key = $line . ':' . $printer->prettyPrintExpr($expr);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $warnings[] = $this->warningAt($line, $this->messageFor($expr, $printer->prettyPrintExpr($expr), $baseName, $finder), null, 'inline-predicate');
        }
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

        // `$x instanceof SomeEnum`
        if ($expr instanceof Expr\Instanceof_) {
            return 'IsEnum::for(...)';
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
            '%s() is a first-match dispatch chain — %d predicate guards each producing a %s. That is a resolver in disguise: extract it to a Resolver (e.g. `Resolvers\\%s\\%sResolver` extending the kernel `Resolver`), each guard becoming a NAMED Predicate (reuse `IsNull`/`IsEnum`/`HasPrefix`; create domain ones for type-specific tests). Do NOT leave the predicates as inline `fn (...) => test ? … : null` closures — that is the same chain with extra boilerplate.',
            $chain['method'],
            $chain['guards'],
            $chain['type'],
            $chain['resolver'],
            $chain['resolver'],
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
     * Predicate conditions of `if`/`match (true)` inside the resolver.
     *
     * @return list<array{0: Expr, 1: int}>
     */
    private function conditionPredicates(NodeFinder $finder, Node\Stmt\Class_ $class): array
    {
        /** @var list<Expr> $candidates */
        $candidates = [];

        /** @var array<Node\Stmt\If_> $ifs */
        $ifs = $finder->findInstanceOf([$class], Node\Stmt\If_::class);

        foreach ($ifs as $if) {
            $candidates[] = $if->cond;

            foreach ($if->elseifs as $elseif) {
                $candidates[] = $elseif->cond;
            }
        }

        /** @var array<Expr\Match_> $matches */
        $matches = $finder->findInstanceOf([$class], Expr\Match_::class);

        foreach ($matches as $match) {
            if (! $this->isTrueConst($match->cond)) {
                continue; // only `match (true)` arms are predicate conditions
            }

            foreach ($match->arms as $arm) {
                foreach ($arm->conds ?? [] as $cond) {
                    $candidates[] = $cond;
                }
            }
        }

        $out = [];

        foreach ($candidates as $expr) {
            if ($this->isPredicateExpr($expr)) {
                $out[] = [$expr, $expr->getStartLine()];
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
