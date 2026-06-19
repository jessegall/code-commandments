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

/**
 * Flag a nullable return whose absence is never actually tolerated (#98 H2): a
 * PRIVATE method declares `?T` / `T | null` / `Option<T>`, yet EVERY call site
 * immediately de-nulls it — `?? throw`, `->getOrThrow()`/`->unwrap()`, or a blind
 * `->` dereference. The nullability is unearned ceremony: make the method total
 * (always return a value — a Null Object, an exhaustive match) or THROW at the
 * source, so the contract is honest in one place instead of re-asserted at every
 * caller.
 *
 * Scoped to PRIVATE methods so "every caller" is provable from the class alone —
 * no hidden out-of-file callers, no index, no false positives. The sibling rungs
 * of the same ladder live in other prophets: a closed-enum `default => null` →
 * {@see ThrowOnUnhandledCaseProphet}; a registry miss → {@see RegistryReturnContractProphet};
 * a nullable param normalised to a default → {@see PreferNullObjectDefaultsProphet};
 * a genuine value-or-nothing → {@see PreferOptionOverNullProphet}.
 *
 * Advisory, never a sin; not auto-fixable (totalising is a design call).
 */
#[IntroducedIn('1.134.0')]
class PreferTotalOverNullableProphet extends PhpCommandment
{
    /** Option de-null methods — calling one treats absence as impossible. */
    private const UNWRAP_METHODS = ['getorthrow', 'getorfail', 'unwrap'];

    public function description(): string
    {
        return 'Make a method total or throw when every caller already de-nulls its nullable return';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A PRIVATE method returns `?T` / `T | null` / `Option<T>`, but every call site immediately requires the value — `?? throw`, `->getOrThrow()`/`->unwrap()`, or a blind `->` dereference. The absence is never handled, so the nullability is unearned.')
            ->leaveWhen('any caller genuinely handles the absence — branches on null, supplies a real `?? $default`, uses `?->`, or passes it on as optional. Then the absence is real: keep it (as `Option<T>`, not raw null).')
            ->whenUnsure('if no caller ever tolerates "no value", make the method TOTAL (return a Null Object / an exhaustive match) or THROW a named exception at the source — declare the contract honestly once, not re-asserted at every call site.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A nullable return is a promise that "no value" is a real, handleable outcome.
When EVERY caller immediately un-hedges it — `?? throw`, `->getOrThrow()`, or a
blind `->` deref that assumes non-null — the promise was a lie: the value always
had to be there. The nullability just spreads defensive ceremony across callers
for a state none of them accept.

Bad — the method hedges, every caller un-hedges:
    private function root(): ?TreeNode { … }
    // caller A:
    $this->root() ?? throw new RuntimeException('no root');
    // caller B:
    $this->root()->id;                          // blind deref — assumes non-null

Good — make the contract honest once, at the source:
    private function root(): TreeNode
    {
        return $this->root ?? throw EmptyTreeException::create();
    }

WHAT FIRES — a PRIVATE method (so all callers are in this class) whose return type
is `?T` / `T | null` / an `Option`, with >= 1 in-class call site, where EVERY call
site de-nulls the result: `$this->m() ?? throw …`, `$this->m()->getOrThrow()` /
`->unwrap()`, or `$this->m()->member` (a non-nullsafe deref).

WHAT DOES NOT — any caller that handles the absence: a `?? $realDefault`, a `?->`
nullsafe chain, a `=== null` branch, assigning it without dereferencing, or
passing it on. A non-private method (callers may live elsewhere — not provable).
Advisory: totalise or throw is a design decision.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $parents = [];
        $this->buildParentMap($ast, null, $parents);
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->getMethods() as $method) {
                $kind = $method->isPrivate() && ! $method->isStatic() ? $this->returnKind($method) : null;

                if ($kind === null) {
                    continue;
                }

                $calls = $this->callsTo($class, $method->name->toString(), $finder);

                if ($calls === [] || ! $this->everyCallDeNulls($calls, $kind, $parents)) {
                    continue;
                }

                $line = $method->getStartLine();
                $warnings[] = $this->warningAt(
                    $line,
                    sprintf('%s() returns a nullable, but every call site immediately requires the value (`?? throw` / `->getOrThrow()` / a blind `->` deref) — the absence is never handled. Make it total (a Null Object / exhaustive match) or throw a named exception at the source, so the contract is honest once instead of re-asserted at every caller.', $method->name->toString()),
                    $this->lineAt($content, $line),
                    'unearned-nullable:' . $method->name->toString(),
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Classify the method's nullable return, or null if it isn't nullable:
     *  - 'nullable' — native `?T` / `T | null`. De-null = blind `->` deref or `?? throw`.
     *  - 'option'   — an `Option`-typed return. De-null = `->getOrThrow()`/`->unwrap()`
     *                 ONLY; `->getOr($default)` / `->map(…)` TOLERATE the absence.
     *
     * @return 'nullable'|'option'|null
     */
    private function returnKind(Node\Stmt\ClassMethod $method): ?string
    {
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

        return ($type instanceof Node\Name && str_ends_with($type->getLast(), 'Option')) ? 'option' : null;
    }

    /**
     * Every `$this->$name(...)` call within the class.
     *
     * @return list<Expr\MethodCall>
     */
    private function callsTo(Node\Stmt\Class_ $class, string $name, NodeFinder $finder): array
    {
        $calls = [];

        foreach ($finder->findInstanceOf($class->stmts, Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier
                && $call->name->toString() === $name
                && $call->var instanceof Expr\Variable
                && $call->var->name === 'this'
            ) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * @param  list<Expr\MethodCall>  $calls
     * @param  'nullable'|'option'  $kind
     * @param  array<int, Node>  $parents
     */
    private function everyCallDeNulls(array $calls, string $kind, array $parents): bool
    {
        foreach ($calls as $call) {
            if (! $this->callDeNulls($call, $kind, $parents)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether this call's result is immediately required (the absence rejected).
     * The rule differs by return kind — an `Option` has tolerant de-null methods
     * (`->getOr($default)`, `->map(…)`) that a native `?T` does not:
     *  - 'option'   — ONLY a `->getOrThrow()`/`->unwrap()` call requires it. Any
     *                 other chained method (`->getOr`, `->map`, `->andThen`) HANDLES
     *                 the none, so the Option is earned — not a de-null.
     *  - 'nullable' — `$this->m() ?? throw …`, or a PLAIN (non-nullsafe) `->member` /
     *                 `->method()` deref (assumes non-null). `?->` and `?? $default`
     *                 tolerate it.
     *
     * @param  'nullable'|'option'  $kind
     * @param  array<int, Node>  $parents
     */
    private function callDeNulls(Expr\MethodCall $call, string $kind, array $parents): bool
    {
        $parent = $parents[spl_object_id($call)] ?? null;

        if ($parent === null) {
            return false;
        }

        if ($kind === 'option') {
            return $parent instanceof Expr\MethodCall
                && $parent->var === $call
                && $parent->name instanceof Node\Identifier
                && in_array(strtolower($parent->name->toString()), self::UNWRAP_METHODS, true);
        }

        // 'nullable': `$this->m() ?? throw …` — coalesce whose fallback is a throw.
        if ($parent instanceof Expr\BinaryOp\Coalesce && $parent->left === $call) {
            return $parent->right instanceof Expr\Throw_;
        }

        // `$this->m()->...` — a PLAIN (non-nullsafe) method/property access requires
        // the result to be non-null.
        if ($parent instanceof Expr\MethodCall && $parent->var === $call) {
            return true;
        }

        return $parent instanceof Expr\PropertyFetch && $parent->var === $call;
    }

    /**
     * @param  array<int, Node>  $parents
     */
    private function buildParentMap(array $nodes, ?Node $parent, array &$parents): void
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node) {
                continue;
            }

            if ($parent !== null) {
                $parents[spl_object_id($node)] = $parent;
            }

            foreach ($node->getSubNodeNames() as $subName) {
                $child = $node->{$subName};
                $children = is_array($child) ? $child : [$child];
                $this->buildParentMap($children, $node, $parents);
            }
        }
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
