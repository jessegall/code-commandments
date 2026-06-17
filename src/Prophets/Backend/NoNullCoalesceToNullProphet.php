<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Two related defects around `null` and the coalesce operator:
 *
 *   1. `EXPR ?? null` is a no-op — coalescing to null returns the left side
 *      unchanged, so it is exactly `EXPR`. (`T_Array::coalesce($x ?? null)` is
 *      the same waste wrapped in ceremony.)
 *
 *   2. `foreach (NULLABLE as ...)` over a value that can be null is a latent
 *      TypeError, and `?? null` "guards" it by iterating null — which still
 *      throws. The fix is `?? []`: an empty array iterates zero times, which is
 *      exactly skipping the loop when the value is absent.
 *
 * Both are auto-fixable: the no-op `?? null` is stripped; a nullable foreach
 * iterable gets `?? []`.
 */
#[IntroducedIn('1.90.0')]
class NoNullCoalesceToNullProphet extends PhpCommandment implements SinRepenter
{
    public function description(): string
    {
        return 'Drop the no-op `?? null`; guard a nullable foreach with `?? []`';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `?? null` (which returns the left side unchanged — a no-op), or a '
                . '`foreach` over a value that can be null (a nullsafe `?->` chain, '
                . 'or one "guarded" with the iterate-null `?? null`).'
            )
            ->leaveWhen(
                'The right-hand side of `??` is a real fallback (not `null`), or the '
                . 'foreach iterates a value that genuinely cannot be null.'
            )
            ->whenUnsure(
                'For an iterable, `?? []` is the safe, complete fix — an empty array '
                . 'runs the loop zero times, the same as guarding it. An explicit '
                . '`if`/early-return is a stylistic equivalent when it reads better.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`$x ?? null` is the null-coalescing operator told to fall back to null — which
is what it already returns when the left side is null. It is `$x`, longer.
`T_Array::coalesce($x ?? null)` is that same no-op wrapped in a helper call.

Iterating a value that can be null is a latent TypeError, and the popular
"guard" `?? null` does nothing — `foreach (null as …)` still throws. The fix is
`?? []`: an empty array iterates zero times, which is precisely "skip the loop
when the value is absent". That makes `?? []` functionally identical to wrapping
the loop in `if ($x !== null)` or guarding with an early return — without
restructuring the code.

Bad:
    $name = $row->label ?? null;                       // no-op — it is $row->label
    foreach ($obj?->getItems() ?? null as $item) {}    // ?? null still iterates null
    foreach (T_Array::coalesce($x ?? null) as $i) {}   // wrapped no-op

Good:
    $name = $row->label;
    foreach ($obj?->getItems() ?? [] as $item) {}      // empty when absent → loop skipped

Good — equivalent explicit guards (use when they read better):
    // early return, when the loop is the last thing the method does:
    $items = $obj?->getItems();
    if ($items === null) {
        return;
    }
    foreach ($items as $item) { … }

    // if-wrap, when work follows the loop:
    if (($items = $obj?->getItems()) !== null) {
        foreach ($items as $item) { … }
    }

WHAT FIRES — a `Coalesce` whose right operand is the `null` literal, and a
`foreach` whose iterable is `EXPR ?? null` or a top-level nullsafe call/fetch
(`$x?->y()`).

WHAT DOES NOT — `?? $realFallback`, `?? []`, or a foreach over a value that is
not syntactically null-capable.

[AUTO-FIXABLE] — `repent` strips a no-op `?? null` and rewrites a nullable
foreach iterable to `?? []`. The if/early-return forms are left as guidance.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $sins = [];

        foreach ($this->findings($ast, $content) as $finding) {
            $sins[] = $this->sinAt(
                $finding['line'],
                $finding['message'],
                $finding['snippet'],
                null,
                $finding['symbol'],
                true,
            );
        }

        if ($sins === []) {
            return $this->righteous();
        }

        return $this->fallen($sins);
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

        $edits = [];
        $penance = [];

        foreach ($this->findings($ast, $content) as $finding) {
            $edits[] = $finding['edit'];
            $penance[] = $finding['penance'];
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        // Apply right-to-left so earlier byte offsets stay valid.
        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * Every defect in the file, each carrying both its finding metadata and the
     * byte-offset edit that repents it.
     *
     * @param  array<Node>  $ast
     * @return list<array{line: int, message: string, snippet: string, symbol: string, penance: string, edit: array{start: int, end: int, text: string}}>
     */
    private function findings(array $ast, string $content): array
    {
        $finder = new NodeFinder;
        $findings = [];

        // Foreach statements that sit last in their function body — where an
        // early-return guard reads cleanly (used only for the stylistic hint).
        $lastInFunction = $this->lastStatementForeaches($finder, $ast);

        // The Coalesce nodes that ARE a foreach iterable — handled as the
        // foreach case (→ `?? []`), so the no-op pass must not also strip them.
        $foreachCoalesce = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Foreach_::class) as $foreach) {
            $expr = $foreach->expr;
            $canEarlyReturn = isset($lastInFunction[spl_object_id($foreach)]);

            if ($expr instanceof Coalesce && $this->isNullLiteral($expr->right)) {
                $foreachCoalesce[spl_object_id($expr)] = true;
                $findings[] = $this->foreachCoalesceFinding($expr, $content, $canEarlyReturn);

                continue;
            }

            if ($expr instanceof Expr\NullsafeMethodCall || $expr instanceof Expr\NullsafePropertyFetch) {
                $findings[] = $this->foreachNullsafeFinding($expr, $content, $canEarlyReturn);
            }
        }

        foreach ($finder->findInstanceOf($ast, Coalesce::class) as $coalesce) {
            if (! $this->isNullLiteral($coalesce->right) || isset($foreachCoalesce[spl_object_id($coalesce)])) {
                continue;
            }

            // `?? null` is only a no-op when the left side is GUARANTEED to be
            // defined. On an array access / property / bare variable, `??`
            // suppresses the undefined-key / uninitialized-property notice — so
            // `$arr[$k] ?? null` and `$obj->prop ?? null` are NOT no-ops and must
            // be left alone.
            if (! $this->isAlwaysDefined($coalesce->left)) {
                continue;
            }

            $findings[] = $this->noopCoalesceFinding($coalesce, $content);
        }

        return $findings;
    }

    /**
     * `foreach (X ?? null …)` → `foreach (X ?? [] …)`.
     *
     * @return array{line: int, message: string, snippet: string, symbol: string, penance: string, edit: array{start: int, end: int, text: string}}
     */
    private function foreachCoalesceFinding(Coalesce $coalesce, string $content, bool $canEarlyReturn): array
    {
        $line = $coalesce->getStartLine();

        return [
            'line' => $line,
            'message' => '`?? null` does not guard this foreach — iterating null still throws. Use `?? []`: an empty array runs the loop zero times, which is exactly skipping it when the value is absent. ' . $this->guardHint($canEarlyReturn),
            'snippet' => $this->lineAt($content, $line),
            'symbol' => 'foreach-coalesce-null',
            'penance' => 'Replaced `?? null` with `?? []` on a foreach iterable',
            'edit' => [
                'start' => $coalesce->right->getStartFilePos(),
                'end' => $coalesce->right->getEndFilePos(),
                'text' => '[]',
            ],
        ];
    }

    /**
     * `foreach ($x?->y() …)` → `foreach ($x?->y() ?? [] …)`.
     *
     * @return array{line: int, message: string, snippet: string, symbol: string, penance: string, edit: array{start: int, end: int, text: string}}
     */
    private function foreachNullsafeFinding(Expr $expr, string $content, bool $canEarlyReturn): array
    {
        $line = $expr->getStartLine();
        $source = $this->sourceOf($expr, $content);

        return [
            'line' => $line,
            'message' => 'This foreach iterates a nullsafe chain that can be null — a latent TypeError. Add `?? []` so it iterates zero times when absent. ' . $this->guardHint($canEarlyReturn),
            'snippet' => $this->lineAt($content, $line),
            'symbol' => 'foreach-nullsafe',
            'penance' => 'Guarded a nullable foreach iterable with `?? []`',
            'edit' => [
                'start' => $expr->getStartFilePos(),
                'end' => $expr->getEndFilePos(),
                'text' => $source . ' ?? []',
            ],
        ];
    }

    /**
     * `EXPR ?? null` → `EXPR`.
     *
     * @return array{line: int, message: string, snippet: string, symbol: string, penance: string, edit: array{start: int, end: int, text: string}}
     */
    private function noopCoalesceFinding(Coalesce $coalesce, string $content): array
    {
        $line = $coalesce->getStartLine();

        return [
            'line' => $line,
            'message' => '`?? null` is a no-op — coalescing to null returns the left side unchanged, so this is exactly `' . $this->sourceOf($coalesce->left, $content) . '`. Drop the `?? null`.',
            'snippet' => $this->lineAt($content, $line),
            'symbol' => 'coalesce-null',
            'penance' => 'Removed no-op `?? null`',
            'edit' => [
                'start' => $coalesce->getStartFilePos(),
                'end' => $coalesce->getEndFilePos(),
                'text' => $this->sourceOf($coalesce->left, $content),
            ],
        ];
    }

    /**
     * Clever guidance: if the foreach is the last statement of its function, an
     * early return reads cleanly; otherwise suggest an if-wrap. Either is
     * equivalent to the `?? []` we apply — this is a stylistic note only.
     */
    private function guardHint(bool $canEarlyReturn): string
    {
        if ($canEarlyReturn) {
            return '(Or, since the loop is the last thing here, an early return: `if ($x === null) { return; }` before the loop.)';
        }

        return '(Or wrap the loop in `if ($x !== null) { … }` so the work after it still runs.)';
    }

    /**
     * The set (by object id) of foreach statements that sit last in their
     * enclosing function/method body — where an early-return guard would skip
     * no later work.
     *
     * @param  array<Node>  $ast
     * @return array<int, true>
     */
    private function lastStatementForeaches(NodeFinder $finder, array $ast): array
    {
        $last = [];

        foreach ($finder->findInstanceOf($ast, Node\FunctionLike::class) as $function) {
            $stmts = $function->getStmts();

            if ($stmts === null || $stmts === []) {
                continue;
            }

            $tail = end($stmts);

            if ($tail instanceof Node\Stmt\Foreach_) {
                $last[spl_object_id($tail)] = true;
            }
        }

        return $last;
    }

    private function isNullLiteral(Expr $expr): bool
    {
        return $expr instanceof Expr\ConstFetch && strtolower($expr->name->toString()) === 'null';
    }

    /**
     * Whether an expression always yields a defined value, so `?? null` adds
     * nothing. Call returns, `new`, and literals/constants qualify. An array
     * access, property fetch, or bare variable does NOT — there `??` suppresses
     * an undefined-key / uninitialized-property / undefined-variable notice, so
     * the `?? null` is load-bearing.
     */
    private function isAlwaysDefined(Expr $expr): bool
    {
        return $expr instanceof Expr\FuncCall
            || $expr instanceof Expr\MethodCall
            || $expr instanceof Expr\NullsafeMethodCall
            || $expr instanceof Expr\StaticCall
            || $expr instanceof Expr\New_
            || $expr instanceof Node\Scalar
            || $expr instanceof Expr\ConstFetch
            || $expr instanceof Expr\ClassConstFetch;
    }

    private function sourceOf(Node $node, string $content): string
    {
        return substr($content, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1);
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
