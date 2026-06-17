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
 * Inside a resolver, flag an inline predicate (a boolean test buried in a
 * closure) and suggest extracting it to a named Predicate class.
 */
#[IntroducedIn('1.59.0')]
class PreferExtractedPredicateProphet extends PhpCommandment
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
        return 'Extract inline predicates in a resolver into named Predicate classes';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A resolver chain inlines a boolean test as a closure — '
                . '`fn ($x) => str_starts_with($x, …) ? … : null`, an `instanceof`, '
                . 'a membership test — instead of a named Predicate. The test is a '
                . 'concept in disguise and is usually repeated across resolvers.'
            )
            ->leaveWhen(
                'The test is a genuine one-off with no reuse and reads clearly '
                . 'inline, or it is not really a predicate (it transforms rather '
                . 'than answers yes/no).'
            )
            ->whenUnsure(
                'If you can name the test (`HasPrefix`, `IsScalarToken`), extract '
                . 'it: generic tests go in the shared `Resolvers\\Predicates`, '
                . 'tests that read a specific type\'s constants go in that '
                . 'resolver\'s own `Resolvers\\<Name>\\Predicates`.'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A resolver should read as a list of NAMED predicates, not a pile of inline
boolean closures. An inline test is a concept in disguise — give it a name
and a class, then reuse and compose it.

Bad — the predicate is buried in the chain:

    protected function resolvers(): array
    {
        return [
            fn (string $t): ?WireType => str_starts_with($t, self::LIST_PREFIX)
                ? self::listOf(self::parse(...)) : null,
        ];
    }

Good — a named predicate, extracted:

    // Support\Resolvers\Predicates\HasPrefix  (generic — reusable)
    new HasPrefix(self::LIST_PREFIX)

WHERE THE PREDICATE GOES:

  - GENERIC (no domain knowledge — a prefix/null/instanceof test) → the
    SHARED kernel `Support\Resolvers\Predicates\`.
  - DOMAIN-BOUND (reads a specific type's constants — `self::SCALARS`,
    `WireType::MIXED`) → that resolver's OWN folder
    `Support\Resolvers\<Name>\Predicates\`, colocated with the resolver.

WHAT FIRES — inside a class named `*Resolver` (configurable suffix) or
extending a configured resolver base: a closure / arrow function whose
decision is a predicate — a call to str_starts_with / str_contains /
str_ends_with / in_array / array_search / array_key_exists / preg_match /
ctype_*, an `instanceof`, or a `=== / !==` comparison — used directly or as
a ternary condition.

This is advisory and NOT auto-fixed: naming the predicate and choosing
shared-vs-local placement are semantic judgements the tool will not guess.

Configuration:

    Backend\PreferExtractedPredicateProphet::class => [
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

        foreach ($classes as $class) {
            if (! $this->isResolverClass($class)) {
                continue;
            }

            $baseName = $this->resolverBaseName($class);
            $seen = [];

            foreach ($this->predicateExpressions($finder, $class) as [$expr, $line]) {
                $printed = $printer->prettyPrintExpr($expr);
                $key = $line . ':' . $printed;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $warnings[] = $this->warningAt($line, $this->messageFor($expr, $printed, $baseName, $finder), null, 'inline-predicate');
            }
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    private function messageFor(Expr $expr, string $printed, string $baseName, NodeFinder $finder): string
    {
        $domainBound = $finder->findInstanceOf([$expr], Expr\ClassConstFetch::class) !== [];

        $home = $domainBound
            ? sprintf('Resolvers\\%s\\Predicates (it reads a type\'s constants)', $baseName)
            : 'the shared Resolvers\\Predicates (it is generic)';

        return sprintf(
            'Inline predicate `%s` in a resolver — extract it to a named Predicate class in %s and reference that instead of inlining the test.',
            $this->truncate($printed),
            $home,
        );
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
    private function predicateExpressions(NodeFinder $finder, Node\Stmt\Class_ $class): array
    {
        /** @var list<Expr> $candidates */
        $candidates = [];

        /** @var array<Expr\ArrowFunction> $arrows */
        $arrows = $finder->findInstanceOf([$class], Expr\ArrowFunction::class);

        foreach ($arrows as $arrow) {
            $predicate = $this->chainPredicateOf($arrow);

            if ($predicate !== null) {
                $candidates[] = $predicate;
            }
        }

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
