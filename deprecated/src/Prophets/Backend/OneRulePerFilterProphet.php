<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * A `->filter()` / `->reject()` closure should express ONE rule. When the predicate
 * is a compound boolean, the chain reads like a paragraph instead of a checklist:
 *
 *   - `->filter(fn ($x) => $x->active && $x->visible)` ANDs two rules into one
 *     closure — split them into one `->filter()` per rule, so each line is a single
 *     readable condition (chained filters are an AND, so this is equivalent).
 *   - `->filter(fn ($x) => ! ($x->a && $x->b))` is a double negative — a filter that
 *     keeps everything NOT matching. `!(A && B)` is logically `(!A || !B)`, an OR, so
 *     it can't be split into chained filters; say it positively with `->reject(A && B)`.
 *
 * Conservative: only a TOP-LEVEL boolean combination fires (an `&&` chain, or a `!`
 * wrapping a compound). A single comparison — `fn ($x) => $x->active`, `fn ($x) =>
 * $x->count > 3` — is already one rule and is left alone. Native `array_filter` is
 * out of scope (this is about the fluent Collection chain).
 */
#[IntroducedIn('3.5.0')]
class OneRulePerFilterProphet extends PhpCommandment
{
    /** The chain methods this rule reads — Collection-style predicates. */
    private const FILTER_METHODS = ['filter', 'reject'];

    public function description(): string
    {
        return 'A filter()/reject() closure should hold ONE rule — split an && chain, and say !(…) as reject(…)';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A `->filter()` or `->reject()` is passed a closure whose WHOLE body is a top-level boolean combination: an `&&` chain (`fn ($x) => $x->a && $x->b`), or a negation wrapping a compound (`fn ($x) => !($x->a && $x->b)`). Each is more than one rule in one closure.')
            ->leaveWhen('the predicate is a single rule (one comparison / method call / property — `fn ($x) => $x->active`, `fn ($x) => $x->count() > 3`), OR a top-level `||` (an either/or rule that does not split into chained AND filters), OR the receiver is not a Collection-like pipeline (it has no `filter`/`reject` that compose this way).')
            ->whenUnsure('if the closure body literally reads as "A and B", split it into `->filter(A)->filter(B)`; if it reads "not (A and B)", flip it to `->reject(A && B)`. If it is a single condition, leave it.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A filter closure is a rule. When it holds more than one rule — joined with `&&`,
or hidden behind a `!(…)` — the reader has to take the whole boolean apart before
they know what survives the filter. One rule per closure keeps the chain scannable:
each line is a single condition you can read and move on.

Bad — two rules ANDed into one closure:
    ->filter(static fn (User $u): bool => $u->active && $u->verified)

Good — one rule per filter (chained filters are an AND, so this is identical):
    ->filter(static fn (User $u): bool => $u->active)
    ->filter(static fn (User $u): bool => $u->verified)

Bad — a double negative: "keep everything that is NOT (has a type AND name matches it)":
    ->filter(static fn (NodeOutput $o): bool => ! ($o->schemaType !== null && $o->name === $o->schemaType))

Good — say it positively. reject() removes what matches, so the `!` disappears:
    ->reject(static fn (NodeOutput $o): bool => $o->schemaType !== null && $o->name === $o->schemaType)

`!(A && B)` is logically `(!A || !B)` — an OR — so it can NOT be split into chained
filters (those are an AND). reject() is the readable home for it.

Left alone — a single rule is already atomic:
    ->filter(static fn (User $u): bool => $u->active)
    ->filter(static fn (Order $o): bool => $o->total > 100)

Also left alone — a top-level `||`: it is one either/or rule, and chained filters
(an AND) can not express it:
    ->filter(static fn (Job $j): bool => $j->urgent || $j->overdue)
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier
                || ! in_array($call->name->toString(), self::FILTER_METHODS, true)
            ) {
                continue;
            }

            $predicate = $this->predicateOf($call);

            if ($predicate === null) {
                continue;
            }

            $warning = $this->inspect($call->name->toString(), $predicate, $call->getStartLine(), $content);

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The single boolean expression a `filter`/`reject` closure evaluates — the
     * arrow-function body, or a single-`return` closure's returned expression.
     * Multi-statement closures and non-closure args are out of scope (null).
     */
    private function predicateOf(Expr\MethodCall $call): ?Expr
    {
        $first = $call->args[0] ?? null;

        if (! $first instanceof Node\Arg) {
            return null;
        }

        if ($first->value instanceof Expr\ArrowFunction) {
            return $first->value->expr;
        }

        if ($first->value instanceof Expr\Closure) {
            $statements = $first->value->stmts;

            if (count($statements) === 1 && $statements[0] instanceof Node\Stmt\Return_ && $statements[0]->expr !== null) {
                return $statements[0]->expr;
            }
        }

        return null;
    }

    private function inspect(string $method, Expr $predicate, int $line, string $content): ?Warning
    {
        $snippet = $this->lineSnippet($content, $line);

        // `!(A && B)` / `!(A || B)` — a double negative on a filter. Say it as reject().
        if ($predicate instanceof Expr\BooleanNot && $this->isCompound($predicate->expr)) {
            $message = $method === 'filter'
                ? 'Double-negative filter — `filter(fn => !(…))` keeps what does NOT match. Say it positively with `->reject(…)` (drop the `!`). `!(A && B)` is an OR, so it can\'t split into chained filters.'
                : 'Double-negative reject — `reject(fn => !(…))` is a filter in disguise. Drop the `!` and use `->filter(…)`.';

            return $this->warningAt($line, $message, $snippet, $method . '-double-negative');
        }

        // `A && B` in a FILTER — split into one rule per filter (chained filters AND).
        if ($method === 'filter' && $this->isAndChain($predicate)) {
            $count = $this->conjunctCount($predicate);

            return $this->warningAt(
                $line,
                sprintf('Compound filter predicate — %d rules ANDed into one closure. Split into one `->filter()` per rule (chained filters are an AND, so it is equivalent) — each line then reads as a single condition.', $count),
                $snippet,
                'filter-and-chain',
            );
        }

        return null;
    }

    private function isAndChain(Expr $expr): bool
    {
        return $expr instanceof Expr\BinaryOp\BooleanAnd || $expr instanceof Expr\BinaryOp\LogicalAnd;
    }

    private function isCompound(Expr $expr): bool
    {
        return $expr instanceof Expr\BinaryOp\BooleanAnd
            || $expr instanceof Expr\BinaryOp\LogicalAnd
            || $expr instanceof Expr\BinaryOp\BooleanOr
            || $expr instanceof Expr\BinaryOp\LogicalOr;
    }

    private function conjunctCount(Expr $expr): int
    {
        if ($this->isAndChain($expr)) {
            /** @var Expr\BinaryOp $expr */
            return $this->conjunctCount($expr->left) + $this->conjunctCount($expr->right);
        }

        return 1;
    }
}
