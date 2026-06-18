<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * `T_Array::coalesce($array[$key] ?? null)` double-coalesces: the whole point of
 * `coalesce` is to BE the `?? []`, so wrapping it around `$array[$key] ?? null`
 * (or even a bare `$array[$key]`, which already means `$array[$key] ?? []`)
 * spells the dictionary lookup the long way. `T_Array::coalesceFor($array,
 * $key)` is the named home for `$array[$key] ?? []` — one call, no `??`.
 *
 * Only DYNAMIC keys qualify (a variable / property / expression — a genuine
 * dictionary lookup). A LITERAL key (`$array['name']`) is a record access:
 * that is `NoArrayStringIndexing`'s territory (build a DTO), so it is left
 * alone here.
 *
 * Auto-fixable: `repent` rewrites the call to `coalesceFor` (carrying a
 * non-trivial default through as the third argument).
 */
#[IntroducedIn('1.105.0')]
class PreferCoalesceForProphet extends PhpCommandment implements SinRepenter
{
    public function description(): string
    {
        return 'Use T_Array::coalesceFor($array, $key) instead of double-coalescing a dynamic dictionary lookup';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    /**
     * Superseding NoNullCoalesce: it would strip the inner `?? null` to leave
     * `T_Array::coalesce($array[$key])`; this rule rewrites the whole thing to
     * `coalesceFor`, so its fix subsumes that one — defer it in the region.
     *
     * @return list<class-string>
     */
    public function supersedes(): array
    {
        return [NoNullCoalesceToNullProphet::class];
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`T_Array::coalesce($array[$key] ?? null)` is a double coalesce. `coalesce`
already IS the `?? []` substitution, so the inner `?? null` is pure noise —
and even without it, `T_Array::coalesce($array[$key])` is just `$array[$key] ??
[]` spelled the long way.

Bad:
    $targets = T_Array::coalesce($forward[$current] ?? null);
    $out     = T_Array::coalesce($outgoing[$node->id] ?? null);

Good:
    $targets = T_Array::coalesceFor($forward, $current);
    $out     = T_Array::coalesceFor($outgoing, $node->id);

`coalesceFor($array, $key, $default = [])` returns the array at `$key`, or the
default when the key is absent or null — the named home for a dynamic
dictionary lookup, no `??` in sight.

ONLY DYNAMIC KEYS. A variable, property, or expression key (`$forward[$current]`,
`$outgoing[$node->id]`) is a real dictionary lookup → use `coalesceFor`. A
LITERAL key (`$config['label']`) is a record wearing a dictionary's clothes —
that is `NoArrayStringIndexing`'s rule (introduce a DTO), so it is NOT rewritten
here.
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

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{line: int, message: string, snippet: string, symbol: string, penance: string, edit: array{start: int, end: int, text: string}}>
     */
    private function findings(array $ast, string $content): array
    {
        $finder = new NodeFinder;
        $findings = [];

        foreach ($finder->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            $access = $this->coalesceForTarget($call);

            if ($access === null) {
                continue;
            }

            [$arrayExpr, $keyExpr, $default] = $access;

            $className = $this->sourceOf($call->class, $content);
            $replacement = sprintf(
                '%s::coalesceFor(%s, %s%s)',
                $className,
                $this->sourceOf($arrayExpr, $content),
                $this->sourceOf($keyExpr, $content),
                $default !== null ? ', ' . $this->sourceOf($default, $content) : '',
            );

            $line = $call->getStartLine();

            $findings[] = [
                'line' => $line,
                'message' => sprintf(
                    'Double coalesce — `%s::coalesce(...)` around a dynamic dictionary lookup. Use `%s` (coalesceFor IS the `?? []`).',
                    $className,
                    $replacement,
                ),
                'snippet' => $this->lineAt($content, $line),
                'symbol' => 'coalesce-for',
                'penance' => 'Rewrote double-coalesce to coalesceFor()',
                'edit' => [
                    'start' => $call->getStartFilePos(),
                    'end' => $call->getEndFilePos(),
                    'text' => $replacement,
                ],
            ];
        }

        return $findings;
    }

    /**
     * When the call is `T_Array::coalesce(<dynamic dictionary lookup>[ ?? D])`,
     * return [arrayExpr, keyExpr, default|null]; otherwise null.
     *
     * @return array{0: Expr, 1: Expr, 2: Expr|null}|null
     */
    private function coalesceForTarget(Expr\StaticCall $call): ?array
    {
        if (! $call->class instanceof Node\Name
            || $call->class->getLast() !== 'T_Array'
            || ! $call->name instanceof Node\Identifier
            || $call->name->toString() !== 'coalesce'
            || count($call->args) !== 1
            || ! $call->args[0] instanceof Node\Arg
            || $call->args[0]->unpack
        ) {
            return null;
        }

        $arg = $call->args[0]->value;
        $default = null;

        if ($arg instanceof Coalesce) {
            $default = $this->isTrivialDefault($arg->right) ? null : $arg->right;
            $arg = $arg->left;
        }

        if (! $arg instanceof Expr\ArrayDimFetch
            || $arg->dim === null
            || ! $this->isDynamicKey($arg->dim)
        ) {
            return null;
        }

        return [$arg->var, $arg->dim, $default];
    }

    /**
     * A trivial default needs no third argument — `null` (coalesceFor already
     * defaults to []) or an empty array literal.
     */
    private function isTrivialDefault(Expr $expr): bool
    {
        if ($expr instanceof Expr\ConstFetch && strtolower($expr->name->toString()) === 'null') {
            return true;
        }

        return $expr instanceof Expr\Array_ && $expr->items === [];
    }

    /**
     * A dynamic key — a variable / property / expression, a genuine dictionary
     * lookup. Literal scalars and constants are record keys (NoArrayStringIndexing).
     */
    private function isDynamicKey(Expr $dim): bool
    {
        return ! $dim instanceof Node\Scalar\String_
            && ! $dim instanceof Node\Scalar\LNumber
            && ! $dim instanceof Node\Scalar\DNumber
            && ! $dim instanceof Expr\ClassConstFetch
            && ! $dim instanceof Expr\ConstFetch;
    }

    private function sourceOf(Node $node, string $content): string
    {
        return substr($content, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1);
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return trim($lines[$line - 1] ?? '');
    }
}
