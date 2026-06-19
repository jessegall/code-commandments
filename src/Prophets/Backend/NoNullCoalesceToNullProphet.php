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
 * `EXPR ?? null` is a no-op — coalescing to null returns the left side
 * unchanged, so it is exactly `EXPR`. (`T_Array::coalesce($x ?? null)` is the
 * same waste wrapped in ceremony.) `repent` strips it.
 *
 * It fires only when the left side is GUARANTEED to be defined (a call return,
 * `new`, a literal/constant) — on an array access / property / bare variable the
 * `?? null` suppresses an undefined-key/uninitialized notice, so it is load-
 * bearing and left alone. A `foreach` iterable is also left alone (defaulting a
 * nullable array is PreferTypeCoalesce's job via `T_Array::coalesce()`).
 */
#[IntroducedIn('1.90.0')]
class NoNullCoalesceToNullProphet extends PhpCommandment implements SinRepenter
{
    public function description(): string
    {
        return 'Drop the no-op `?? null` — it returns the left side unchanged';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `?? null` whose left side is always defined (a call return, `new`, '
                . 'a literal/constant) — coalescing to null returns it unchanged, so '
                . 'the `?? null` is pure noise.'
            )
            ->leaveWhen(
                'The right-hand side of `??` is a real fallback (not `null`), or the '
                . 'left side is an array access / property / bare variable where `?? null` '
                . 'suppresses an undefined-key / uninitialized notice (load-bearing).'
            )
            ->whenUnsure(
                'If the value can legitimately be absent and you need a default, give a '
                . 'real one (or T_X::coalesce(...) for a nullable typed value) — not `?? null`.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`$x ?? null` is the null-coalescing operator told to fall back to null — which
is what it already returns when the left side is null. It is `$x`, longer.
`T_Array::coalesce($x ?? null)` is that same no-op wrapped in a helper call.

Bad:
    $name = $this->label() ?? null;     // no-op — it is $this->label()
    return $this->build() ?? null;      // no-op — it is $this->build()

Good:
    $name = $this->label();
    return $this->build();

WHAT FIRES — a `Coalesce` whose right operand is the `null` literal AND whose
left side is GUARANTEED to be defined (a function/method/static call, `new`, a
scalar, or a constant).

WHAT DOES NOT — `?? $realFallback`; a `?? null` on an array access / property /
bare variable (there it suppresses an undefined-key / uninitialized-property /
undefined-variable notice, so it is load-bearing); or a `foreach` iterable
(defaulting a nullable array is PreferTypeCoalesce's job — `T_Array::coalesce()`).

[AUTO-FIXABLE] — `repent` strips the no-op `?? null`.
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

        // A Coalesce that IS a foreach iterable is left entirely alone — we no
        // longer add `?? []` guards (defaulting a nullable array is
        // PreferTypeCoalesce's job), and we must not strip its `?? null` and
        // expose an unguarded nullable foreach.
        $foreachCoalesce = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Foreach_::class) as $foreach) {
            if ($foreach->expr instanceof Coalesce) {
                $foreachCoalesce[spl_object_id($foreach->expr)] = true;
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
